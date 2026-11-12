<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Application\Failure;

final readonly class DeadlineWindowResolver
{
    public const WINDOWS = TaskCalendar::WINDOWS;

    private const TEMPORAL_FLAGS = '--deadline-window, --overdue, --created-window, --changed-window or --closed-window';

    private TaskCalendar $calendar;

    public function __construct(?Clock $clock = null)
    {
        $this->calendar = new TaskCalendar($clock);
    }

    public function resolve(?string $window, bool $overdue, ?string $timezone): ?DeadlineWindow
    {
        return $this->resolveAll($window, $overdue, [], $timezone)[0];
    }

    /**
     * Resolves the deadline window and the creation/change windows with one timezone and one fixed now.
     *
     * @param array<string, ?string> $dateWindows CLI field (created|changed|closed) => window mnemonic
     *
     * @return array{0: ?DeadlineWindow, 1: list<DateWindow>}
     */
    public function resolveAll(?string $window, bool $overdue, array $dateWindows, ?string $timezone): array
    {
        $dateWindows = array_filter($dateWindows, static fn (?string $value): bool => $value !== null);
        if ($window === null && !$overdue && $dateWindows === []) {
            if ($timezone !== null) {
                throw Failure::usage('--timezone requires ' . self::TEMPORAL_FLAGS . '.');
            }

            return [null, []];
        }

        if ($window !== null && !in_array($window, self::WINDOWS, true)) {
            throw Failure::usage('Use --deadline-window ' . implode(', ', self::WINDOWS) . '.');
        }

        foreach ($dateWindows as $field => $value) {
            if (!array_key_exists($field, DateWindow::FIELDS)) {
                throw new \InvalidArgumentException('Unsupported date window field ' . $field . '.');
            }

            if (!in_array($value, self::WINDOWS, true)) {
                throw Failure::usage('Use --' . $field . '-window ' . implode(', ', self::WINDOWS) . '.');
            }
        }

        $timezone = TaskCalendar::timezone($timezone);
        $now = $this->calendar->now($timezone);

        $deadline = null;
        if ($window !== null || $overdue) {
            [$from, $to] = $window === null ? [null, null] : self::bounds($window, $now);
            if ($overdue && ($to === null || $now < $to)) {
                $to = $now;
            }

            $deadline = new DeadlineWindow($window, $overdue, $timezone, $now, $from, $to);
        }

        $windows = [];
        foreach ($dateWindows as $field => $value) {
            [$from, $to] = self::bounds($value, $now);
            $windows[] = new DateWindow($field, $value, $timezone, $now, $from, $to);
        }

        return [$deadline, $windows];
    }

    /**
     * Calendar bounds [from, to) of a window mnemonic in the timezone of $now: weeks start on Monday, quarters in January.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    public static function bounds(string $window, \DateTimeImmutable $now): array
    {
        return TaskCalendar::bounds($window, $now);
    }
}
