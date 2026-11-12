<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Application\Failure;

/**
 * Calendar shared by search windows and relative deadlines: one IANA timezone, one now per operation, weeks start on Monday.
 */
final readonly class TaskCalendar
{
    /** Window mnemonic => [unit, offset]; day/week/month/quarter are calendar periods, rolling covers seven whole days including today. */
    private const TABLE = [
        'today' => ['day', 0], 'tomorrow' => ['day', 1], 'yesterday' => ['day', -1], 'day-before-yesterday' => ['day', -2],
        'this-week' => ['week', 0], 'last-week' => ['week', -1], 'next-week' => ['week', 1],
        'this-month' => ['month', 0], 'last-month' => ['month', -1], 'next-month' => ['month', 1],
        'this-quarter' => ['quarter', 0], 'last-quarter' => ['quarter', -1], 'next-quarter' => ['quarter', 1],
        'next-7-days' => ['rolling', 0], 'last-7-days' => ['rolling', -6],
    ];

    public const WINDOWS = [
        'today', 'tomorrow', 'yesterday', 'day-before-yesterday',
        'this-week', 'this-month', 'this-quarter',
        'last-week', 'next-week', 'last-month', 'next-month', 'last-quarter', 'next-quarter',
        'next-7-days', 'last-7-days',
    ];

    public const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function __construct(private ?Clock $clock = null)
    {
    }

    /**
     * The explicit IANA timezone, else TZ, else the PHP default timezone.
     */
    public static function timezone(?string $timezone): string
    {
        $timezone ??= getenv('TZ') ?: date_default_timezone_get();
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)) {
            throw Failure::usage('Use an IANA timezone for --timezone (for example Asia/Bishkek).');
        }

        return $timezone;
    }

    /**
     * Current instant in the timezone, truncated to seconds like API datetimes.
     */
    public function now(string $timezone): \DateTimeImmutable
    {
        $now = ($this->clock?->now() ?? new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone($timezone));
        return $now->setTime((int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'));
    }

    /**
     * Calendar bounds [from, to) of a window mnemonic in the timezone of $now.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    public static function bounds(string $window, \DateTimeImmutable $now): array
    {
        [$unit, $offset] = self::TABLE[$window] ?? throw new \InvalidArgumentException('Unknown window ' . $window . '.');
        $day = $now->setTime(0, 0);
        $start = match ($unit) {
            'day', 'rolling' => $day,
            'week' => $day->modify('-' . ((int) $day->format('N') - 1) . ' days'),
            'month' => $day->setDate((int) $day->format('Y'), (int) $day->format('n'), 1),
            'quarter' => $day->setDate((int) $day->format('Y'), 1 + 3 * intdiv((int) $day->format('n') - 1, 3), 1),
        };
        $step = ['day' => 'day', 'rolling' => 'day', 'week' => 'week', 'month' => 'month', 'quarter' => 'month'][$unit];
        $size = ['day' => 1, 'rolling' => 7, 'week' => 1, 'month' => 1, 'quarter' => 3][$unit];
        $shift = $unit === 'quarter' ? 3 * $offset : $offset;
        // Calendar arithmetic on local midnights keeps DST and month lengths exact.
        $from = $shift === 0 ? $start : $start->modify(sprintf('%+d %s', $shift, $step));
        $to = $from->modify(sprintf('+%d %s', $size, $step));

        return [self::midnight($from), self::midnight($to)];
    }

    /**
     * Local midnight of a relative day token: today, tomorrow, day-after-tomorrow, monday…sunday (the nearest such day after today),
     * next-week-monday…next-week-sunday (that day of the following calendar week) or a YYYY-MM-DD calendar date.
     */
    public static function day(string $token, \DateTimeImmutable $now): \DateTimeImmutable
    {
        $today = $now->setTime(0, 0);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $token, $parts) === 1) {
            if (!checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                throw Failure::usage('The date ' . $token . ' does not exist.');
            }

            return self::midnight($today->setDate((int) $parts[1], (int) $parts[2], (int) $parts[3]));
        }

        $weekday = static fn (string $name): int => (int) array_search($name, self::WEEKDAYS, true) + 1;
        $current = (int) $today->format('N');
        $days = match (true) {
            $token === 'today' => 0,
            $token === 'tomorrow' => 1,
            $token === 'day-after-tomorrow' => 2,
            in_array($token, self::WEEKDAYS, true) => ($weekday($token) - $current + 6) % 7 + 1,
            str_starts_with($token, 'next-week-') && in_array(substr($token, 10), self::WEEKDAYS, true) => 7 - $current + $weekday(substr($token, 10)),
            default => throw Failure::usage('Use a day token: ' . implode(', ', self::dayTokens()) . ' or YYYY-MM-DD.'),
        };

        return self::midnight($days === 0 ? $today : $today->modify('+' . $days . ' days'));
    }

    /**
     * @return list<string>
     */
    public static function dayTokens(): array
    {
        return ['today', 'tomorrow', 'day-after-tomorrow', ...self::WEEKDAYS, ...array_map(static fn (string $day): string => 'next-week-' . $day, self::WEEKDAYS)];
    }

    /**
     * Wall-clock HH:MM on a local day; a time skipped or repeated by a DST transition is rejected instead of being shifted.
     */
    public static function at(\DateTimeImmutable $day, string $time): \DateTimeImmutable
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $parts) !== 1) {
            throw Failure::usage('Use HH:MM (00:00-23:59) for the deadline time.');
        }

        $zone = $day->getTimezone();
        $local = $day->format('Y-m-d') . ' ' . $time . ':00';
        $value = new \DateTimeImmutable($local, $zone);
        if ($value->format('Y-m-d H:i:s') !== $local) {
            throw Failure::usage(sprintf('%s does not exist in %s because of a DST transition; pass an exact ISO8601 deadline instead.', $local, $zone->getName()));
        }

        // A repeated wall-clock time has a second instant one offset change away (30 minutes to 2 hours in tzdata).
        foreach ([-7200, -3600, -1800, 1800, 3600, 7200] as $shift) {
            $other = $value->setTimestamp($value->getTimestamp() + $shift);
            if ($other->format('Y-m-d H:i:s') === $local) {
                throw Failure::usage(sprintf('%s occurs twice in %s because of a DST transition; pass an exact ISO8601 deadline with its offset instead.', $local, $zone->getName()));
            }
        }

        return $value;
    }

    /**
     * Re-anchors a date at local midnight so that the offset reflects that day, not the day the arithmetic started on.
     */
    private static function midnight(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date->format('Y-m-d') . ' 00:00:00', $date->getTimezone());
    }
}
