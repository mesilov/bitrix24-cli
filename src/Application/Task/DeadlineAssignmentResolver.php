<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Application\Failure;

/**
 * Turns a relative day token plus an explicit HH:MM into an exact future deadline; no default hour is ever assumed.
 */
final readonly class DeadlineAssignmentResolver
{
    private TaskCalendar $calendar;

    public function __construct(?Clock $clock = null)
    {
        $this->calendar = new TaskCalendar($clock);
    }

    /**
     * @param string $dayFlag  console name of the day option, for error messages
     * @param string $timeFlag console name of the time option, for error messages
     */
    public function resolve(?string $day, ?string $time, ?string $timezone, string $dayFlag, string $timeFlag): ?DeadlineResolution
    {
        if ($day === null && $time === null) {
            if ($timezone !== null) {
                throw Failure::usage('--timezone requires ' . $dayFlag . ' and ' . $timeFlag . '.');
            }

            return null;
        }

        if ($day === null) {
            throw Failure::usage($timeFlag . ' requires ' . $dayFlag . '.');
        }

        if ($time === null) {
            throw Failure::usage($dayFlag . ' requires an explicit ' . $timeFlag . ' HH:MM; there is no default hour.');
        }

        $timezone = TaskCalendar::timezone($timezone);
        $now = $this->calendar->now($timezone);
        $deadline = TaskCalendar::at(TaskCalendar::day($day, $now), $time);
        if ($deadline <= $now) {
            throw Failure::usage(sprintf('The relative deadline %s is not after now (%s); pass an exact ISO8601 deadline for a past date.', $deadline->format(DATE_ATOM), $now->format(DATE_ATOM)));
        }

        return new DeadlineResolution($day, $time, $timezone, $now, $deadline);
    }
}
