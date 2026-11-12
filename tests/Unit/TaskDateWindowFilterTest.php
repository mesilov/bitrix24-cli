<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\DeadlineWindowResolver;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Issue #186: creation/change windows, server predicates on the temporal route, server status and local scan notices.
 */
final class TaskDateWindowFilterTest extends TestCase
{
    private function invoke(string $command, array $options, \Closure $respond, string $format = '--json'): array
    {
        $fakeApiTransport = new FakeApiTransport($respond);
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        $run = ConsoleHarness::run($app, [$format, $command, ...($command === 'task:find' ? ['--title', 'договор'] : []), ...$options]);
        return [$run, $fakeApiTransport, $spyConnectionResolver];
    }

    public static function commands(): iterable
    {
        yield 'list' => ['task:list'];
        yield 'find' => ['task:find'];
    }

    private static function row(int $id, string $created = '', string $changed = '', string $status = '2', string $deadline = ''): array
    {
        return ['id' => (string) $id, 'title' => 'Договор поставки', 'responsibleId' => '1', 'createdBy' => '1', 'groupId' => '42', 'status' => $status, 'deadline' => $deadline, 'createdDate' => $created, 'changedDate' => $changed];
    }

    private function clock(string $now): Clock
    {
        return new readonly class (new \DateTimeImmutable($now)) implements Clock {
            public function __construct(private \DateTimeImmutable $now)
            {
            }

            public function now(): \DateTimeImmutable
            {
                return $this->now;
            }
        };
    }

    public function testCreationAndChangeWindowsShareCalendarBoundsAndOneNow(): void
    {
        [$deadline, $windows] = (new DeadlineWindowResolver($this->clock('2026-11-12T10:15:30+06:00')))
            ->resolveAll('this-quarter', false, ['created' => 'this-quarter', 'changed' => 'this-week'], 'Asia/Bishkek');
        self::assertCount(2, $windows);
        [$created, $changed] = $windows;
        self::assertSame(['created', 'changed'], [$created->field, $changed->field]);
        self::assertSame(['2026-10-01T00:00:00+06:00', '2027-01-01T00:00:00+06:00'], [$created->from->format(DATE_ATOM), $created->to->format(DATE_ATOM)]);
        self::assertSame(['2026-11-09T00:00:00+06:00', '2026-11-16T00:00:00+06:00'], [$changed->from->format(DATE_ATOM), $changed->to->format(DATE_ATOM)]);
        self::assertEquals([$deadline?->from, $deadline?->to], [$created->from, $created->to]);
        self::assertSame($created->now, $changed->now);
        self::assertSame($deadline?->now, $created->now);
        self::assertSame(['>=CREATED_DATE' => '2026-10-01T00:00:00+06:00', '<CREATED_DATE' => '2027-01-01T00:00:00+06:00'], $created->filter());
        self::assertSame(['>=CHANGED_DATE' => '2026-11-09T00:00:00+06:00', '<CHANGED_DATE' => '2026-11-16T00:00:00+06:00'], $changed->filter());
        self::assertTrue($created->matches('2026-09-30T21:00:00+03:00'));
        self::assertFalse($created->matches('2026-09-30T20:59:59+03:00'));
        self::assertNull($created->matches(''));
        self::assertNull($created->matches(null));
        self::assertNull($created->matches('not a date'));
    }

    public function testInvalidWindowValueAndTimezoneWithoutTemporalFlagAreUsageErrors(): void
    {
        $deadlineWindowResolver = new DeadlineWindowResolver($this->clock('2026-11-12T10:00:00Z'));
        foreach ([[null, ['created' => 'last-year'], '--created-window'], [null, ['changed' => 'soon'], '--changed-window']] as [$deadline, $windows, $flag]) {
            try {
                $deadlineWindowResolver->resolveAll($deadline, false, $windows, null);
                self::fail('Expected usage error');
            } catch (Failure $failure) {
                self::assertSame(2, $failure->exitStatus);
                self::assertStringContainsString($flag, $failure->getMessage());
            }
        }

        try {
            $deadlineWindowResolver->resolveAll(null, false, ['created' => null], 'UTC');
            self::fail('Expected usage error');
        } catch (Failure $failure) {
            self::assertStringContainsString('--created-window or --changed-window', $failure->getMessage());
        }
    }

    #[DataProvider('commands')]
    public function testGroupTasksCreatedThisQuarter(string $command): void
    {
        [$run, $api] = $this->invoke($command, ['--group-id', '42', '--created-window', 'this-quarter', '--timezone', 'Asia/Bishkek'], static function ($method, $version, array $params): ApiResponse {
            self::assertSame(['tasks.task.list', 1], [$method, $version]);
            $filter = (array) $params['filter'];
            self::assertSame(['>=CREATED_DATE', '<CREATED_DATE', 'GROUP_ID'], array_keys($filter));
            self::assertSame(42, $filter['GROUP_ID']);
            self::assertStringEndsWith('+06:00', $filter['>=CREATED_DATE']);
            self::assertContains('CREATED_DATE', $params['select']);
            $before = (new \DateTimeImmutable($filter['>=CREATED_DATE']))->modify('-1 second')->format(DATE_ATOM);
            return new ApiResponse(['tasks' => [self::row(9, $filter['>=CREATED_DATE']), self::row(8, $before)]], null, 2);
        });
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        $json = $run->json();
        self::assertSame([9], array_column($json['data']['items'], 'id'));
        self::assertArrayNotHasKey('created', $json['data']['items'][0]);
        self::assertSame(['apiVersion' => '1.0', 'serverFilter' => ['groupId', 'created'], 'reason' => null], $json['meta']['listRoute']);
        self::assertSame(['window', 'timezone', 'now', 'fromInclusive', 'toExclusive', 'route', 'applied'], array_keys($json['meta']['createdFilter']));
        self::assertSame('this-quarter', $json['meta']['createdFilter']['window']);
        self::assertSame('Asia/Bishkek', $json['meta']['createdFilter']['timezone']);
        self::assertSame(['method' => 'tasks.task.list', 'apiVersion' => '1.0'], $json['meta']['createdFilter']['route']);
        self::assertArrayNotHasKey('deadlineFilter', $json['meta']);
        self::assertArrayNotHasKey('changedFilter', $json['meta']);
        self::assertCount(1, $api->calls);
        self::assertStringNotContainsString('Локальный обход', $run->stderr);
    }

    public function testSelectedCreationDateStaysInTheOutput(): void
    {
        [$run] = $this->invoke('task:list', ['--changed-window', 'today', '--timezone', 'UTC', '--select', 'id', '--select', 'changed'], static function ($method, $version, array $params): ApiResponse {
            $from = ((array) $params['filter'])['>=CHANGED_DATE'];
            return new ApiResponse(['tasks' => [['id' => '5', 'title' => 'Договор', 'changedDate' => $from]]], null, 1);
        });
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame(['id', 'title', 'changed'], array_keys($run->json()['data']['items'][0]));
    }

    #[DataProvider('commands')]
    public function testWindowsAndServerPredicatesCombineInOneRequest(string $command): void
    {
        [$run, $api] = $this->invoke($command, ['--role', 'responsible', '--status', 'in_progress', '--changed-window', 'this-week', '--deadline-window', 'this-month', '--timezone', 'UTC'], static function ($method, $version, array $params): ApiResponse {
            self::assertSame(['tasks.task.list', 1], [$method, $version]);
            $filter = (array) $params['filter'];
            self::assertSame(['>=DEADLINE', '<DEADLINE', '>=CHANGED_DATE', '<CHANGED_DATE', 'RESPONSIBLE_ID', 'REAL_STATUS'], array_keys($filter));
            self::assertSame([1, 3], [$filter['RESPONSIBLE_ID'], $filter['REAL_STATUS']]);
            $changed = $filter['>=CHANGED_DATE'];
            $deadline = $filter['>=DEADLINE'];
            return new ApiResponse(['tasks' => [
                self::row(9, changed: $changed, status: '3', deadline: $deadline),
                self::row(8, changed: $changed, status: '2', deadline: $deadline),
                self::row(7, changed: $filter['<CHANGED_DATE'], status: '3', deadline: $deadline),
            ]], null, 3);
        });
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        $json = $run->json();
        self::assertSame([9], array_column($json['data']['items'], 'id'));
        self::assertSame(['responsibleId', 'status', 'deadline', 'changed'], $json['meta']['listRoute']['serverFilter']);
        self::assertSame('this-week', $json['meta']['changedFilter']['window']);
        self::assertSame('this-month', $json['meta']['deadlineFilter']['window']);
        self::assertSame($json['meta']['changedFilter']['now'], $json['meta']['deadlineFilter']['now']);
        self::assertSame(['tasks.task.list'], array_values(array_unique(array_column($api->calls, 'method'))));
    }

    public function testMissingOrInvalidWindowDateIsPartial(): void
    {
        foreach (['', 'вчера'] as $value) {
            [$run] = $this->invoke('task:list', ['--created-window', 'today', '--timezone', 'UTC'], static fn ($method, $version, array $params): ApiResponse => new ApiResponse(['tasks' => [self::row(9, ((array) $params['filter'])['>=CREATED_DATE']), self::row(8, $value)]], null, 2));
            self::assertSame(3, $run->status, $run->stdout);
            self::assertSame('missing-predicate-field', $run->json()['meta']['reason']);
            self::assertSame([9], array_column($run->json()['data']['items'], 'id'));
        }
    }

    #[DataProvider('commands')]
    public function testInvalidCombinationsAndStrictPolicyFailBeforeApi(string $command): void
    {
        $usage = [
            ['--created-window', 'last-year'],
            ['--changed-window', 'today', '--changed-window', 'tomorrow'],
            ['--created-window', 'today', '--where', '{"groupId":42}'],
            ['--changed-window', 'today', '--due-before', '2026-11-12T00:00:00Z'],
            ['--created-window', 'today', '--select', 'chat'],
            ['--timezone', 'UTC'],
        ];
        foreach ($usage as $options) {
            [$run, $api, $resolver] = $this->invoke($command, $options, static fn () => throw new \LogicException('Unexpected call'));
            self::assertSame(2, $run->status, implode(' ', $options) . ' ' . $run->stdout);
            self::assertSame('usage-error', $run->json()['error']['code']);
            self::assertSame([], $api->calls);
            self::assertSame(0, $resolver->reads);
        }

        foreach (['--created-window=today', '--changed-window=this-month'] as $option) {
            [$run, $api, $resolver] = $this->invoke($command, [$option, '--api-policy', 'strict-rest3'], static fn () => throw new \LogicException('Unexpected call'));
            self::assertSame(4, $run->status, $run->stdout);
            self::assertSame('policy-denied', $run->json()['error']['code']);
            self::assertSame([], $api->calls);
            self::assertSame(0, $resolver->reads);
        }
    }

    public function testUndocumentedDeclinedStatusStaysLocalNextToServerPredicates(): void
    {
        [$run] = $this->invoke('task:list', ['--group-id', '42', '--status', 'declined'], static function ($method, $version, array $params): ApiResponse {
            self::assertSame(['tasks.task.list', 1], [$method, $version]);
            self::assertSame(['GROUP_ID' => 42], (array) $params['filter']);
            return new ApiResponse(['tasks' => [self::row(9, status: '7'), self::row(8, status: '5')]], null, 2);
        });
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame([9], array_column($run->json()['data']['items'], 'id'));
        self::assertSame(['groupId'], $run->json()['meta']['listRoute']['serverFilter']);
    }

    public function testServerStatusIsConfirmedLocally(): void
    {
        [$run] = $this->invoke('task:list', ['--status', 'completed'], static function ($method, $version, array $params): ApiResponse {
            self::assertSame(['REAL_STATUS' => 5], (array) $params['filter']);
            return new ApiResponse(['tasks' => [self::row(9, status: '5'), self::row(8, status: '3')]], null, 2);
        });
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame([9], array_column($run->json()['data']['items'], 'id'));
        self::assertSame(['apiVersion' => '1.0', 'serverFilter' => ['status'], 'reason' => null], $run->json()['meta']['listRoute']);
        self::assertSame('', $run->stderr);
    }

    private function rest3Pages(int $total): \Closure
    {
        return static function ($method, $version, array $params) use ($total): ApiResponse {
            self::assertSame(['tasks.task.list', 3], [$method, $version]);
            $offset = $params['pagination']['offset'];
            $count = max(0, min($params['pagination']['limit'], $total - $offset));
            $ids = $count === 0 ? [] : range($total - $offset, $total - $offset - $count + 1, -1);
            return new ApiResponse(['items' => array_map(static fn (int $id): array => ['id' => $id, 'title' => 'Договор', 'responsibleId' => 1, 'groupId' => 42, 'status' => $id % 2 === 0 ? 'declined' : 'pending', 'deadline' => null], $ids)]);
        };
    }

    public static function formats(): iterable
    {
        yield 'json' => ['--json'];
        yield 'plain' => ['--plain'];
        yield 'human' => ['--no-ansi'];
    }

    #[DataProvider('formats')]
    public function testLocalScanWarnsAndReportsProgressInStderrOnly(string $format): void
    {
        [$run] = $this->invoke('task:list', ['--status', 'declined', '--limit', '5', '--max-scan', '2500'], $this->rest3Pages(2200), $format);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        $lines = explode("\n", rtrim($run->stderr, "\n"));
        self::assertStringStartsWith('Локальный обход REST 3.0 (причина: no-server-predicate): просмотр до 2500 задач', $lines[0]);
        self::assertStringContainsString('--status', $lines[0]);
        self::assertContains('Локальный обход: просмотрено 1000 из 2500 задач, совпадений 500.', $lines);
        self::assertContains('Локальный обход: просмотрено 2000 из 2500 задач, совпадений 1000.', $lines);
        self::assertStringNotContainsString('Локальный обход', $run->stdout);
        if ($format === '--json') {
            self::assertSame(2200, $run->json()['meta']['scanned']);
            self::assertSame(1100, $run->json()['meta']['matched']);
        }
    }

    public function testLocalScanNoticeExplainsStrictPolicyAndUnsupportedFields(): void
    {
        [$strict] = $this->invoke('task:list', ['--group-id', '42', '--api-policy', 'strict-rest3'], $this->rest3Pages(3));
        self::assertStringContainsString('причина: strict-rest3', $strict->stderr);
        self::assertStringContainsString('недоступны при --api-policy strict-rest3', $strict->stderr);

        [$field] = $this->invoke('task:list', ['--group-id', '42', '--order', 'responsibleId:asc'], $this->rest3Pages(3));
        self::assertStringContainsString('причина: unsupported-field:responsibleId', $field->stderr);
        self::assertStringContainsString('уберите поле responsibleId', $field->stderr);
    }

    public function testNoNoticeForExplicitIdsOrQuietModes(): void
    {
        [$ids] = $this->invoke('task:list', ['--status', 'declined', '--id', '2'], $this->rest3Pages(2));
        self::assertSame(0, $ids->status, $ids->stdout . $ids->stderr);
        self::assertSame('', $ids->stderr);

        foreach (['--quiet', '--silent'] as $flag) {
            [$run] = $this->invoke('task:list', ['--status', 'declined', '--max-scan', '1500', $flag], $this->rest3Pages(1200));
            self::assertSame(0, $run->status);
            self::assertSame('', $run->stderr);
        }
    }
}
