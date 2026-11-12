<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Chat\MessagePeriodResolver;
use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Application\Failure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessagePeriodResolverTest extends TestCase
{
    private function resolver(string $now): MessagePeriodResolver
    {
        return new MessagePeriodResolver(new readonly class (new \DateTimeImmutable($now)) implements Clock {
            public function __construct(private \DateTimeImmutable $now)
            {
            }

            public function now(): \DateTimeImmutable
            {
                return $this->now;
            }
        });
    }

    public static function yesterdays(): iterable
    {
        // [now, timezone, from, to, seconds]
        yield 'moscow' => ['2026-11-12T10:00:00+03:00', 'Europe/Moscow', '2026-11-11T00:00:00+03:00', '2026-11-12T00:00:00+03:00', 86400];
        yield 'local date differs from utc' => ['2026-11-11T20:30:00Z', 'Asia/Omsk', '2026-11-11T00:00:00+06:00', '2026-11-12T00:00:00+06:00', 86400];
        yield 'spring forward is 23 hours' => ['2026-03-09T12:00:00-04:00', 'America/New_York', '2026-03-08T00:00:00-05:00', '2026-03-09T00:00:00-04:00', 82800];
        yield 'fall back is 25 hours' => ['2026-10-26T09:00:00+01:00', 'Europe/Berlin', '2026-10-25T00:00:00+02:00', '2026-10-26T00:00:00+01:00', 90000];
        yield 'year boundary' => ['2027-01-01T00:30:00+03:00', 'Europe/Moscow', '2026-12-31T00:00:00+03:00', '2027-01-01T00:00:00+03:00', 86400];
        yield 'month boundary' => ['2026-03-01T08:00:00+03:00', 'Europe/Moscow', '2026-02-28T00:00:00+03:00', '2026-03-01T00:00:00+03:00', 86400];
    }

    #[DataProvider('yesterdays')]
    public function testYesterdayIsLocalMidnightToLocalMidnight(string $now, string $timezone, string $from, string $to, int $seconds): void
    {
        $period = $this->resolver($now)->resolve('yesterday', null, null, $timezone);
        self::assertSame([$from, $to, $timezone, 'yesterday'], [$period->from->format(\DATE_ATOM), $period->to->format(\DATE_ATOM), $period->timezone, $period->kind]);
        self::assertSame($seconds, $period->to->getTimestamp() - $period->from->getTimestamp());
        self::assertTrue($period->contains($period->from));
        self::assertFalse($period->contains($period->to));
        self::assertSame($now, $period->now->format(\DATE_ATOM) === (new \DateTimeImmutable($now))->format(\DATE_ATOM) ? $now : null);
    }

    public function testExactPeriodKeepsAbsoluteBoundsAndReportsTimezone(): void
    {
        $period = $this->resolver('2026-11-12T10:00:00Z')->resolve(null, '2026-11-11T00:00:00+06:00', '2026-11-12T00:00:00+06:00', 'Europe/Moscow');
        self::assertSame(['2026-11-10T21:00:00+03:00', '2026-11-11T21:00:00+03:00', 'exact'], [$period->from->format(\DATE_ATOM), $period->to->format(\DATE_ATOM), $period->kind]);
        $untouched = $this->resolver('2026-11-12T10:00:00Z')->resolve(null, '2026-11-11T00:00:00Z', '2026-11-11T00:00:01Z', null);
        self::assertNull($untouched->timezone);
        self::assertSame('2026-11-11T00:00:00+00:00', $untouched->from->format(\DATE_ATOM));
    }

    public static function invalid(): iterable
    {
        yield 'nothing' => [null, null, null, null];
        yield 'period without timezone' => ['yesterday', null, null, null];
        yield 'unknown period' => ['today', null, null, 'UTC'];
        yield 'period and range' => ['yesterday', '2026-11-11T00:00:00Z', '2026-11-12T00:00:00Z', 'UTC'];
        yield 'only from' => [null, '2026-11-11T00:00:00Z', null, null];
        yield 'no offset' => [null, '2026-11-11T00:00:00', '2026-11-12T00:00:00', 'UTC'];
        yield 'invalid date' => [null, '2026-02-30T00:00:00Z', '2026-03-01T00:00:00Z', null];
        yield 'empty interval' => [null, '2026-11-11T00:00:00Z', '2026-11-11T03:00:00+03:00', null];
        yield 'reversed' => [null, '2026-11-12T00:00:00Z', '2026-11-11T00:00:00Z', null];
        yield 'bad timezone' => ['yesterday', null, null, 'Mars/Base'];
        yield 'offset as timezone' => ['yesterday', null, null, '+03:00'];
    }

    #[DataProvider('invalid')]
    public function testInvalidPeriodsAreUsageErrors(?string $period, ?string $from, ?string $to, ?string $timezone): void
    {
        try {
            $this->resolver('2026-11-12T10:00:00Z')->resolve($period, $from, $to, $timezone);
            self::fail('Expected a usage error.');
        } catch (Failure $failure) {
            self::assertSame('usage-error', $failure->errorCode);
        }
    }
}
