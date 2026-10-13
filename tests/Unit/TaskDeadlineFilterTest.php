<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\DeadlineWindowResolver;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaskDeadlineFilterTest extends TestCase
{
    private function invoke(string $command, array $options, \Closure $respond, string $format = '--json'): array
    {
        $fakeApiTransport = new FakeApiTransport($respond);
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        $run = ConsoleHarness::run($app, [$format, $command, ...($command === 'task:find' ? ['--title', 'ЁЖ['] : []), ...$options]);
        return [$run, $fakeApiTransport, $spyConnectionResolver];
    }

    public static function commands(): iterable
    {
        yield 'list' => ['task:list'];
        yield 'find' => ['task:find'];
    }

    private static function row(int $id, string $deadline, string $status = '2'): array
    {
        return ['id' => (string) $id, 'title' => 'Новый ёж[ договор', 'responsibleId' => '1', 'createdBy' => '1', 'groupId' => '42', 'status' => $status, 'deadline' => $deadline];
    }

    #[DataProvider('commands')]
    public function testServerPayloadAndStatusNormalization(string $command): void
    {
        [$run, $api] = $this->invoke($command, ['--deadline-window', 'this-quarter', '--timezone', 'Asia/Bishkek', '--group-id', '42'], static function ($method, $version, array $params): ApiResponse {
            self::assertSame('tasks.task.list', $method);
            self::assertSame(1, $version);
            $filter = (array) $params['filter'];
            self::assertSame(42, $filter['GROUP_ID']);
            self::assertArrayHasKey('>=DEADLINE', $filter);
            self::assertArrayHasKey('<DEADLINE', $filter);
            self::assertArrayNotHasKey('deadline', $filter);
            self::assertSame(['ID' => 'DESC'], (array) $params['order']);
            self::assertSame(0, $params['start']);
            self::assertContains('DEADLINE', $params['select']);
            return new ApiResponse(['tasks' => [self::row(9, $filter['>=DEADLINE'], '5')]]);
        });
        self::assertSame(0, $run->status, $run->stdout);
        $json = $run->json();
        self::assertSame([9], array_column($json['data']['items'], 'id'));
        self::assertSame('server', $json['meta']['deadlineFilter']['deadlineApplied']);
        self::assertSame('Asia/Bishkek', $json['meta']['deadlineFilter']['timezone']);
        self::assertSame('server', $json['meta']['deadlineFilter']['groupApplied']);
        self::assertCount(1, $api->calls);
        if ($command === 'task:list') {
            self::assertSame('completed', $json['data']['items'][0]['status']);
            self::assertSame(42, $json['data']['items'][0]['groupId']);
            self::assertSame(1, $json['data']['items'][0]['responsibleId']);
        } else {
            self::assertSame(['id', 'title'], array_keys($json['data']['items'][0]));
        }
    }

    #[DataProvider('commands')]
    public function testOverdueChecksLifecycleAndDeadlineBoundaries(string $command): void
    {
        [$run] = $this->invoke($command, ['--overdue', '--timezone', 'UTC'], static function ($method, $version, array $params): ApiResponse {
            $now = ((array) $params['filter'])['<DEADLINE'];
            $before = (new \DateTimeImmutable($now))->modify('-1 second')->format(DATE_ATOM);
            $rows = array_map(static fn ($status): array => self::row((int) $status, $before, $status), ['2', '3', '4', '5', '6', '7']);
            $rows[] = self::row(8, $now);
            $rows[] = self::row(9, '');
            return new ApiResponse(['tasks' => $rows]);
        });
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([2, 3, 4, 6], array_column($run->json()['data']['items'], 'id'));
        self::assertSame('local', $run->json()['meta']['deadlineFilter']['overdueStatusApplied']);
    }

    #[DataProvider('commands')]
    public function testInvalidArgumentsAndStrictPolicyFailBeforeApi(string $command): void
    {
        $cases = [
            ['--deadline-window', 'day:0'], ['--deadline-window', ''],
            ['--deadline-window', 'today', '--deadline-window', 'tomorrow'],
            ['--overdue', '--timezone', '+06:00'], ['--overdue', '--timezone', 'invalid'], ['--timezone', 'UTC'],
            ['--overdue', '--order', 'responsibleId:asc'],
        ];
        if ($command === 'task:list') {
            $cases[] = ['--overdue', '--where', '{}'];
            $cases[] = ['--overdue', '--where-file', '/no-such-file'];
            $cases[] = ['--overdue', '--due-before', '2026-01-01T00:00:00Z'];
            $cases[] = ['--overdue', '--select', 'chat'];
        }

        foreach ($cases as $options) {
            [$run, $api, $resolver] = $this->invoke($command, $options, static fn () => throw new \LogicException('Unexpected call'));
            self::assertSame(2, $run->status, $run->stdout);
            self::assertSame('usage-error', $run->json()['error']['code']);
            self::assertSame([], $api->calls);
            self::assertSame(0, $resolver->reads);
        }

        foreach (['--overdue', '--deadline-window=tomorrow'] as $option) {
            [$run, $api, $resolver] = $this->invoke($command, [$option, '--api-policy', 'strict-rest3'], static fn () => throw new \LogicException('Unexpected call'));
            self::assertSame(4, $run->status, $run->stdout);
            self::assertSame('policy-denied', $run->json()['error']['code']);
            self::assertSame([], $api->calls);
            self::assertSame(0, $resolver->reads);
        }
    }

    #[DataProvider('commands')]
    public function testFutureOverdueIsCompleteEmptyWithoutApi(string $command): void
    {
        [$run, $api, $resolver] = $this->invoke($command, ['--deadline-window', 'tomorrow', '--overdue', '--role', 'responsible'], static fn () => throw new \LogicException('Unexpected call'));
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([], $run->json()['data']['items']);
        self::assertTrue($run->json()['meta']['complete']);
        self::assertSame(0, $run->json()['meta']['scanned']);
        self::assertSame([], $api->calls);
        self::assertSame(0, $resolver->reads);
    }

    #[DataProvider('commands')]
    public function testPaginationHasFrozenDatesAndBudgetIndependentOfOutputLimit(string $command): void
    {
        $firstFilter = null;
        $respond = static function ($method, $version, array $params) use (&$firstFilter): ApiResponse {
            $filter = (array) $params['filter'];
            $firstFilter ??= $filter;
            self::assertSame($firstFilter, $filter);
            $rows = array_map(static fn (int $id): array => self::row($id, $filter['>=DEADLINE']), range(1, 101));
            $start = $params['start'];
            return new ApiResponse(['tasks' => array_slice($rows, $start, 50)], $start < 100 ? $start + 50 : null);
        };
        [$run, $api] = $this->invoke($command, ['--deadline-window', 'today', '--limit', '2'], $respond);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([0, 50, 100], array_column(array_column($api->calls, 'parameters'), 'start'));
        self::assertSame(101, $run->json()['meta']['matched']);
        self::assertSame(2, $run->json()['meta']['returned']);
        self::assertTrue($run->json()['meta']['limitApplied']);
        $firstFilter = null;
        [$partial] = $this->invoke($command, ['--deadline-window', 'today', '--all', '--max-scan', '1'], $respond);
        self::assertSame(3, $partial->status, $partial->stdout);
        self::assertSame('scan-budget-exhausted', $partial->json()['meta']['reason']);
        self::assertFalse($partial->json()['meta']['complete']);
    }

    #[DataProvider('commands')]
    public function testMissingInvalidDatesAndUnknownLifecycleProducePartial(string $command): void
    {
        foreach (['broken-date', 'missing-date', 'unknown-status'] as $case) {
            [$run] = $this->invoke($command, ['--overdue'], static function () use ($case): ApiResponse {
                $row = self::row(1, '2020-01-01T00:00:00Z');
                if ($case === 'missing-date') {
                    unset($row['deadline']);
                } elseif ($case === 'broken-date') {
                    $row['deadline'] = 'invalid';
                } else {
                    $row['status'] = '999';
                }

                return new ApiResponse(['tasks' => [$row]]);
            });
            self::assertSame(3, $run->status, $run->stdout);
            self::assertSame('missing-predicate-field', $run->json()['meta']['reason']);
        }
    }

    #[DataProvider('commands')]
    public function testLaterPageFailureAndNoProgressRemainPartial(string $command): void
    {
        foreach (['failure', 'no-progress'] as $case) {
            [$run] = $this->invoke($command, ['--deadline-window', 'today'], static function ($method, $version, array $params) use ($case): ApiResponse {
                if ($case === 'failure' && $params['start'] > 0) {
                    throw new Failure('api-error', 'Page error');
                }

                return new ApiResponse(['tasks' => [self::row(1, ((array) $params['filter'])['>=DEADLINE'])]], 50);
            });
            self::assertSame(3, $run->status, $run->stdout);
            self::assertSame($case === 'failure' ? 'page-error' : 'pagination-no-progress', $run->json()['meta']['reason']);
        }
    }

    #[DataProvider('commands')]
    public function testHelpCompletionAndOutputFormats(string $command): void
    {
        $fakeApiTransport = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected call'));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport);
        $help = ConsoleHarness::run($app, [$command, '--help']);
        foreach (['--deadline-window', '--overdue', '--timezone'] as $flag) {
            self::assertStringContainsString($flag, $help->stdout);
        }

        $completion = ConsoleHarness::run($app, ['_complete', '--shell', 'bash', '--input', 'b24cli', '--input', $command, '--input=--deadline-window', '--input=t', '--current', '3']);
        self::assertSame(0, $completion->status);
        foreach (DeadlineWindowResolver::WINDOWS as $token) {
            self::assertStringContainsString($token, $completion->stdout);
        }

        self::assertSame([], $fakeApiTransport->calls);
        foreach (['--plain', '--no-ansi'] as $format) {
            [$run] = $this->invoke($command, ['--deadline-window', 'today'], static fn ($m, $v, array $p): ApiResponse => new ApiResponse(['tasks' => [self::row(1, ((array) $p['filter'])['>=DEADLINE'])]]), $format);
            self::assertSame(0, $run->status, $run->stdout);
            self::assertStringContainsString('Новый ёж[ договор', $run->stdout);
            self::assertStringNotContainsString("\033[", $run->stdout);
        }
    }

    public static function roleCases(): iterable
    {
        foreach (['task:list', 'task:find'] as $command) {
            foreach (['responsible', 'creator', 'accomplice', 'auditor'] as $role) {
                yield $command . ':' . $role => [$command, $role];
            }
        }
    }

    #[DataProvider('roleCases')]
    public function testTemporalCriteriaIntersectWithRoleProjectStatusAndLiteralTitle(string $command, string $role): void
    {
        [$run, $api] = $this->invoke($command, ['--deadline-window', 'today', '--group-id', '42', '--status', 'pending', '--role', $role], static function ($method, $version, array $params) use ($role, $command): ApiResponse {
            self::assertSame(1, $version);
            if ($method === 'tasks.task.get') {
                $field = $role === 'accomplice' ? 'accomplices' : 'auditors';
                return new ApiResponse(['task' => ['id' => (string) $params['taskId'], $field => $params['taskId'] === 2 ? [] : ['1']]]);
            }

            $date = ((array) $params['filter'])['>=DEADLINE'];
            $included = self::row(1, $date);
            $wrongActor = self::row(2, $date);
            $wrongActor['responsibleId'] = '2';
            $wrongActor['createdBy'] = '2';
            $wrongGroup = self::row(3, $date);
            $wrongGroup['groupId'] = '7';
            $wrongTitle = self::row(4, $date);
            $wrongTitle['title'] = 'other';
            return new ApiResponse(['tasks' => [$included, $wrongActor, $wrongGroup, ...($command === 'task:find' ? [$wrongTitle] : []), self::row(5, $date, '5')]]);
        });
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([1], array_column($run->json()['data']['items'], 'id'));
        self::assertSame(1, $run->json()['meta']['roleFilter']['userId']);
        self::assertSame('pending', $command === 'task:list' ? $run->json()['data']['items'][0]['status'] : 'pending');
        self::assertNotContains(3, array_column($api->calls, 'version'));
    }

    public function testTemporalIdScopeAndOrderMapping(): void
    {
        [$run] = $this->invoke('task:list', ['--deadline-window', 'today', '--id', '9', '--order', 'status:asc', '--select', 'creatorId'], static function ($method, $version, array $params): ApiResponse {
            self::assertSame([9], ((array) $params['filter'])['ID']);
            self::assertSame(['REAL_STATUS' => 'ASC', 'ID' => 'ASC'], (array) $params['order']);
            self::assertContains('CREATED_BY', $params['select']);
            $date = ((array) $params['filter'])['>=DEADLINE'];
            return new ApiResponse(['tasks' => [self::row(9, $date), self::row(10, $date)]]);
        });
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([9], array_column($run->json()['data']['items'], 'id'));
        self::assertSame(1, $run->json()['data']['items'][0]['creatorId']);
        self::assertSame('explicit-task-ids', $run->json()['meta']['scope']);
    }
}
