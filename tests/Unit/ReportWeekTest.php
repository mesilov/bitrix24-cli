<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\{Clock, Failure};
use Bitrix24\CLI\Application\Task\ReportWeekResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReportWeekTest extends TestCase
{
    private function resolver(string $instant): ReportWeekResolver
    {
        return new ReportWeekResolver(new readonly class ($instant) implements Clock {
            public function __construct(private string $instant)
            {
            }

            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable($this->instant);
            }
        });
    }

    public static function weeks(): iterable
    {
        yield 'default previous' => ['2026-10-16T19:00:00+06:00', 'Asia/Bishkek', 'last-week', '2026-10-05T00:00:00+06:00', '2026-10-12T00:00:00+06:00'];
        yield 'date within week' => ['2026-10-16T19:00:00+06:00', 'Asia/Bishkek', '2026-10-14', '2026-10-12T00:00:00+06:00', '2026-10-19T00:00:00+06:00'];
        yield 'spring DST' => ['2026-04-01T12:00:00Z', 'Europe/Berlin', 'last-week', '2026-03-23T00:00:00+01:00', '2026-03-30T00:00:00+02:00'];
        yield 'autumn DST' => ['2026-10-28T12:00:00Z', 'Europe/Berlin', 'last-week', '2026-10-19T00:00:00+02:00', '2026-10-26T00:00:00+01:00'];
        yield 'year' => ['2027-01-01T12:00:00Z', 'UTC', 'this-week', '2026-12-28T00:00:00+00:00', '2027-01-04T00:00:00+00:00'];
    }

    #[DataProvider('weeks')]
    public function testCalendarAndHalfOpenBoundaries(string $now, string $timezone, string $week, string $from, string $to): void
    {
        $result = $this->resolver($now)->resolve($week, $timezone);
        self::assertSame($from, $result->from->format(DATE_ATOM));
        self::assertSame($to, $result->to->format(DATE_ATOM));
        self::assertTrue($result->contains($from));
        self::assertFalse($result->contains($to));
        self::assertFalse($result->contains($now));
        self::assertNull($result->contains('invalid'));
        self::assertNull($result->contains('2026-02-30T00:00:00Z'));
        self::assertNull($result->contains(null));
    }

    public function testCurrentWeekUsesSingleClockReadAndPreservesMicroseconds(): void
    {
        $clock = new class () implements Clock {
            public int $reads = 0;
            public function now(): \DateTimeImmutable
            {
                $this->reads++;
                return new \DateTimeImmutable('2026-10-16T12:00:00.123456Z');
            }
        };
        $week = (new ReportWeekResolver($clock))->resolve('this-week', 'UTC');
        self::assertSame(1, $clock->reads);
        self::assertSame('2026-10-16T12:00:00.123456+00:00', $week->metadata()['effectiveTo']);
        self::assertTrue($week->contains('2026-10-16T12:00:00.123455Z'));
        self::assertFalse($week->contains('2026-10-16T12:00:00.123456Z'));
    }

    public static function invalid(): iterable
    {
        yield ['2026-02-30', 'UTC'];
        yield ['2026-13-01', 'UTC'];
        yield ['next-week', 'UTC'];
        yield ['2027-01-01', 'UTC'];
        yield ['this-week', '+06:00'];
        yield ['', 'UTC'];
    }

    #[DataProvider('invalid')]
    public function testInvalidInput(string $week, string $timezone): void
    {
        $this->expectException(Failure::class);
        $this->resolver('2026-10-16T12:00:00Z')->resolve($week, $timezone);
    }
}
