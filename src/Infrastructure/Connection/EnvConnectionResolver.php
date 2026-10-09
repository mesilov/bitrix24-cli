<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Connection;

use Bitrix24\CLI\Application\Failure;
use Symfony\Component\Dotenv\Dotenv;

final readonly class EnvConnectionResolver implements ConnectionResolver
{
    public function __construct(private string $projectRoot)
    {
    }

    public function webhook(): string
    {
        foreach (['BITRIX24_WEBHOOK', 'BITRIX24_PHP_SDK_PLAYGROUND_WEBHOOK'] as $name) {
            $value = getenv($name);
            if ($value === false) {
                $value = $_ENV[$name] ?? null;
            }

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $path = getenv('B24CLI_ENV_FILE') ?: $this->projectRoot . '/.env';
        if (!is_file($path) || !is_readable($path)) {
            throw new Failure('connection-unavailable', 'Set BITRIX24_WEBHOOK in the root .env or process environment.');
        }

        try {
            $content = file_get_contents($path);
            $values = (new Dotenv())->parse($content === false ? '' : $content);
        } catch (\Throwable) {
            throw new Failure('configuration-error', 'Cannot parse the selected environment file; check its syntax.');
        }

        $value = $values['BITRIX24_WEBHOOK'] ?? $values['BITRIX24_PHP_SDK_PLAYGROUND_WEBHOOK'] ?? '';
        if ($value === '') {
            throw new Failure('connection-unavailable', 'BITRIX24_WEBHOOK is missing from the selected environment file.');
        }

        return $value;
    }
}
