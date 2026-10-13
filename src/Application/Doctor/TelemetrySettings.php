<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Doctor;

use Bitrix24\CLI\Application\Failure;

final class TelemetrySettings
{
    public const DEFAULTS = [
        'collectProblemTrajectories' => true,
        'maxProblemTrajectoryFiles' => 100,
        'lastProblemTrajectoryProcessingAt' => null,
        'lastProblemTrajectoryAnalysisOfferedAt' => null,
    ];

    public static function effective(array $settings): array
    {
        self::validate($settings);
        return [...self::DEFAULTS, ...$settings];
    }

    public static function validate(array $settings): void
    {
        if (array_diff(array_keys($settings), array_keys(self::DEFAULTS)) !== []) {
            throw new Failure('configuration-error', 'Unknown telemetry setting.');
        }

        foreach ($settings as $name => $value) {
            $valid = match ($name) {
                'collectProblemTrajectories' => is_bool($value),
                'maxProblemTrajectoryFiles' => is_int($value) && $value > 0,
                default => $value === null || self::timestamp($value),
            };
            if (!$valid) {
                throw new Failure('configuration-error', 'Invalid telemetry setting type or UTC timestamp.');
            }
        }
    }

    public static function timestamp(mixed $value): bool
    {
        if (!is_string($value) || str_starts_with($value, '0000-') || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $value) !== 1) {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new \DateTimeZone('UTC'));
        return $date !== false && $date->format('Y-m-d\TH:i:s\Z') === $value;
    }
}
