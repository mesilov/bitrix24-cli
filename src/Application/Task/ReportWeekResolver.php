<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\{Clock, Failure};

final readonly class ReportWeekResolver
{
    public function __construct(private Clock $clock)
    {
    }

    public function resolve(string $week = 'last-week', ?string $timezone = null): ReportWeek
    {
        $timezone ??= getenv('TZ') ?: date_default_timezone_get();
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)) {
            throw Failure::usage('Use an IANA timezone for --timezone.');
        }

        $dateTimeZone = new \DateTimeZone($timezone);
        $now = $this->clock->now()->setTimezone($dateTimeZone);
        $day = $now->setTime(0, 0);
        if (!in_array($week, ['this-week', 'last-week'], true)) {
            $day = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $week) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $week, $dateTimeZone) : false;
            $errors = \DateTimeImmutable::getLastErrors();
            if ($day === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                throw Failure::usage('Use --week this-week, last-week or a valid YYYY-MM-DD date.');
            }
        }

        $from = $day->modify('-' . ((int) $day->format('N') - 1) . ' days');
        if ($week === 'last-week') {
            $from = $from->modify('-1 week');
        }

        if ($from > $now) {
            throw Failure::usage('The report week must not be in the future.');
        }

        return new ReportWeek($from, $from->modify('+1 week'), $now, $timezone);
    }
}
