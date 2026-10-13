<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Application\Failure;

final readonly class DeadlineWindowResolver
{
    public const WINDOWS = ['today', 'tomorrow', 'yesterday', 'day-before-yesterday', 'this-week', 'this-month', 'this-quarter'];

    public function __construct(private ?Clock $clock = null)
    {
    }

    public function resolve(?string $window, bool $overdue, ?string $timezone): ?DeadlineWindow
    {
        if ($window === null && !$overdue) {
            if ($timezone !== null) {
                throw Failure::usage('--timezone requires --deadline-window or --overdue.');
            }

            return null;
        }

        if ($window !== null && !in_array($window, self::WINDOWS, true)) {
            throw Failure::usage('Use --deadline-window ' . implode(', ', self::WINDOWS) . '.');
        }

        $timezone ??= getenv('TZ') ?: date_default_timezone_get();
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)) {
            throw Failure::usage('Use an IANA timezone for --timezone (for example Asia/Bishkek).');
        }

        $now = ($this->clock?->now() ?? new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone($timezone));
        $now = $now->setTime((int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'));

        $day = $now->setTime(0, 0);
        $from = match ($window) {
            'today' => $day, 'tomorrow' => $day->modify('+1 day'), 'yesterday' => $day->modify('-1 day'), 'day-before-yesterday' => $day->modify('-2 days'),
            'this-week' => $day->modify('-' . ((int) $day->format('N') - 1) . ' days'),
            'this-month' => $day->setDate((int) $day->format('Y'), (int) $day->format('n'), 1),
            'this-quarter' => $day->setDate((int) $day->format('Y'), 1 + 3 * intdiv((int) $day->format('n') - 1, 3), 1),
            default => null,
        };
        $to = $from?->modify(match ($window) {
            'this-week' => '+1 week', 'this-month' => '+1 month', 'this-quarter' => '+3 months', default => '+1 day',
        });
        if ($overdue && ($to === null || $now < $to)) {
            $to = $now;
        }

        return new DeadlineWindow($window, $overdue, $timezone, $now, $from, $to);
    }
}
