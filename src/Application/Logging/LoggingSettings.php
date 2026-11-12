<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Logging;

final class LoggingSettings
{
    public const DEFAULTS = [
        'enabled' => false,
        'level' => 'info',
        'directory' => null,
        'fileMask' => 'b24cli-{YYYY}{MM}{DD}.log',
        'retentionDays' => 7,
    ];

    public const LEVELS = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

    public const TOKENS = ['{YYYY}', '{MM}', '{DD}'];

    /**
     * Returns effective settings with status ok, disabled or invalid. Invalid settings never enable logging.
     *
     * @return array{settings: array<string, mixed>, status: string, reason: ?string}
     */
    public static function effective(mixed $logging, bool $present = true): array
    {
        if (!$present) {
            return ['settings' => self::DEFAULTS, 'status' => 'disabled', 'reason' => null];
        }

        $reason = self::problem($logging);
        if ($reason !== null) {
            return ['settings' => self::DEFAULTS, 'status' => 'invalid', 'reason' => $reason];
        }

        $settings = [...self::DEFAULTS, ...get_object_vars($logging)];
        return ['settings' => $settings, 'status' => $settings['enabled'] ? 'ok' : 'disabled', 'reason' => null];
    }

    /**
     * Validates a logging value decoded with JSON objects as stdClass, so {} and [] stay distinct.
     */
    public static function problem(mixed $logging): ?string
    {
        if (!$logging instanceof \stdClass) {
            return 'logging must be a JSON object.';
        }

        $values = get_object_vars($logging);
        $unknown = array_diff(array_keys($values), array_keys(self::DEFAULTS));
        if ($unknown !== []) {
            return 'Unknown logging setting: ' . implode(', ', array_map('strval', $unknown)) . '.';
        }

        foreach ($values as $name => $value) {
            $problem = match ($name) {
                'enabled' => is_bool($value) ? null : 'logging.enabled must be true or false.',
                'level' => in_array($value, self::LEVELS, true) ? null : 'logging.level must be one of ' . implode(', ', self::LEVELS) . '.',
                'directory' => $value === null || (is_string($value) && $value !== '') ? null : 'logging.directory must be a nonempty string or null.',
                'fileMask' => self::maskProblem($value),
                default => is_int($value) && $value > 0 ? null : 'logging.retentionDays must be a positive JSON integer.',
            };
            if ($problem !== null) {
                return $problem;
            }
        }

        return null;
    }

    public static function maskProblem(mixed $mask): ?string
    {
        if (!is_string($mask) || $mask === '' || strlen($mask) > 255) {
            return 'logging.fileMask must be a nonempty file name up to 255 bytes.';
        }

        if (str_contains($mask, '..') || preg_match('~[/\\\\<>:"|?*\x00-\x1F\x7F]~', $mask) === 1) {
            return 'logging.fileMask must be a plain file name without path separators, "..", control or reserved characters.';
        }

        foreach (self::TOKENS as $token) {
            if (substr_count($mask, $token) !== 1) {
                return 'logging.fileMask must contain each of {YYYY}, {MM} and {DD} exactly once.';
            }
        }

        return null;
    }

    public static function fileName(string $mask, \DateTimeInterface $date): string
    {
        return str_replace(self::TOKENS, [$date->format('Y'), $date->format('m'), $date->format('d')], $mask);
    }

    /**
     * Regex with named groups year, month and day that matches only names produced by the mask.
     */
    public static function pattern(string $mask): string
    {
        $parts = preg_split('/(\{YYYY\}|\{MM\}|\{DD\})/', $mask, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $regex = '';
        foreach ($parts as $part) {
            $regex .= match ($part) {
                '{YYYY}' => '(?<year>\d{4})',
                '{MM}' => '(?<month>\d{2})',
                '{DD}' => '(?<day>\d{2})',
                default => preg_quote($part, '~'),
            };
        }

        return '~^' . $regex . '$~D';
    }

    public static function timezone(): \DateTimeZone
    {
        $name = getenv('TZ') ?: date_default_timezone_get();
        try {
            return new \DateTimeZone($name);
        } catch (\Throwable) {
            return new \DateTimeZone(date_default_timezone_get());
        }
    }
}
