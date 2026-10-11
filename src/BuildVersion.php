<?php

declare(strict_types=1);

namespace Bitrix24\CLI;

use RuntimeException;

final class BuildVersion
{
    public static function version(): string
    {
        $path = __DIR__ . '/../build-info.json';
        if (!is_file($path)) {
            return '0.1.0';
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Cannot read build identity.');
        }

        $info = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($info) || !isset($info['version']) || !is_string($info['version'])) {
            throw new RuntimeException('Invalid build identity.');
        }

        return $info['version'];
    }
}
