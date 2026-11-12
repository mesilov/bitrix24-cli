<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Application\Failure;

final readonly class DeadlineWindowResolver
{
    public const WINDOWS = ['today', 'tomorrow', 'yesterday', 'day-before-yesterday', 'this-week', 'this-month', 'this-quarter'];

    private const TEMPORAL_FLAGS = '--deadline-window, --overdue, --created-window or --changed-window';

    public function __construct(private ?Clock $clock = null)
    {
    }

    public function resolve(?string $window, bool $overdue, ?string $timezone): ?DeadlineWindow
    {
        return $this->resolveAll($window, $overdue, [], $timezone)[0];
    }

    /**
     * Resolves the deadline window and the creation/change windows with one timezone and one fixed now.
     *
     * @param array<string, ?string> $dateWindows CLI field (created|changed) => window mnemonic
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

        $timezone ??= getenv('TZ') ?: date_default_timezone_get();
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)) {
            throw Failure::usage('Use an IANA timezone for --timezone (for example Asia/Bishkek).');
        }

        $now = ($this->clock?->now() ?? new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone($timezone));
        $now = $now->setTime((int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'));

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
        $day = $now->setTime(0, 0);
        $from = match ($window) {
            'today' => $day, 'tomorrow' => $day->modify('+1 day'), 'yesterday' => $day->modify('-1 day'), 'day-before-yesterday' => $day->modify('-2 days'),
            'this-week' => $day->modify('-' . ((int) $day->format('N') - 1) . ' days'),
            'this-month' => $day->setDate((int) $day->format('Y'), (int) $day->format('n'), 1),
            'this-quarter' => $day->setDate((int) $day->format('Y'), 1 + 3 * intdiv((int) $day->format('n') - 1, 3), 1),
            default => throw new \InvalidArgumentException('Unknown window ' . $window . '.'),
        };
        $to = $from->modify(match ($window) {
            'this-week' => '+1 week', 'this-month' => '+1 month', 'this-quarter' => '+3 months', default => '+1 day',
        });

        return [$from, $to];
    }
}
