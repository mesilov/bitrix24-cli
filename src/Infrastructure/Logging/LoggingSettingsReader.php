<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Logging;

use Bitrix24\CLI\Application\Logging\LoggingSettings;
use Bitrix24\CLI\Infrastructure\Connection\ConfigDirectoryResolver;

/**
 * Reads root logging settings from config.json of the configuration directory, independent of the connection source.
 * It never takes the configuration lock, reads credentials or changes the selected connection.
 */
final readonly class LoggingSettingsReader
{
    public function __construct(private ConfigDirectoryResolver $directories)
    {
    }

    /**
     * @return array{settings: array<string, mixed>, status: string, reason: ?string, configDirectory: ?string, directory: ?string, currentFile: ?string}
     */
    public function read(?string $configDirectory, ?\DateTimeImmutable $now = null): array
    {
        try {
            $configDirectory = $this->directories->resolve($configDirectory);
        } catch (\Throwable) {
            return [...LoggingSettings::effective(null, false), 'configDirectory' => null, 'directory' => null, 'currentFile' => null];
        }

        $path = $configDirectory . '/config.json';
        if (!is_file($path)) {
            $effective = LoggingSettings::effective(null, false);
        } else {
            try {
                $raw = @file_get_contents($path);
                $object = json_decode($raw === false ? '' : $raw, false, 64, JSON_THROW_ON_ERROR);
                $effective = $object instanceof \stdClass
                    ? LoggingSettings::effective($object->logging ?? null, property_exists($object, 'logging'))
                    : ['settings' => LoggingSettings::DEFAULTS, 'status' => 'invalid', 'reason' => 'config.json is not a JSON object.'];
            } catch (\Throwable) {
                $effective = ['settings' => LoggingSettings::DEFAULTS, 'status' => 'invalid', 'reason' => 'config.json cannot be read as JSON.'];
            }
        }

        $directory = self::directory($configDirectory, $effective['settings']['directory']);
        $date = ($now ?? new \DateTimeImmutable())->setTimezone(LoggingSettings::timezone());
        return [
            ...$effective,
            'configDirectory' => $configDirectory,
            'directory' => $directory,
            'currentFile' => $directory . DIRECTORY_SEPARATOR . LoggingSettings::fileName($effective['settings']['fileMask'], $date),
        ];
    }

    public static function directory(string $configDirectory, ?string $directory): string
    {
        if ($directory === null) {
            return $configDirectory . DIRECTORY_SEPARATOR . 'logs';
        }

        if (self::absolute($directory)) {
            return rtrim($directory, '/\\') === '' ? $directory : rtrim($directory, '/\\');
        }

        return $configDirectory . DIRECTORY_SEPARATOR . rtrim($directory, '/\\');
    }

    private static function absolute(string $path): bool
    {
        return str_starts_with($path, '/') || str_starts_with($path, '\\\\') || preg_match('~^[A-Za-z]:[\\\\/]~', $path) === 1;
    }
}
