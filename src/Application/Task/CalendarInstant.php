<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

/** Strict offset-bearing instants; never normalize invalid dates or assume a local timezone. */
final class CalendarInstant
{
    public static function parse(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-](?:0\d|1[0-4]):[0-5]\d)$/D', $value)) {
            return null;
        }

        try {
            $date = new \DateTimeImmutable($value);
            $errors = \DateTimeImmutable::getLastErrors();
            return $errors !== false && ($errors['warning_count'] || $errors['error_count']) ? null : $date;
        } catch (\Exception) {
            return null;
        }
    }

    public static function seconds(\DateTimeImmutable $date): float
    {
        return (float) $date->format('U.u');
    }

    public static function elapsed(\DateTimeImmutable $from, \DateTimeImmutable $to): int|float
    {
        $value = round(self::seconds($to) - self::seconds($from), 6);
        return floor($value) === $value ? (int) $value : $value;
    }
}
