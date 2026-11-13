<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Connection;

use Bitrix24\CLI\Application\Doctor\TelemetrySettings;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Logging\LoggingSettings;

/**
 * Closed list of non-secret config.json settings addressed by config:* commands.
 * A key is the exact JSON path; profile keys live in profiles.<alias>, root keys start with logging. or telemetry.
 */
final class ConfigKeys
{
    /** key => [value type, writable] */
    private const KEYS = [
        'name' => ['hostname', true],
        'default.task.groupId' => ['id', true],
        'default.task.creatorId' => ['id', true],
        'default.task.responsibleId' => ['id', true],
        'default.task.allowsTimeTracking' => ['boolean', true],
        'default.users.observerId' => ['id', true],
        'default.users.managerId' => ['id', true],
        'default.users.autoAddObserver' => ['boolean', true],
        'logging.enabled' => ['boolean', true],
        'logging.level' => ['level', true],
        'logging.directory' => ['directory', true],
        'logging.fileMask' => ['fileMask', true],
        'logging.retentionDays' => ['count', true],
        'telemetry.collectProblemTrajectories' => ['boolean', true],
        'telemetry.maxProblemTrajectoryFiles' => ['count', true],
        'telemetry.lastProblemTrajectoryProcessingAt' => ['timestamp', false],
        'telemetry.lastProblemTrajectoryAnalysisOfferedAt' => ['timestamp', false],
    ];

    /**
     * @return list<string>
     */
    public static function names(bool $writable = false): array
    {
        return array_keys($writable ? array_filter(self::KEYS, static fn (array $definition): bool => $definition[1]) : self::KEYS);
    }

    public static function isRoot(string $key): bool
    {
        return str_starts_with($key, 'logging.') || str_starts_with($key, 'telemetry.');
    }

    /**
     * Rejects unknown, protected and (for writes) read-only keys before any file is touched.
     */
    public static function assertKnown(string $key, bool $write): void
    {
        if ($key === 'defaultProfile') {
            throw Failure::usage('defaultProfile is not a config:* setting; choose the default profile with auth:switch NAME.');
        }

        if ($key === 'connection' || str_starts_with($key, 'connection.')) {
            throw Failure::usage('connection.* is managed by credentials commands; replace the webhook and its hostname with auth:login NAME --replace.');
        }

        if (!isset(self::KEYS[$key])) {
            throw Failure::usage('Unknown configuration key. Keys are exact config.json paths: ' . implode(', ', self::names()) . '.');
        }

        if ($write && !self::KEYS[$key][1]) {
            throw Failure::usage($key . ' is read-only: the CLI maintains it. Writable keys: ' . implode(', ', self::names(true)) . '.');
        }
    }

    /**
     * Converts a command-line value by the type of its key; the type is never guessed from the value.
     */
    public static function parse(string $key, string $value): mixed
    {
        self::assertKnown($key, true);
        $maskProblem = self::KEYS[$key][0] === 'fileMask' ? LoggingSettings::maskProblem($value) : null;
        if ($maskProblem !== null) {
            throw Failure::usage($maskProblem);
        }

        return match (self::KEYS[$key][0]) {
            'id' => self::integer($value) ?? throw Failure::usage($key . ' expects a positive integer ID from 1 to ' . PHP_INT_MAX . ' written with digits only, for example 187.'),
            'count' => self::integer($value) ?? throw Failure::usage($key . ' expects a positive integer from 1 to ' . PHP_INT_MAX . ' written with digits only, for example 7.'),
            'boolean' => match ($value) {
                'true' => true,
                'false' => false,
                default => throw Failure::usage($key . ' expects true or false.'),
            },
            'level' => in_array($value, LoggingSettings::LEVELS, true) ? $value : throw Failure::usage($key . ' expects one of ' . implode(', ', LoggingSettings::LEVELS) . ' in lower case.'),
            'hostname' => ProfileRepository::domain($value) ? $value : throw Failure::usage($key . ' expects a bare ASCII hostname such as example.bitrix24.ru, without scheme, port or path.'),
            'fileMask' => $value,
            default => $value !== '' ? $value : throw Failure::usage($key . ' expects a nonempty path; use config:unset ' . $key . ' to return to the default logs directory.'),
        };
    }

    /**
     * Effective value of one key with its source: profile, root or default.
     *
     * @return array{key: string, value: mixed, source: string}
     */
    public static function read(array $config, ?string $alias, string $key): array
    {
        self::assertKnown($key, false);
        $parts = explode('.', $key);
        if ($parts[0] === 'logging') {
            $present = array_key_exists('logging', $config);
            $effective = LoggingSettings::effective($config['logging'] ?? null, $present);
            $saved = $effective['status'] !== 'invalid' && $present && property_exists($config['logging'], $parts[1]);
            return ['key' => $key, 'value' => $effective['settings'][$parts[1]], 'source' => $saved ? 'root' : 'default'];
        }

        if ($parts[0] === 'telemetry') {
            $saved = array_key_exists($parts[1], $config['telemetry'] ?? []);
            return ['key' => $key, 'value' => $saved ? $config['telemetry'][$parts[1]] : TelemetrySettings::DEFAULTS[$parts[1]], 'source' => $saved ? 'root' : 'default'];
        }

        $profile = $alias === null ? [] : ($config['profiles'][$alias] ?? []);
        $container = $key === 'name' ? $profile : ($profile['default'][$parts[1]] ?? []);
        $field = $parts[count($parts) - 1];
        if (array_key_exists($field, $container)) {
            return ['key' => $key, 'value' => $container[$field], 'source' => 'profile'];
        }

        return ['key' => $key, 'value' => self::KEYS[$key][0] === 'boolean' ? false : null, 'source' => 'default'];
    }

    /**
     * Effective values of every key; profile keys are listed only for a selected profile.
     *
     * @return list<array{key: string, value: mixed, source: string}>
     */
    public static function listing(array $config, ?string $alias): array
    {
        $items = [];
        foreach (self::names() as $key) {
            if ($alias !== null || self::isRoot($key)) {
                $items[] = self::read($config, $alias, $key);
            }
        }

        return $items;
    }

    private static function integer(string $value): ?int
    {
        // Digits only: no sign, spaces, 1.0 or 1e0; the round trip rejects values beyond PHP_INT_MAX.
        return preg_match('/^[1-9][0-9]*$/D', $value) === 1 && (string) (int) $value === $value ? (int) $value : null;
    }
}
