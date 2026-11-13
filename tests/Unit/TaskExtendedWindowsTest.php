<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Application\Task\DateWindow;
use Bitrix24\CLI\Application\Task\DeadlineWindowResolver;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Issue #141: previous/next calendar periods, rolling seven-day windows and the actual completion window.
 */
final class TaskExtendedWindowsTest extends TestCase
{
    private function resolver(string $now): DeadlineWindowResolver
    {
        return new DeadlineWindowResolver(new readonly class (new \DateTimeImmutable($now)) implements Clock {
            public function __construct(private \DateTimeImmutable $now)
            {
            }

            public function now(): \DateTimeImmutable
            {
                return $this->now;
            }
        });
    }

    private function invoke(string $command, array $options, \Closure $respond): array
    {
        $fakeApiTransport = new FakeApiTransport($respond);
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        $run = ConsoleHarness::run($app, ['--json', $command, ...($command === 'task:find' ? ['--title', 'договор'] : []), ...$options]);
        return [$run, $fakeApiTransport, $spyConnectionResolver];
    }

    public static function commands(): iterable
    {
        yield 'list' => ['task:list'];
        yield 'find' => ['task:find'];
    }

    private static function row(int $id, ?string $closed, string $status = '5'): array
    {
        return ['id' => (string) $id, 'title' => 'Договор поставки', 'responsibleId' => '1', 'createdBy' => '1', 'groupId' => '42', 'status' => $status, 'closedDate' => $closed];
    }

    public static function periods(): iterable
    {
        yield 'last-week' => ['2026-11-13T15:00:00+06:00', 'last-week', '2026-11-02', '2026-11-09'];
        yield 'next-week' => ['2026-11-13T15:00:00+06:00', 'next-week', '2026-11-16', '2026-11-23'];
        yield 'last-month' => ['2026-11-13T15:00:00+06:00', 'last-month', '2026-10-01', '2026-11-01'];
        yield 'next-month' => ['2026-11-13T15:00:00+06:00', 'next-month', '2026-12-01', '2027-01-01'];
        yield 'last-quarter' => ['2026-11-13T15:00:00+06:00', 'last-quarter', '2026-07-01', '2026-10-01'];
        yield 'next-quarter' => ['2026-11-13T15:00:00+06:00', 'next-quarter', '2027-01-01', '2027-04-01'];
        yield 'next-7-days' => ['2026-11-13T15:00:00+06:00', 'next-7-days', '2026-11-13', '2026-11-20'];
        yield 'last-7-days' => ['2026-11-13T15:00:00+06:00', 'last-7-days', '2026-11-07', '2026-11-14'];
        yield 'year boundary last-month' => ['2027-01-05T12:00:00+06:00', 'last-month', '2026-12-01', '2027-01-01'];
        yield 'year boundary last-quarter' => ['2027-01-05T12:00:00+06:00', 'last-quarter', '2026-10-01', '2027-01-01'];
        yield 'year boundary last-week' => ['2027-01-05T12:00:00+06:00', 'last-week', '2026-12-28', '2027-01-04'];
        yield 'next-month from January 31' => ['2027-01-31T12:00:00+06:00', 'next-month', '2027-02-01', '2027-03-01'];
        yield 'leap February last-7-days' => ['2028-03-02T12:00:00+06:00', 'last-7-days', '2028-02-25', '2028-03-03'];
    }

    #[DataProvider('periods')]
    public function testPreviousNextAndRollingWindows(string $now, string $token, string $from, string $to): void
    {
        $window = $this->resolver($now)->resolve($token, false, 'Asia/Bishkek');
        self::assertSame(['>=DEADLINE' => $from . 'T00:00:00+06:00', '<DEADLINE' => $to . 'T00:00:00+06:00'], $window?->filter());
    }

    public function testRollingWindowKeepsSevenCalendarDaysAcrossDst(): void
    {
        $window = $this->resolver('2026-10-22T10:00:00+02:00')->resolve('next-7-days', false, 'Europe/Berlin');
        self::assertSame(['>=DEADLINE' => '2026-10-22T00:00:00+02:00', '<DEADLINE' => '2026-10-29T00:00:00+01:00'], $window?->filter());
        $hours = ($window->to->getTimestamp() - $window->from->getTimestamp()) / 3600;
        self::assertSame(169, (int) $hours);
    }

    public function testRollingWindowIncludesEarlierTodayAndOverdueCutsAtNow(): void
    {
        $resolver = $this->resolver('2026-11-13T15:00:00+06:00');
        $window = $resolver->resolve('next-7-days', false, 'Asia/Bishkek');
        self::assertTrue($window?->matches('2026-11-13T09:00:00+06:00'));
        $overdue = $resolver->resolve('next-7-days', true, 'Asia/Bishkek');
        self::assertSame(['>=DEADLINE' => '2026-11-13T00:00:00+06:00', '<DEADLINE' => '2026-11-13T15:00:00+06:00'], $overdue?->filter());
    }

    public function testEveryWindowWorksForEveryDateField(): void
    {
        foreach (DeadlineWindowResolver::WINDOWS as $token) {
            [$deadline, $windows] = $this->resolver('2026-11-13T15:00:00+06:00')->resolveAll($token, false, ['created' => $token, 'changed' => $token, 'closed' => $token], 'Asia/Bishkek');
            self::assertSame(['created', 'changed', 'closed'], array_map(static fn (DateWindow $window): string => $window->field, $windows));
            foreach ($windows as $window) {
                self::assertEquals([$deadline?->from, $deadline?->to], [$window->from, $window->to], $token);
            }
        }
    }

    public function testClosedWindowTreatsEmptyCompletionAsMismatch(): void
    {
        [, [$closed]] = $this->resolver('2026-11-13T15:00:00+06:00')->resolveAll(null, false, ['closed' => 'this-week'], 'Asia/Bishkek');
        self::assertSame(['>=CLOSED_DATE' => '2026-11-09T00:00:00+06:00', '<CLOSED_DATE' => '2026-11-16T00:00:00+06:00'], $closed->filter());
        self::assertFalse($closed->matches(null));
        self::assertFalse($closed->matches(''));
        self::assertNull($closed->matches('вчера'));
        self::assertTrue($closed->matches('2026-11-10T12:00:00+06:00'));
        self::assertFalse($closed->matches('2026-11-16T00:00:00+06:00'));
    }

    #[DataProvider('commands')]
    public function testTasksClosedThisWeekUseClosedDateOnly(string $command): void
    {
        [$run, $api] = $this->invoke($command, ['--group-id', '42', '--closed-window', 'this-week', '--timezone', 'Asia/Bishkek'], static function ($method, $version, array $params): ApiResponse {
            self::assertSame(['tasks.task.list', 1], [$method, $version]);
            $filter = (array) $params['filter'];
            self::assertSame(['>=CLOSED_DATE', '<CLOSED_DATE', 'GROUP_ID'], array_keys($filter));
            self::assertStringEndsWith('+06:00', $filter['>=CLOSED_DATE']);
            self::assertContains('CLOSED_DATE', $params['select']);
            $inside = $filter['>=CLOSED_DATE'];
            return new ApiResponse(['tasks' => [
                self::row(9, $inside),
                self::row(8, null, '3'),
                self::row(7, '', '2'),
                self::row(6, $inside, '3'),
                self::row(5, $filter['<CLOSED_DATE']),
            ]], null, 5);
        });
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        $json = $run->json();
        self::assertSame([9, 6], array_column($json['data']['items'], 'id'));
        self::assertTrue($json['meta']['complete']);
        self::assertSame(['groupId', 'closed'], $json['meta']['listRoute']['serverFilter']);
        self::assertSame(['window', 'timezone', 'now', 'fromInclusive', 'toExclusive', 'route', 'applied'], array_keys($json['meta']['closedFilter']));
        self::assertSame('this-week', $json['meta']['closedFilter']['window']);
        self::assertArrayNotHasKey('deadlineFilter', $json['meta']);
        self::assertCount(1, $api->calls);
    }

    public function testReopenedTaskIsExcludedOnlyWithExplicitCompletedStatus(): void
    {
        [$run] = $this->invoke('task:list', ['--closed-window', 'last-week', '--status', 'completed', '--timezone', 'UTC'], static function ($method, $version, array $params): ApiResponse {
            $filter = (array) $params['filter'];
            self::assertSame(['>=CLOSED_DATE', '<CLOSED_DATE', 'REAL_STATUS'], array_keys($filter));
            self::assertSame(5, $filter['REAL_STATUS']);
            return new ApiResponse(['tasks' => [self::row(9, $filter['>=CLOSED_DATE']), self::row(8, $filter['>=CLOSED_DATE'], '3')]], null, 2);
        });
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame([9], array_column($run->json()['data']['items'], 'id'));
    }

    public function testUnreadableCompletionDateIsPartial(): void
    {
        [$run] = $this->invoke('task:list', ['--closed-window', 'today', '--timezone', 'UTC'], static fn ($method, $version, array $params): ApiResponse => new ApiResponse(['tasks' => [self::row(9, ((array) $params['filter'])['>=CLOSED_DATE']), self::row(8, 'вчера')]], null, 2));
        self::assertSame(3, $run->status, $run->stdout);
        self::assertSame('missing-predicate-field', $run->json()['meta']['reason']);
        self::assertSame([9], array_column($run->json()['data']['items'], 'id'));
    }

    #[DataProvider('commands')]
    public function testInvalidClosedWindowAndStrictPolicyFailBeforeApi(string $command): void
    {
        foreach ([
            ['--closed-window', 'last-year'],
            ['--closed-window', 'today', '--closed-window', 'yesterday'],
            ['--closed-window', 'today', '--where', '{"groupId":42}'],
            ['--closed-window', 'today', '--due-before', '2026-11-12T00:00:00Z'],
            ['--deadline-window', 'next-fortnight'],
        ] as $options) {
            [$run, $api, $resolver] = $this->invoke($command, $options, static fn () => throw new \LogicException('Unexpected call'));
            self::assertSame(2, $run->status, implode(' ', $options) . ' ' . $run->stdout);
            self::assertSame('usage-error', $run->json()['error']['code']);
            self::assertSame([], $api->calls);
            self::assertSame(0, $resolver->reads);
        }

        [$run, $api, $resolver] = $this->invoke($command, ['--closed-window=next-7-days', '--api-policy', 'strict-rest3'], static fn () => throw new \LogicException('Unexpected call'));
        self::assertSame(4, $run->status, $run->stdout);
        self::assertSame('policy-denied', $run->json()['error']['code']);
        self::assertSame([], $api->calls);
        self::assertSame(0, $resolver->reads);
    }

    #[DataProvider('commands')]
    public function testHelpAndCompletionListAllWindows(string $command): void
    {
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), new FakeApiTransport(static fn () => throw new \LogicException('Unexpected call')), new SpyConnectionResolver());
        $help = ConsoleHarness::run($app, [$command, '--help']);
        self::assertStringContainsString('--closed-window', $help->stdout);
        self::assertStringContainsString('next-7-days', $help->stdout);
        foreach (['--deadline-window', '--closed-window'] as $flag) {
            $completion = ConsoleHarness::run($app, ['_complete', '--shell', 'bash', '--input', 'b24cli', '--input', $command, '--input=' . $flag, '--input=t', '--current', '3']);
            self::assertSame(0, $completion->status);
            foreach (DeadlineWindowResolver::WINDOWS as $token) {
                self::assertStringContainsString($token, $completion->stdout, $flag);
            }
        }
    }
}
