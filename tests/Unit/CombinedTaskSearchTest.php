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

final class CombinedTaskSearchTest extends TestCase
{
    private static function row(int $id, int $group = 7, int $responsible = 1): array
    {
        return ['id' => $id, 'title' => 'Согласовать договор', 'responsibleId' => $responsible, 'creatorId' => 1, 'groupId' => $group, 'status' => 'in_progress', 'deadline' => '2026-10-15T12:00:00+06:00'];
    }

    private function invoke(array $options, \Closure $response, ?string $stdin = null): array
    {
        $fakeApiTransport = new FakeApiTransport($response);
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        return [ConsoleHarness::run($app, ['--json', 'task:find', '--title', 'ДОГОВОР', ...$options], $stdin), $fakeApiTransport, $spyConnectionResolver];
    }

    public static function filters(): iterable
    {
        yield 'one' => [['--group-id', '7', '--responsible', '1', '--status', 'in_progress', '--due-before', '2026-10-16T00:00:00+06:00'], [3]];
        yield 'empty' => [['--group-id', '9', '--responsible', '1'], []];
        yield 'many' => [['--status', 'in_progress'], [3, 2, 1]];
        yield 'where' => [['--where', '{"groupId":7,"responsibleId":1}'], [3]];
        yield 'contradictory role' => [['--responsible', '2', '--role', 'responsible'], []];
        yield 'role and where' => [['--where', '{"groupId":7}', '--role', 'responsible'], [3]];
    }

    #[DataProvider('filters')]
    public function testZeroOneManyAndRoleMatchesKeepShapeAndLocalPredicates(array $options, array $ids): void
    {
        [$run, $api, $resolver] = $this->invoke($options, static function ($method, $version, array $params): ApiResponse {
            self::assertSame('tasks.task.list', $method);
            self::assertSame(3, $version);
            self::assertSame([], $params['filter']);
            return new ApiResponse(['items' => [self::row(3), self::row(2, 7, 2), self::row(1, 8)]]);
        });
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame($ids, array_column($run->json()['data']['items'], 'id'));
        foreach ($run->json()['data']['items'] as $item) {
            self::assertSame(['id', 'title'], array_keys($item));
        }

        self::assertCount(1, $api->calls);
        self::assertSame(in_array('--role', $options, true) ? 1 : 0, $resolver->reads);
        self::assertSame(['id' => 'DESC'], $run->json()['meta']['order']);
        self::assertTrue($run->json()['meta']['complete']);
    }

    public function testPredicatesAreSelectedAndAppliedOnEveryPageBeforeOutputLimit(): void
    {
        [$run, $api] = $this->invoke(['--group-id', '7', '--responsible', '1', '--status', 'in_progress', '--due-before', '2026-10-16T00:00:00+06:00', '--limit', '1', '--order', 'title:asc'], static function ($method, $version, array $params): ApiResponse {
            self::assertEqualsCanonicalizing(['id', 'title', 'groupId', 'responsibleId', 'status', 'deadline'], $params['select']);
            self::assertSame(['title' => 'ASC', 'id' => 'ASC'], (array) $params['order']);
            self::assertSame([], $params['filter']);
            return new ApiResponse(['items' => $params['pagination']['offset'] === 0 ? array_map(static fn (int $id): array => self::row($id, 8), range(1, 50)) : [self::row(51), self::row(52), [...self::row(53), 'deadline' => null]]]);
        });
        self::assertSame(0, $run->status, $run->stdout);
        self::assertCount(2, $api->calls);
        self::assertSame([['id' => 51, 'title' => 'Согласовать договор']], $run->json()['data']['items']);
        self::assertSame(53, $run->json()['meta']['scanned']);
        self::assertSame(2, $run->json()['meta']['matched']);
        self::assertTrue($run->json()['meta']['limitApplied']);
    }

    public function testRepeatableServerIdsComposeWithWhereFileAndRole(): void
    {
        [$run, $api] = $this->invoke(['--id', '1', '--id', '3', '--where-file', '-', '--role', 'responsible', '--all'], static function ($method, $version, array $params): ApiResponse {
            self::assertSame([['id', 'in', [1, 3]]], $params['filter']);
            return new ApiResponse(['items' => [self::row(3), self::row(1, 8)]]);
        }, '{"groupId":7}');
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([3], array_column($run->json()['data']['items'], 'id'));
        self::assertSame('explicit-task-ids', $run->json()['meta']['scope']);
        self::assertSame(['role' => 'responsible', 'userId' => 1], $run->json()['meta']['roleFilter']);
        self::assertCount(1, $api->calls);
    }

    public function testParticipantReadsOnlyCandidatesAndStrictPolicyNeverCallsApi(): void
    {
        foreach (['auditor', 'accomplice'] as $role) {
            [$run, $api] = $this->invoke(['--group-id', '7', '--responsible', '1', '--role', $role], static function ($method, $version, array $params) use ($role): ApiResponse {
                if ($version === 3) {
                    return new ApiResponse(['items' => [self::row(3), self::row(2, 8), self::row(1, 7, 2)]]);
                }

                self::assertSame('tasks.task.get', $method);
                self::assertSame(3, $params['taskId']);
                $field = $role === 'auditor' ? 'auditors' : 'accomplices';
                return new ApiResponse(['task' => ['id' => '3', $field => ['1']]]);
            });
            self::assertSame(0, $run->status, $run->stdout);
            self::assertSame([3], array_column($run->json()['data']['items'], 'id'));
            self::assertCount(2, $api->calls);
            [$run, $api, $resolver] = $this->invoke(['--where', '{"groupId":7}', '--role', $role, '--api-policy', 'strict-rest3'], static fn () => throw new \LogicException('Unexpected API.'));
            self::assertSame(4, $run->status);
            self::assertSame([], $api->calls);
            self::assertSame(0, $resolver->reads);
        }
    }

    public static function invalid(): iterable
    {
        foreach ([['--id', '0'], ['--responsible', '-1'], ['--group-id', '0'], ['--due-before', '2026-10-15'], ['--where', '[]'], ['--where', '{"project":7}'], ['--where', '{}', '--responsible', '1'], ['--where', '{}', '--due-before', '2026-10-15T00:00:00Z'], ['--where', '{}', '--where-file', '-'], ['--config', '-', '--where-file', '-'], ['--project', '7'], ['--limit', '1', '--all']] as $options) {
            yield implode(' ', $options) => [$options];
        }
    }

    #[DataProvider('invalid')]
    public function testInvalidAndConflictingInputNeverCallsApi(array $options): void
    {
        [$run, $api, $resolver] = $this->invoke($options, static fn () => throw new \LogicException('Unexpected API.'), '{}');
        self::assertSame(2, $run->status, $run->stdout);
        self::assertSame('usage-error', $run->json()['error']['code']);
        self::assertSame([], $api->calls);
        self::assertSame(0, $resolver->reads);
    }

    public function testMissingPredicateCapAndFailedSecondPageRemainPartial(): void
    {
        foreach (['missing-predicate-field', 'scan-budget-exhausted', 'page-error'] as $reason) {
            [$run] = $this->invoke(['--responsible', '1', ...($reason === 'scan-budget-exhausted' ? ['--max-scan', '1'] : [])], static function ($method, $version, array $params) use ($reason): ApiResponse {
                if ($params['pagination']['offset'] > 0) {
                    throw new Failure('api-error', 'Fixture page error');
                }

                $rows = $reason === 'missing-predicate-field' ? [['id' => 2, 'title' => 'Договор']] : array_map(static fn (int $id): array => self::row($id), range(1, 50));
                return new ApiResponse(['items' => $rows]);
            });
            self::assertSame(3, $run->status, $run->stdout);
            self::assertFalse($run->json()['meta']['complete']);
            self::assertSame($reason, $run->json()['meta']['reason']);
        }
    }
    public function testPlainSearchColumnsAndNativeVerbosityRemainStable(): void
    {
        foreach (['--plain', '--json'] as $format) {
            foreach ([[], ['--quiet'], ['--silent'], ['--no-ansi', '--no-interaction']] as $flags) {
                $api = new FakeApiTransport(static fn (): ApiResponse => new ApiResponse(['items' => [self::row(3), self::row(2, 8)]]));
                $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), ['task:find', '--title', 'ДОГОВОР', '--responsible', '1', '--group-id', '7', $format, ...$flags]);
                self::assertSame(0, $run->status, $run->stdout . $run->stderr);
                self::assertStringNotContainsString("\033", $run->stdout . $run->stderr);
                if (in_array('--quiet', $flags, true) || in_array('--silent', $flags, true)) {
                    self::assertSame('', $run->stdout . $run->stderr);
                } elseif ($format === '--plain') {
                    self::assertSame("3\tСогласовать договор\n", $run->stdout);
                    self::assertStringContainsString('complete=true', $run->stderr);
                } else {
                    self::assertSame([['id' => 3, 'title' => 'Согласовать договор']], $run->json()['data']['items']);
                    self::assertSame('', $run->stderr);
                }
            }
        }
    }

    public function testFirstPageAndRoleReadFailuresPreserveTheirBoundaries(): void
    {
        [$run] = $this->invoke(['--responsible', '1'], static fn () => throw new Failure('api-error', 'Fixture first-page failure'));
        self::assertSame(1, $run->status);
        self::assertSame('api-error', $run->json()['error']['code']);
        [$run] = $this->invoke(['--role', 'auditor', '--where', '{"groupId":7}'], static function ($method, $version, array $params): ApiResponse {
            if ($version === 3) {
                return new ApiResponse(['items' => [self::row(3), self::row(2)]]);
            }

            if ($params['taskId'] === 2) {
                throw new Failure('api-error', 'Fixture participant failure');
            }

            return new ApiResponse(['task' => ['id' => '3', 'auditors' => ['1']]]);
        });
        self::assertSame(3, $run->status, $run->stdout);
        self::assertSame('role-read-error', $run->json()['meta']['reason']);
        self::assertSame([3], array_column($run->json()['data']['items'], 'id'));
    }

}
