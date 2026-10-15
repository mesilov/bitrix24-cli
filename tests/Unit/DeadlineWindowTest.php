<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Application\Task\DeadlineWindowResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeadlineWindowTest extends TestCase
{
    private function resolver(string $instant): DeadlineWindowResolver
    {
        return new DeadlineWindowResolver(new class ($instant) implements Clock {
            public int $reads = 0;

            public function __construct(private readonly string $instant)
            {
            }

            public function now(): \DateTimeImmutable
            {
                $this->reads++;
                return new \DateTimeImmutable($this->instant);
            }
        });
    }

    public static function windows(): iterable
    {
        yield 'today' => ['today', '2026-10-13', '2026-10-14'];
        yield 'tomorrow' => ['tomorrow', '2026-10-14', '2026-10-15'];
        yield 'yesterday' => ['yesterday', '2026-10-12', '2026-10-13'];
        yield 'day-before-yesterday' => ['day-before-yesterday', '2026-10-11', '2026-10-12'];
        yield 'this-week' => ['this-week', '2026-10-12', '2026-10-19'];
        yield 'this-month' => ['this-month', '2026-10-01', '2026-11-01'];
        yield 'this-quarter' => ['this-quarter', '2026-10-01', '2027-01-01'];
    }

    #[DataProvider('windows')]
    public function testCanonicalWindowsAndHalfOpenBoundaries(string $token, string $from, string $to): void
    {
        $window = $this->resolver('2026-10-13T12:34:56+06:00')->resolve($token, false, 'Asia/Bishkek');
        self::assertSame(['>=DEADLINE' => $from . 'T00:00:00+06:00', '<DEADLINE' => $to . 'T00:00:00+06:00'], $window->filter());
        self::assertTrue($window->matches($from . 'T00:00:00+06:00'));
        self::assertFalse($window->matches($to . 'T00:00:00+06:00'));
        self::assertFalse($window->matches(null));
        self::assertNull($window->matches('invalid'));
        self::assertNull($window->matches('2026-10-13T12:00:00'));
    }

    public static function calendarEdges(): iterable
    {
        yield 'spring DST' => ['2026-03-29T12:00:00+02:00', 'Europe/Berlin', 'today', '2026-03-29T00:00:00+01:00', '2026-03-30T00:00:00+02:00'];
        yield 'autumn DST' => ['2026-10-25T12:00:00+01:00', 'Europe/Berlin', 'today', '2026-10-25T00:00:00+02:00', '2026-10-26T00:00:00+01:00'];
        yield 'leap month' => ['2024-02-29T12:00:00Z', 'UTC', 'this-month', '2024-02-01T00:00:00+00:00', '2024-03-01T00:00:00+00:00'];
        yield 'year boundary' => ['2026-12-31T12:00:00Z', 'UTC', 'tomorrow', '2027-01-01T00:00:00+00:00', '2027-01-02T00:00:00+00:00'];
        yield 'Q1' => ['2026-03-31T12:00:00Z', 'UTC', 'this-quarter', '2026-01-01T00:00:00+00:00', '2026-04-01T00:00:00+00:00'];
        yield 'Q2' => ['2026-04-01T12:00:00Z', 'UTC', 'this-quarter', '2026-04-01T00:00:00+00:00', '2026-07-01T00:00:00+00:00'];
        yield 'Q3' => ['2026-08-31T12:00:00Z', 'UTC', 'this-quarter', '2026-07-01T00:00:00+00:00', '2026-10-01T00:00:00+00:00'];
        yield 'Sunday' => ['2026-10-18T12:00:00Z', 'UTC', 'this-week', '2026-10-12T00:00:00+00:00', '2026-10-19T00:00:00+00:00'];
    }

    #[DataProvider('calendarEdges')]
    public function testCalendarEdges(string $now, string $timezone, string $token, string $from, string $to): void
    {
        self::assertSame(['>=DEADLINE' => $from, '<DEADLINE' => $to], $this->resolver($now)->resolve($token, false, $timezone)->filter());
    }

    public function testTimezoneDeterminesDateAndOverdueUsesOneNow(): void
    {
        $resolver = $this->resolver('2026-10-13T23:30:00Z');
        self::assertSame('2026-10-14T00:00:00+06:00', $resolver->resolve('today', false, 'Asia/Bishkek')->metadata()['fromInclusive']);
        self::assertSame('2026-10-13T00:00:00+00:00', $resolver->resolve('today', false, 'UTC')->metadata()['fromInclusive']);
        $window = $resolver->resolve('this-week', true, 'UTC');
        self::assertSame('2026-10-13T23:30:00+00:00', $window->metadata()['toExclusive']);
        self::assertFalse($window->matches($window->metadata()['now']));
        self::assertTrue($window->matches('2026-10-13T23:29:59Z'));
        self::assertTrue($resolver->resolve('tomorrow', true, 'UTC')->empty());
        self::assertSame(['<DEADLINE' => '2026-10-13T23:30:00+00:00'], $resolver->resolve(null, true, 'UTC')->filter());
        self::assertSame('2026-10-13T00:00:00+00:00', $resolver->resolve('yesterday', true, 'UTC')->metadata()['toExclusive']);
    }
}
