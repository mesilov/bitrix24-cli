<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Application\Task\Handler\AddTaskHandler;
use Bitrix24\CLI\Application\Task\Request\AddTaskRequest;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest1ParticipantAdapter;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest3TaskAdapter;
use Bitrix24\CLI\Infrastructure\Connection\FileCredentialStore;
use Bitrix24\CLI\Infrastructure\Connection\FileProtection;
use Bitrix24\CLI\Infrastructure\Connection\ProfileRepository;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DefaultObserverTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/b24cli-observer-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }

            rmdir($this->directory);
        }
    }

    private function profile(array $roles): void
    {
        $fileProtection = new FileProtection();
        (new ProfileRepository($fileProtection, new FileCredentialStore($fileProtection)))->add($this->directory, 'work', 'https://work.invalid/rest/1/RecognizableFixtureSecret/', null, ['creatorId' => 1, 'responsibleId' => 2], $roles);
    }

    private function response(string $method, int $version, array $parameters): ApiResponse
    {
        return match ($method) {
            'tasks.task.add' => new ApiResponse(['item' => ['id' => 123]]),
            'tasks.task.get' => new ApiResponse(['task' => ['id' => '123', 'auditors' => ['6']]]),
            'tasks.task.update' => new ApiResponse(['task' => ['id' => '123']]),
            'tasks.task.field.list' => new ApiResponse(['items' => array_map(static fn ($name): array => ['name' => $name, 'type' => match ($name) {
                'title' => 'string', 'allowsTimeTracking' => 'boolean', default => 'integer'
            }, 'editable' => true], ['title', 'creatorId', 'responsibleId', 'allowsTimeTracking'])]),
            default => throw new \LogicException('Unexpected observer workflow method.'),
        };
    }

    private function invoke(array $argv = [], ?\Closure $respond = null): array
    {
        $fakeApiTransport = new FakeApiTransport($respond ?? $this->response(...));
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver, true);
        $run = ConsoleHarness::run($app, ['--json', '--config-dir', $this->directory, '--profile', 'work', 'task:add', ...($argv === [] ? ['--title', 'Fixture'] : $argv)]);
        return [$run->status, $run->json(), $fakeApiTransport->calls, $spyConnectionResolver->reads];
    }

    public function testObserverIsAddedWithoutReplacingOtherAuditorsOrEnteringRest3Fields(): void
    {
        $this->profile(['observerId' => 5, 'autoAddObserver' => true]);
        [$status, $json, $calls] = $this->invoke();
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(123, $json['data']['operation']['taskId']);
        self::assertTrue($json['meta']['complete']);
        self::assertSame(['tasks.task.add', 'tasks.task.get', 'tasks.task.update'], array_column($calls, 'method'));
        self::assertSame([3, 1, 1], array_column($calls, 'version'));
        self::assertSame(['taskId' => 123, 'fields' => ['AUDITORS' => [6, 5]]], $calls[2]['parameters']);
        self::assertSame(['title' => 'Fixture', 'creatorId' => 1, 'responsibleId' => 2, 'allowsTimeTracking' => false], $calls[0]['parameters']['fields']);
        self::assertSame('profile-default', $json['meta']['fieldSources']['default.users.observerId']);
    }

    public function testExistingObserverSkipsRedundantUpdate(): void
    {
        $this->profile(['observerId' => 5, 'autoAddObserver' => true]);
        [$status, $json, $calls] = $this->invoke(respond: fn ($method, $version, $params): ApiResponse => $method === 'tasks.task.get' ? new ApiResponse(['task' => ['id' => '123', 'auditors' => ['5', '6']]]) : $this->response($method, $version, $params));
        self::assertSame(0, $status);
        self::assertCount(2, $calls);
        self::assertSame('already-present', $json['meta']['ledger'][2]['status']);
    }

    public static function disabledRoles(): iterable
    {
        yield 'omitted' => [[]];
        yield 'ID only' => [['observerId' => 5]];
        yield 'explicit false' => [['observerId' => 5, 'autoAddObserver' => false]];
        yield 'manager only' => [['managerId' => 5]];
    }

    #[DataProvider('disabledRoles')]
    public function testDisabledObserverKeepsSingleRest3Creation(array $roles): void
    {
        $this->profile($roles);
        [$status, $json, $calls] = $this->invoke();
        self::assertSame(0, $status);
        self::assertCount(1, $calls);
        self::assertArrayNotHasKey('observerId', $json['meta']);
    }

    public function testStrictPolicyAndDryRunReadNoCredentialsOrApi(): void
    {
        $this->profile(['observerId' => 5, 'autoAddObserver' => true]);
        [$status, $json, $calls, $reads] = $this->invoke(['--title', 'Fixture', '--api-policy', 'strict-rest3']);
        self::assertSame(4, $status);
        self::assertSame([], $calls);
        self::assertSame(0, $reads);
        [$status, $json, $calls, $reads] = $this->invoke(['--title', 'Fixture', '--dry-run']);
        self::assertSame(0, $status);
        self::assertSame([], $calls);
        self::assertSame(0, $reads);
        self::assertCount(3, $json['data']['plan']['steps']);
        self::assertTrue($json['data']['plan']['steps'][2]['mergeWithExisting']);
        self::assertSame([5], $json['data']['plan']['steps'][2]['fields']['AUDITORS']);
    }

    public function testAdvancedCreationStillUsesSeparateObserverCompanion(): void
    {
        $this->profile(['observerId' => 5, 'autoAddObserver' => true]);
        [$status, $json, $calls] = $this->invoke(['--fields', '{"title":"Advanced"}']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(['tasks.task.add', 'tasks.task.get', 'tasks.task.update'], array_column($calls, 'method'));
    }

    public static function failures(): iterable
    {
        yield 'read failure' => ['tasks.task.get', false];
        yield 'write denied' => ['tasks.task.update', false];
        yield 'write outcome unknown' => ['tasks.task.update', true];
    }

    #[DataProvider('failures')]
    public function testCompanionFailureRetainsConfirmedTaskIdAndNeverRepeatsCreate(string $failed, bool $unknown): void
    {
        $this->profile(['observerId' => 5, 'autoAddObserver' => true]);
        [$status, $json, $calls] = $this->invoke(respond: fn ($method, $version, $params): ApiResponse => $method === $failed ? throw new Failure('transport-error', 'Observer fixture failure.', 1, [], $unknown) : $this->response($method, $version, $params));
        self::assertSame(3, $status);
        self::assertSame(123, $json['data']['operation']['taskId']);
        self::assertSame('partial-result', $json['error']['code']);
        self::assertFalse($json['meta']['complete']);
        self::assertSame($unknown, $json['meta']['outcomeUnknown']);
        self::assertSame('confirmed', $json['meta']['ledger'][0]['status']);
        self::assertCount(1, array_filter($calls, static fn (array $call): bool => $call['method'] === 'tasks.task.add'));
        self::assertStringContainsString('task:show 123', $json['meta']['reason']);
    }

    public function testUnconfirmedCreationDoesNotStartObserverSteps(): void
    {
        $this->profile(['observerId' => 5, 'autoAddObserver' => true]);
        [$status, $json, $calls] = $this->invoke(respond: static fn (): ApiResponse => throw new Failure('transport-error', 'Create unknown.', 1, [], true));
        self::assertSame(1, $status);
        self::assertTrue($json['meta']['outcomeUnknown']);
        self::assertCount(1, $calls);
    }

    public function testInvalidParticipantResponseCannotClearOtherObservers(): void
    {
        $this->profile(['observerId' => 5, 'autoAddObserver' => true]);
        [$status, $json, $calls] = $this->invoke(respond: fn ($method, $version, $params): ApiResponse => $method === 'tasks.task.get' ? new ApiResponse(['task' => ['id' => 123]]) : $this->response($method, $version, $params));
        self::assertSame(3, $status);
        self::assertSame(123, $json['data']['operation']['taskId']);
        self::assertCount(2, $calls);
    }

    public function testTagsAndObserverShareOneCreationAndFullPreview(): void
    {
        $this->profile(['observerId' => 5, 'autoAddObserver' => true]);
        [$status, $json, $calls] = $this->invoke(['--title', 'Fixture', '--tag', '#plan']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(['tasks.task.add', 'tasks.task.update', 'tasks.task.get', 'tasks.task.update'], array_column($calls, 'method'));
        self::assertSame([3, 1, 1, 1], array_column($calls, 'version'));
        self::assertSame(['TAGS' => ['#plan']], $calls[1]['parameters']['fields']);
        self::assertSame(['AUDITORS' => [6, 5]], $calls[3]['parameters']['fields']);
        self::assertSame(['confirmed', 'confirmed', 'confirmed', 'confirmed'], array_column($json['meta']['ledger'], 'status'));
        self::assertContains('TAGS', $json['data']['operation']['changedFields']);
        [$status, $json, $calls, $reads] = $this->invoke(['--title', 'Fixture', '--tag', '#plan', '--dry-run']);
        self::assertSame(0, $status);
        self::assertSame([1, 2, 3, 4], array_column($json['data']['plan']['steps'], 'step'));
        self::assertSame([], $calls);
        self::assertSame(0, $reads);
    }

    public function testFailedTagsSkipObserverAndRetainCreation(): void
    {
        $this->profile(['observerId' => 5, 'autoAddObserver' => true]);
        [$status, $json, $calls] = $this->invoke(['--title', 'Fixture', '--tag', '#plan'], respond: fn ($method, $version, $params): ApiResponse => $method === 'tasks.task.update' ? throw new Failure('transport-error', 'Tag failure.', 1, [], true) : $this->response($method, $version, $params));
        self::assertSame(3, $status);
        self::assertSame(123, $json['data']['items'][0]['taskId']);
        self::assertSame(['confirmed', 'unknown', 'skipped', 'skipped'], array_column($json['meta']['ledger'], 'status'));
        self::assertTrue($json['meta']['outcomeUnknown']);
        self::assertCount(2, $calls);
    }

    public function testFailedObserverRetainsConfirmedTagWrite(): void
    {
        $this->profile(['observerId' => 5, 'autoAddObserver' => true]);
        [$status, $json, $calls] = $this->invoke(['--title', 'Fixture', '--tag', '#plan'], respond: fn ($method, $version, $params): ApiResponse => $method === 'tasks.task.get' ? throw new Failure('permission-denied', 'Observer read denied.') : $this->response($method, $version, $params));
        self::assertSame(3, $status);
        self::assertSame(123, $json['data']['operation']['taskId']);
        self::assertContains('TAGS', $json['data']['operation']['changedFields']);
        self::assertSame(['confirmed', 'confirmed', 'failed', 'skipped'], array_column($json['meta']['ledger'], 'status'));
        self::assertCount(3, $calls);
    }

    public function testCustomCreationKeepsProfileDefaultsTagsAndObserver(): void
    {
        $this->profile(['observerId' => 5, 'autoAddObserver' => true]);
        foreach ([null, 'tags', 'observer'] as $failed) {
            [$status, $json, $calls] = $this->invoke(['--title', 'Custom', '--user-fields', '{"UF_QUERY":"5"}', '--tag', '#plan'], respond: function ($method, int $version, array $params) use ($failed): ApiResponse {
                if ($method === 'tasks.task.getFields') {
                    return new ApiResponse(['fields' => ['UF_QUERY' => ['type' => 'string']]]);
                }

                if ($method === 'tasks.task.add') {
                    return new ApiResponse(['task' => ['id' => '123']]);
                }

                if (($failed === 'tags' && isset($params['fields']['TAGS'])) || ($failed === 'observer' && $method === 'tasks.task.get')) {
                    throw new Failure('permission-denied', 'Companion denied.');
                }

                return $this->response($method, $version, $params);
            });
            self::assertSame($failed === null ? 0 : 3, $status, json_encode($json));
            self::assertSame([1, 1], array_slice(array_column($calls, 'version'), 0, 2));
            self::assertSame(['TITLE' => 'Custom', 'CREATED_BY' => 1, 'RESPONSIBLE_ID' => 2, 'ALLOW_TIME_TRACKING' => 'N', 'UF_QUERY' => '5'], $calls[1]['parameters']['fields']);
            self::assertSame(123, $json['data']['operation']['taskId'] ?? $json['data']['items'][0]['taskId']);
            self::assertCount($failed === 'tags' ? 3 : ($failed === 'observer' ? 4 : 5), $calls);
        }
    }

    public function testCancellationAfterCreationStopsAllCompanionCalls(): void
    {
        $runtimeState = new RuntimeState();
        $fakeApiTransport = new FakeApiTransport(static function () use ($runtimeState): ApiResponse {
            $runtimeState->cancelled = true;
            return new ApiResponse(['item' => ['id' => 123]]);
        });
        $addTaskHandler = new AddTaskHandler(new Rest3TaskAdapter($fakeApiTransport), new Rest1ParticipantAdapter($fakeApiTransport), $runtimeState, new \Bitrix24\CLI\Application\Task\TaskTagMutation(new \Bitrix24\CLI\Infrastructure\Bitrix24\Rest1TaskTagAdapter($fakeApiTransport)), new \Bitrix24\CLI\Infrastructure\Bitrix24\Rest1TaskUserFieldAdapter($fakeApiTransport));
        $operation = $addTaskHandler->prepare(new AddTaskRequest(['title' => 'Fixture', 'creatorId' => 1, 'responsibleId' => 1], false, 5));
        $result = ($operation->execute)();
        self::assertSame(130, $result->exitStatus);
        self::assertSame(123, $result->data['operation']['taskId']);
        self::assertCount(1, $fakeApiTransport->calls);
        self::assertFalse($result->meta['complete']);
    }
}
