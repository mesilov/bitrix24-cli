<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Task\WeekdayDuration;
use PHPUnit\Framework\TestCase;

final class WeekdayDurationTest extends TestCase
{
    public function testWeekendsBoundariesOffsetsAndLongRanges(): void
    {
        foreach ([
            ['2026-10-16T16:00:00+03:00', '2026-10-19T10:00:00+03:00', 64800],
            ['2026-10-17T00:00:00+03:00', '2026-10-19T00:00:00+03:00', 0],
            ['2026-10-16T23:00:00+03:00', '2026-10-16T22:00:00Z', 3600],
            ['2026-10-16T23:00:00-04:00', '2026-10-17T05:00:00Z', 3600],
            ['2026-10-19T00:00:00+03:00', '2026-10-19T01:00:00+03:00', 3600],
            ['2026-10-12T00:00:00Z', '2027-10-11T00:00:00Z', 52 * 5 * 86400],
            ['2026-10-16T23:59:59.500000+03:00', '2026-10-17T00:00:00.500000+03:00', 0.5],
        ] as [$start, $end, $expected]) {
            $from = new \DateTimeImmutable($start);
            self::assertEquals($expected, WeekdayDuration::seconds($from, new \DateTimeImmutable($end), $from->getTimezone()));
        }
    }

    public function testUnitsRoundIndependentlyAndPreserveNull(): void
    {
        self::assertSame(['seconds' => 49, 'hours' => 0.01, 'days' => 0.0], WeekdayDuration::units(48.621677));
        // Rounding seconds first would incorrectly make hours=0.01.
        self::assertSame(['seconds' => 18, 'hours' => 0.0, 'days' => 0.0], WeekdayDuration::units(17.6));
        self::assertSame(['seconds' => null, 'hours' => null, 'days' => null], WeekdayDuration::units(null));
    }
}
