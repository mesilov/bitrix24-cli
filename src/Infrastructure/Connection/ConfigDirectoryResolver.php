<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Connection;

use Bitrix24\CLI\Application\Failure;

final class ConfigDirectoryResolver
{
    public function resolve(?string $explicit = null, ?array $environment = null, ?string $os = null, ?string $cwd = null): string
    {
        $environment ??= getenv();
        $os ??= PHP_OS_FAMILY;
        $cwd ??= getcwd() ?: '';
        $path = $explicit ?? ($environment['B24CLI_CONFIG_DIR'] ?? '');
        if ($path !== '') {
            return $this->absolute($path) ? rtrim($path, '/\\') : $cwd . DIRECTORY_SEPARATOR . $path;
        }

        if ($explicit !== null) {
            throw Failure::usage('--config-dir requires a nonempty path.');
        }

        if ($os === 'Windows') {
            $base = $environment['APPDATA'] ?? '';
        } else {
            $xdg = $environment['XDG_CONFIG_HOME'] ?? '';
            $base = $this->absolute($xdg) ? $xdg : (($environment['HOME'] ?? '') === '' ? '' : $environment['HOME'] . '/.config');
        }

        if ($base === '' || !$this->absolute($base)) {
            throw new Failure('configuration-error', 'No user configuration directory is available; provide --config-dir.');
        }

        return rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'b24cli';
    }

    private function absolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^(?:[A-Za-z]:[\\\\\/]|\\\\\\\\)/', $path) === 1;
    }
}
