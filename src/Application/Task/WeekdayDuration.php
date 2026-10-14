<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

/** Counts all weekday hours in the fixed offset supplied by the server. */
final class WeekdayDuration
{
    public static function seconds(\DateTimeImmutable $from, \DateTimeImmutable $to, \DateTimeZone $serverOffset): int|float
    {
        $start = $from->setTimezone($serverOffset);
        $end = $to->setTimezone($serverOffset);
        if ($end <= $start) {
            return 0;
        }

        $boundary = $start->modify('tomorrow')->setTime(0, 0);
        if ($end <= $boundary) {
            return (int) $start->format('N') < 6 ? CalendarInstant::elapsed($start, $end) : 0;
        }

        $seconds = (int) $start->format('N') < 6 ? CalendarInstant::elapsed($start, $boundary) : 0;
        $lastMidnight = $end->setTime(0, 0);
        $days = intdiv($lastMidnight->getTimestamp() - $boundary->getTimestamp(), 86400);
        $weeks = intdiv($days, 7);
        $seconds += $weeks * 5 * 86400;
        $cursor = $boundary->modify('+' . ($weeks * 7) . ' days');
        for ($remaining = $days % 7; $remaining > 0; --$remaining) {
            if ((int) $cursor->format('N') < 6) {
                $seconds += 86400;
            }

            $cursor = $cursor->modify('+1 day');
        }

        if ((int) $end->format('N') < 6) {
            $seconds += CalendarInstant::elapsed($lastMidnight, $end);
        }

        return round($seconds, 6);
    }

    public static function units(int|float|null $seconds): array
    {
        return [
            'seconds' => $seconds === null ? null : (int) round($seconds),
            'hours' => $seconds === null ? null : round($seconds / 3600, 2),
            'days' => $seconds === null ? null : round($seconds / 86400, 2),
        ];
    }
}
