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
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TaskRoleFilterTest extends TestCase
{
    public static function roles(): iterable
    {
        foreach (['task:list', 'task:find'] as $command) {
            foreach (['responsible', 'creator', 'accomplice', 'auditor'] as $role) {
                yield $command . ':' . $role => [$command, $role];
            }
        }
    }

    private function invoke(string $command, array $options, \Closure $response, string $mode = '--json'): array
    {
        $fakeApiTransport = new FakeApiTransport($response);
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        $argv = [$mode, $command, ...($command === 'task:find' ? ['--title', 'ДОГОВОР'] : []), ...$options];
        return [ConsoleHarness::run($app, $argv), $fakeApiTransport, $spyConnectionResolver];
    }

    private static function row(int $id, int $actor = 1): array
    {
        return ['id' => $id, 'title' => 'Договор', 'responsibleId' => $actor, 'creatorId' => $actor, 'groupId' => 42, 'status' => 'completed', 'deadline' => null];
    }

    #[DataProvider('roles')]
    public function testEachRoleKeepsOnlyVerifiedMatches(string $command, string $role): void
    {
        [$run, $api, $resolver] = $this->invoke($command, ['--role', $role], static function ($method, $version, array $params) use ($role): ApiResponse {
            if ($version === 3) {
                self::assertSame('tasks.task.list', $method);
                self::assertSame([], $params['filter']);
                self::assertNotContains('accomplices.id', $params['select']);
                self::assertNotContains('auditors.id', $params['select']);
                return new ApiResponse(['items' => [self::row(8, 2), self::row(9), self::row(10)]]);
            }

            $field = $role === 'accomplice' ? 'accomplices' : 'auditors';
            self::assertSame('tasks.task.get', $method);
            self::assertSame(['ID', strtoupper($field)], $params['select']);
            return new ApiResponse(['task' => ['id' => (string) $params['taskId'], $field => $params['taskId'] === 8 ? [] : ['1']]]);
        });
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame([9, 10], array_column($run->json()['data']['items'], 'id'));
        self::assertSame(['role' => $role, 'userId' => 1], $run->json()['meta']['roleFilter']);
        self::assertTrue($run->json()['meta']['complete']);
        self::assertSame(1, $resolver->reads);
        self::assertSame(['read'], array_values(array_unique(array_column($api->calls, 'effect'))));
        self::assertCount(in_array($role, ['responsible', 'creator'], true) ? 1 : 4, $api->calls);
        if ($command === 'task:find') {
            self::assertSame(['id', 'title'], array_keys($run->json()['data']['items'][0]));
        }
    }

    public function testInvalidRolesAndStrictParticipantPolicyDoNotReadCredentials(): void
    {
        foreach (['task:list', 'task:find'] as $command) {
            foreach (['', 'unknown', 'responsible,auditor', 'auditors', 'помогаю'] as $role) {
                [$run, $api, $resolver] = $this->invoke($command, ['--role', $role], static fn () => throw new \LogicException('Unexpected API.'));
                self::assertSame(2, $run->status, $run->stdout);
                self::assertSame('usage-error', $run->json()['error']['code']);
                self::assertSame([], $api->calls);
                self::assertSame(0, $resolver->reads);
            }

            foreach (['accomplice', 'auditor'] as $role) {
                [$run, $api, $resolver] = $this->invoke($command, ['--role', $role, '--api-policy', 'strict-rest3'], static fn () => throw new \LogicException('Unexpected API.'));
                self::assertSame(4, $run->status, $run->stdout);
                self::assertSame('policy-denied', $run->json()['error']['code']);
                self::assertSame([], $api->calls);
                self::assertSame(0, $resolver->reads);
            }
        }
    }

    public function testOmittedRoleAndOfflineHelpCompletionDoNotReadIdentity(): void
    {
        foreach (['task:list', 'task:find'] as $command) {
            [$run, $api, $resolver] = $this->invoke($command, [], static fn (): ApiResponse => new ApiResponse(['items' => []]));
            self::assertSame(0, $run->status, $run->stdout);
            self::assertArrayNotHasKey('roleFilter', $run->json()['meta']);
            self::assertSame(0, $resolver->reads);
            self::assertCount(1, $api->calls);
            $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $api, $resolver);
            $help = ConsoleHarness::run($app, [$command, '--help']);
            self::assertSame(0, $help->status);
            self::assertStringContainsString('strict-rest3', $help->stdout);
            foreach (['responsible', 'accomplice', 'creator', 'auditor'] as $role) {
                self::assertStringContainsString($role, $help->stdout);
            }

            $completion = ConsoleHarness::run($app, ['_complete', '--shell', 'bash', '--input', 'b24cli', '--input', $command, '--input=--role=', '--current', '2']);
            self::assertSame(0, $completion->status, $completion->stdout . $completion->stderr);
            self::assertEqualsCanonicalizing(['responsible', 'accomplice', 'creator', 'auditor'], preg_split('/\s+/', trim($completion->stdout)));
            self::assertSame(0, $resolver->reads);
            self::assertCount(1, $api->calls);
        }
    }

    public function testRoleComposesWithAdvancedWhereAndContradictoryResponsible(): void
    {
        $respond = static fn (): ApiResponse => new ApiResponse(['items' => [self::row(1), [...self::row(2), 'responsibleId' => 2], self::row(3, 2)]]);
        [$run] = $this->invoke('task:list', ['--role', 'creator', '--where', '{"responsibleId":2}', '--api-policy', 'strict-rest3'], $respond);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([2], array_column($run->json()['data']['items'], 'id'));
        [$run] = $this->invoke('task:list', ['--role', 'responsible', '--responsible', '2'], $respond);
        self::assertSame(0, $run->status);
        self::assertSame([], $run->json()['data']['items']);
        [$run, $api, $resolver] = $this->invoke('task:list', ['--role', 'creator', '--where', '{"groupId":42}', '--group-id', '42'], $respond);
        self::assertSame(2, $run->status);
        self::assertSame([], $api->calls);
        self::assertSame(0, $resolver->reads);
    }

    public function testInternalCreatorFieldIsOnlyReturnedWhenOtherwiseRequested(): void
    {
        foreach ([[], ['--select', 'creatorId'], ['--where', '{"creatorId":1}']] as $options) {
            [$run] = $this->invoke('task:list', ['--role', 'creator', ...$options], static fn (): ApiResponse => new ApiResponse(['items' => [self::row(1)]]));
            self::assertSame(0, $run->status, $run->stdout);
            self::assertSame($options !== [], array_key_exists('creatorId', $run->json()['data']['items'][0]));
        }
    }

    public function testUnavailableRoleIdentityDoesNotCallApi(): void
    {
        $fakeApiTransport = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected API.'));
        $resolver = new class () implements \Bitrix24\CLI\Infrastructure\Connection\ConnectionResolver {
            public function webhook(): string
            {
                throw new Failure('connection-unavailable', 'The selected webhook is unavailable.');
            }
        };
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $resolver);
        $run = ConsoleHarness::run($app, ['--json', 'task:list', '--role', 'creator']);
        self::assertSame(1, $run->status, $run->stdout);
        self::assertSame('connection-unavailable', $run->json()['error']['code']);
        self::assertSame([], $fakeApiTransport->calls);
    }

    public function testOtherPredicatesAndDuplicateIdsAvoidParticipantReads(): void
    {
        [$run, $api] = $this->invoke('task:find', ['--role', 'auditor', '--group-id', '42'], static function ($method, $version, array $params): ApiResponse {
            if ($version === 3) {
                return new ApiResponse(['items' => [[...self::row(1), 'groupId' => 7], [...self::row(2), 'title' => 'Другое'], self::row(3), self::row(3)]]);
            }

            self::assertSame(3, $params['taskId']);
            return new ApiResponse(['task' => ['id' => '3', 'auditors' => ['1']]]);
        });
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([['id' => 3, 'title' => 'Договор']], $run->json()['data']['items']);
        self::assertCount(2, $api->calls);
    }

    public function testParticipantRoleComposesWithListStatusGroupAndDeadline(): void
    {
        [$run, $api] = $this->invoke('task:list', ['--role', 'auditor', '--group-id', '42', '--status', 'in_progress', '--due-before', '2026-10-13T00:00:00Z'], static function ($method, $version, array $params): ApiResponse {
            if ($version === 3) {
                self::assertSame([], $params['filter']);
                return new ApiResponse(['items' => [
                    [...self::row(1), 'status' => 'in_progress', 'deadline' => '2026-10-12T00:00:00Z'],
                    [...self::row(2), 'groupId' => 7, 'status' => 'in_progress', 'deadline' => '2026-10-12T00:00:00Z'],
                    [...self::row(3), 'deadline' => '2026-10-12T00:00:00Z'],
                    [...self::row(4), 'status' => 'in_progress', 'deadline' => '2026-10-20T00:00:00Z'],
                ]]);
            }

            self::assertSame(1, $params['taskId']);
            return new ApiResponse(['task' => ['id' => '1', 'auditors' => ['1']]]);
        });
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([1], array_column($run->json()['data']['items'], 'id'));
        self::assertCount(2, $api->calls);
        self::assertTrue($run->json()['meta']['complete']);
    }

    #[DataProvider('roles')]
    public function testSecondPageLimitAndBudgetAreIndependent(string $command, string $role): void
    {
        $response = static function ($method, $version, array $params): ApiResponse {
            if ($version === 1) {
                $field = strtolower($params['select'][1]);
                return new ApiResponse(['task' => ['id' => (string) $params['taskId'], $field => in_array($params['taskId'], [2, 51], true) ? ['1'] : []]]);
            }

            return new ApiResponse(['items' => $params['pagination']['offset'] === 0
                ? array_map(static fn (int $id): array => self::row($id, $id === 2 ? 1 : 2), range(1, 50))
                : [self::row(51)]]);
        };
        [$run] = $this->invoke($command, ['--role', $role, '--limit', '1'], $response);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([2], array_column($run->json()['data']['items'], 'id'));
        self::assertSame(51, $run->json()['meta']['scanned']);
        self::assertSame(2, $run->json()['meta']['matched']);
        self::assertTrue($run->json()['meta']['limitApplied']);
        [$run, $api] = $this->invoke($command, ['--role', $role, '--all', '--max-scan', '50'], $response);
        self::assertSame(3, $run->status, $run->stdout);
        self::assertSame('scan-budget-exhausted', $run->json()['meta']['reason']);
        self::assertSame([2], array_column($run->json()['data']['items'], 'id'));
        self::assertLessThanOrEqual(51, count($api->calls));
    }

    public function testMissingOrMalformedScalarRoleIsIncomplete(): void
    {
        foreach (['task:list', 'task:find'] as $command) {
            foreach (['responsible' => 'responsibleId', 'creator' => 'creatorId'] as $role => $field) {
                foreach ([null, false, [], 1.0, 0, -1, '1x', '9999999999999999999999999999999999999999'] as $value) {
                    [$run] = $this->invoke($command, ['--role', $role, '--api-policy', 'strict-rest3'], static fn (): ApiResponse => new ApiResponse(['items' => [[...self::row(1), $field => $value], self::row(2)]]));
                    self::assertSame(3, $run->status, $run->stdout);
                    self::assertSame([2], array_column($run->json()['data']['items'], 'id'));
                    self::assertSame('missing-predicate-field', $run->json()['meta']['reason']);
                }
            }
        }
    }

    public function testUnknownMembershipStopsWithoutRetryAndRetainsMatches(): void
    {
        foreach (['task:list', 'task:find'] as $command) {
            foreach (['error', 'foreign-id', 'malformed', 'missing'] as $failure) {
                [$run, $api] = $this->invoke($command, ['--role', 'auditor'], static function ($method, $version, array $params) use ($failure): ApiResponse {
                    if ($version === 3) {
                        return new ApiResponse(['items' => [self::row(1), self::row(2), self::row(3)]]);
                    }

                    if ($params['taskId'] === 1) {
                        return new ApiResponse(['task' => ['id' => '1', 'auditors' => ['1']]]);
                    }

                    return match ($failure) {
                        'error' => throw new Failure('permission-denied', 'Denied'),
                        'foreign-id' => new ApiResponse(['task' => ['id' => '99', 'auditors' => ['1']]]),
                        'malformed' => new ApiResponse(['task' => ['id' => '2', 'auditors' => [true]]]),
                        default => new ApiResponse(['task' => ['id' => '2']]),
                    };
                });
                self::assertSame(3, $run->status, $run->stdout);
                self::assertSame([1], array_column($run->json()['data']['items'], 'id'));
                self::assertSame('role-read-error', $run->json()['meta']['reason']);
                self::assertCount(3, $api->calls);
            }
        }
    }

    public function testConfirmedEmptyRoleAndOutputShapes(): void
    {
        foreach (['task:list', 'task:find'] as $command) {
            foreach (['--json', '--plain', '--no-ansi'] as $mode) {
                [$run] = $this->invoke($command, ['--role', 'auditor'], static fn ($method, $version): ApiResponse => $version === 3 ? new ApiResponse(['items' => [self::row(1)]]) : new ApiResponse(['task' => ['id' => '1', 'auditors' => []]]), $mode);
                self::assertSame(0, $run->status, $run->stdout);
                if ($mode === '--json') {
                    self::assertSame([], $run->json()['data']['items']);
                    self::assertTrue($run->json()['meta']['complete']);
                } elseif ($mode === '--plain') {
                    self::assertSame('', $run->stdout);
                } else {
                    self::assertStringContainsString('id', $run->stdout);
                }
            }
        }
    }

    public function testCancellationBeforeCompanionReadStopsRequests(): void
    {
        if (!function_exists('pcntl_signal')) {
            self::markTestSkipped('Requires pcntl signal delivery.');
        }

        [$run, $api] = $this->invoke('task:list', ['--role', 'auditor'], static function (): ApiResponse {
            posix_kill(getmypid(), SIGINT);
            return new ApiResponse(['items' => [self::row(1)]]);
        });
        self::assertSame(130, $run->status, $run->stdout);
        self::assertCount(1, $api->calls);
    }

    public function testSdkAccountingDeclaresBothVersionsWithoutUserLookup(): void
    {
        $time = ['start' => 1, 'finish' => 2, 'duration' => 1, 'processing' => 0, 'date_start' => '2026-10-13T00:00:00+00:00', 'date_finish' => '2026-10-13T00:00:01+00:00'];
        $mockHttpClient = new MockHttpClient([
            new MockResponse(json_encode(['result' => ['task']], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['result' => ['items' => [self::row(1)]], 'time' => $time], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['result' => ['task' => ['id' => '1', 'auditors' => ['1']]], 'time' => $time], JSON_THROW_ON_ERROR)),
        ]);
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), resolver: new SpyConnectionResolver(), httpClient: $mockHttpClient);
        $directory = sys_get_temp_dir() . '/b24cli-role-scope-' . bin2hex(random_bytes(6));
        try {
            $run = ConsoleHarness::run($app, ['--config-dir', $directory, '--json', 'task:list', '--role', 'auditor']);
        } finally {
            (new \Symfony\Component\Filesystem\Filesystem())->remove($directory);
        }

        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame(['1.0', '3.0'], $run->json()['meta']['apiVersions']);
        self::assertSame(['scope', 'tasks.task.list', 'tasks.task.get'], array_column($run->json()['meta']['apiCalls'], 'method'));
        self::assertSame(['read', 'read', 'read'], array_column($run->json()['meta']['apiCalls'], 'effect'));
    }
}
