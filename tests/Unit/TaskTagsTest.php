<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaskTagsTest extends TestCase
{
    private const CREATE = ['task:add', '--title', 'Tagged task', '--creator', '1', '--responsible', '1'];

    private function api(?Failure $tagFailure = null, ?Failure $firstFailure = null): FakeApiTransport
    {
        return new FakeApiTransport(static function (string $method, int $version) use ($tagFailure, $firstFailure): ApiResponse {
            if ($version === 3 && $firstFailure instanceof \Bitrix24\CLI\Application\Failure) {
                throw $firstFailure;
            }

            if ($version === 1 && $tagFailure instanceof \Bitrix24\CLI\Application\Failure) {
                throw $tagFailure;
            }

            return new ApiResponse(match ([$method, $version]) {
                ['tasks.task.add', 3] => ['item' => ['id' => 123]],
                ['tasks.task.update', 3] => ['result' => true],
                ['tasks.task.update', 1] => ['task' => ['id' => '123']],
                ['tasks.task.get', 3] => ['item' => ['id' => 123, 'tags' => [['id' => 11, 'name' => '#плановая']]]],
                ['tasks.task.field.list', 3] => ['items' => [['name' => 'title', 'type' => 'string', 'editable' => false]]],
                default => throw new \LogicException('Unexpected route.'),
            });
        });
    }

    public function testCreateAndMixedUpdateUseExactVersionSpecificPayloads(): void
    {
        foreach ([self::CREATE, ['task:update', '123', '--title', 'Tagged task']] as $argv) {
            $api = $this->api();
            $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), ['--json', ...$argv, '--tag', '#плановая', '--tag', ' Этап 1 ', '--tag', '#плановая']);
            self::assertSame(0, $run->status, $run->stdout);
            self::assertCount(2, $api->calls);
            self::assertSame(3, $api->calls[0]['version']);
            self::assertArrayNotHasKey('TAGS', $api->calls[0]['parameters']['fields']);
            self::assertArrayNotHasKey('tags', $api->calls[0]['parameters']['fields']);
            self::assertSame(['method' => 'tasks.task.update', 'version' => 1, 'parameters' => ['taskId' => 123, 'fields' => ['TAGS' => ['#плановая', ' Этап 1 ']]], 'effect' => 'write'], $api->calls[1]);
            self::assertTrue($run->json()['data']['operation']['confirmed']);
            self::assertSame(123, $run->json()['data']['operation']['taskId']);
            self::assertContains('TAGS', $run->json()['data']['operation']['changedFields']);
            self::assertTrue($run->json()['meta']['complete']);
            self::assertSame(['confirmed', 'confirmed'], array_column($run->json()['meta']['ledger'], 'status'));
        }
    }

    public function testTagsOnlyReplaceClearAndOmissionAreDistinct(): void
    {
        foreach ([['--tag', '#ошибка'], ['--clear-tags'], ['--title', 'unchanged tags']] as $options) {
            $api = $this->api();
            $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), ['task:update', '123', '--json', ...$options]);
            self::assertSame(0, $run->status, $run->stdout);
            self::assertCount(1, $api->calls);
            if ($options[0] === '--title') {
                self::assertSame(3, $api->calls[0]['version']);
                self::assertSame(['title' => 'unchanged tags'], $api->calls[0]['parameters']['fields']);
            } else {
                self::assertSame(1, $api->calls[0]['version']);
                self::assertSame(['TAGS' => $options[0] === '--tag' ? ['#ошибка'] : []], $api->calls[0]['parameters']['fields']);
            }
        }
    }

    public static function rejectedInputs(): iterable
    {
        foreach ([
            ['task:update', '123', '--tag', ''], ['task:update', '123', '--tag', '   '],
            ['task:update', '123', '--tag', "\u{00A0}"], ['task:update', '123', '--tag', "\xFF"],
            ['task:update', '123', '--tag', '#x', '--clear-tags'],
            ['task:add', '--clear-tags'], [...self::CREATE, '--tag', ''],
            ['task:update', '123', '--tag', '#x', '--fields', '{"title":"x"}'],
            ['task:update', '123', '--clear-tags', '--fields-file', '-'],
            ['task:update', '123', '--fields', '{"tags":["#x"]}'],
            ['task:update', '123', '--fields', '{"TAGS":["#x"]}'],
        ] as $index => $argv) {
            yield (string) $index => [$argv];
        }
    }

    #[DataProvider('rejectedInputs')]
    public function testInvalidInputsStopBeforeCredentialReadsOrRequests(array $argv): void
    {
        $spyConnectionResolver = new SpyConnectionResolver();
        $api = $this->api();
        $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api, $spyConnectionResolver), ['--json', ...$argv], '{"title":"x"}');
        self::assertSame(2, $run->status, $run->stdout);
        self::assertSame('usage-error', $run->json()['error']['code']);
        self::assertSame(0, $spyConnectionResolver->reads);
        self::assertSame([], $api->calls);
        if (str_contains(implode(' ', $argv), '"tags"') || str_contains(implode(' ', $argv), '"TAGS"')) {
            self::assertStringContainsString('--tag', $run->json()['error']['message']);
        }
    }

    public function testStrictPolicyBlocksTheEntireCompositionAndDryRun(): void
    {
        foreach ([[...self::CREATE, '--tag', '#x'], ['task:update', '123', '--title', 'x', '--tag', '#x'], ['task:update', '123', '--clear-tags']] as $argv) {
            foreach ([[], ['--dry-run']] as $dryRun) {
                $api = $this->api();
                $resolver = new SpyConnectionResolver();
                $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api, $resolver), ['--json', '--api-policy', 'strict-rest3', ...$argv, ...$dryRun]);
                self::assertSame(4, $run->status, $run->stdout);
                self::assertSame('policy-denied', $run->json()['error']['code']);
                self::assertSame([], $api->calls);
                self::assertSame(0, $resolver->reads);
            }
        }
    }

    public function testPreviewIncludesCreateIdDependencyAndDoesNotWrite(): void
    {
        $api = $this->api();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $api);
        $run = ConsoleHarness::run($app, ['--json', ...self::CREATE, '--tag', '#плановая', '--dry-run']);
        self::assertSame(0, $run->status, $run->stdout);
        $steps = $run->json()['data']['plan']['steps'];
        self::assertSame(['3.0', '1.0'], array_column($steps, 'apiVersion'));
        self::assertSame([1, 2], array_column($steps, 'step'));
        self::assertSame(['TAGS' => ['#плановая']], $steps[1]['fields']);
        self::assertNull($steps[1]['taskId']);
        self::assertSame(1, $steps[1]['taskIdFromStep']);
        $update = ConsoleHarness::run($app, ['--json', 'task:update', '123', '--clear-tags', '--dry-run'])->json()['data']['plan']['steps'];
        self::assertCount(1, $update);
        self::assertSame(123, $update[0]['taskId']);
        self::assertSame(['TAGS' => []], $update[0]['fields']);
        self::assertSame([], $api->calls);
        foreach ([['--plain'], []] as $mode) {
            $preview = ConsoleHarness::run($app, [...$mode, ...self::CREATE, '--tag', '#плановая', '--dry-run']);
            self::assertSame(0, $preview->status);
            if ($mode === []) {
                self::assertStringContainsString('taskIdFromStep', $preview->stdout);
            } else {
                self::assertStringContainsString('1.0', $preview->stdout);
                $rows = explode(PHP_EOL, trim($preview->stdout));
                self::assertSame('1', explode("\t", $rows[1])[6]);
            }
        }
    }

    public static function writeFailures(): iterable
    {
        yield 'denied' => [new Failure('permission-denied', 'Denied.'), 3, false, 'failed'];
        yield 'lost' => [new Failure('transport-error', 'Response lost.', 1, [], true), 3, true, 'unknown'];
        yield 'cancelled before send' => [new Failure('interrupted', 'Cancelled.', 130), 130, false, 'failed'];
        yield 'cancelled while sent' => [new Failure('interrupted', 'Cancelled.', 130, [], true), 130, true, 'unknown'];
    }

    #[DataProvider('writeFailures')]
    public function testConfirmedFirstStepRemainsVisibleAfterTagFailure(Failure $failure, int $status, bool $unknown, string $stepStatus): void
    {
        foreach ([self::CREATE, ['task:update', '123', '--title', 'x']] as $argv) {
            $api = $this->api($failure);
            $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), ['--json', ...$argv, '--tag', '#x']);
            self::assertSame($status, $run->status, $run->stdout);
            $json = $run->json();
            self::assertSame(123, $json['data']['items'][0]['taskId']);
            self::assertTrue($json['data']['items'][0]['confirmed']);
            self::assertFalse($json['meta']['complete']);
            self::assertSame($unknown, $json['meta']['outcomeUnknown']);
            self::assertSame(['confirmed', $stepStatus], array_column($json['meta']['ledger'], 'status'));
            self::assertStringContainsString('retry only task:update 123', $json['error']['message']);
            self::assertCount(2, $api->calls);
            self::assertNotContains('tasks.task.delete', array_column($api->calls, 'method'));
        }
    }

    public function testSingleUnconfirmedWritesDoNotBecomePartialOrRetry(): void
    {
        foreach ([false, true] as $unknown) {
            $failure = new Failure('api-error', 'Rejected.', 1, [], $unknown);
            foreach ([[self::CREATE, null, $failure], [['task:update', '123', '--tag', '#x'], $failure, null]] as [$argv, $tagFailure, $firstFailure]) {
                $api = $this->api($tagFailure, $firstFailure);
                $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), ['--json', ...$argv, ...($firstFailure instanceof \Bitrix24\CLI\Application\Failure ? ['--tag', '#x'] : [])]);
                self::assertSame(1, $run->status, $run->stdout);
                self::assertSame('api-error', $run->json()['error']['code']);
                self::assertNull($run->json()['data']);
                self::assertSame($unknown, $run->json()['meta']['outcomeUnknown']);
                self::assertCount(1, $api->calls);
            }
        }
    }

    public function testOutputModesRetainPartialTaskIdAndRecovery(): void
    {
        foreach ([[], ['--plain'], ['--json']] as $mode) {
            $api = $this->api(new Failure('api-error', 'Rejected.'));
            $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), [...$mode, ...self::CREATE, '--tag', '#x']);
            self::assertSame(3, $run->status, $run->stdout);
            self::assertStringContainsString('123', $run->stdout);
            self::assertStringContainsString('retry only task:update 123', $run->stderr);
            self::assertStringNotContainsString('secret-fixture', $run->stdout . $run->stderr);
        }
    }

    public function testReadbackUsesExistingRest3TagProjectionAndEditableGateRemains(): void
    {
        $api = $this->api();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $api);
        $run = ConsoleHarness::run($app, ['--json', 'task:show', '123', '--select', 'tags.id', '--select', 'tags.name']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([['id' => 11, 'name' => '#плановая']], $run->json()['data']['task']['tags']);
        self::assertSame(['id', 'tags.id', 'tags.name'], $api->calls[0]['parameters']['select']);
        $run = ConsoleHarness::run($app, ['--json', 'task:update', '123', '--fields', '{"title":"x"}']);
        self::assertSame(2, $run->status, $run->stdout);
        self::assertStringContainsString('not confirmed editable', $run->json()['error']['message']);
        self::assertSame(['tasks.task.get', 'tasks.task.field.list'], array_column($api->calls, 'method'));
    }

    public function testHelpWorksOfflineAndAdvertisesOptions(): void
    {
        $spyConnectionResolver = new SpyConnectionResolver();
        $api = $this->api();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $api, $spyConnectionResolver);
        foreach (['task:add', 'task:update'] as $name) {
            $run = ConsoleHarness::run($app, [$name, '--help']);
            self::assertSame(0, $run->status);
            self::assertStringContainsString('--tag', $run->stdout);
            self::assertStringContainsString('strict-rest3', $run->stdout);
            if ($name === 'task:update') {
                self::assertStringContainsString('--clear-tags', $run->stdout);
            }
        }

        self::assertSame(0, $spyConnectionResolver->reads);
        self::assertSame([], $api->calls);
    }
}
