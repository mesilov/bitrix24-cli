<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat;

use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\CalendarInstant;

/**
 * Resolves --period yesterday or an exact --from/--to pair to [from, to).
 * Yesterday is local midnight to local midnight in the IANA timezone, so DST days
 * last 23 or 25 hours instead of a fixed 24.
 */
final readonly class MessagePeriodResolver
{
    public function __construct(private Clock $clock)
    {
    }

    public function resolve(?string $period, ?string $from, ?string $to, ?string $timezone): MessagePeriod
    {
        if ($timezone !== null && !in_array($timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)) {
            throw Failure::usage('Use an IANA timezone for --timezone, for example Europe/Moscow.');
        }

        if ($period !== null && ($from !== null || $to !== null)) {
            throw Failure::usage('Choose --period or the --from/--to pair.');
        }

        $now = $this->clock->now();
        if ($period !== null) {
            if ($period !== 'yesterday') {
                throw Failure::usage('Use --period yesterday or an exact --from/--to pair.');
            }

            if ($timezone === null) {
                throw Failure::usage('A calendar period needs --timezone, for example --timezone Europe/Moscow.');
            }

            $zone = new \DateTimeZone($timezone);
            $today = $now->setTimezone($zone)->setTime(0, 0);
            $yesterday = $today->modify('-1 day')->setTime(0, 0);
            return new MessagePeriod('yesterday', $yesterday, $today, $now, $timezone);
        }

        if ($from === null || $to === null) {
            throw Failure::usage('Pass --period yesterday or both --from and --to.');
        }

        $start = CalendarInstant::parse($from);
        $end = CalendarInstant::parse($to);
        if (!$start instanceof \DateTimeImmutable || !$end instanceof \DateTimeImmutable) {
            throw Failure::usage('Use ISO 8601 with an offset for --from/--to, for example 2026-11-11T00:00:00+03:00.');
        }

        if ($start >= $end) {
            throw Failure::usage('--from must be earlier than --to.');
        }

        if ($timezone !== null) {
            $zone = new \DateTimeZone($timezone);
            $start = $start->setTimezone($zone);
            $end = $end->setTimezone($zone);
        }

        return new MessagePeriod('exact', $start, $end, $now, $timezone);
    }
}
