<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

final readonly class LocalFile
{
    public const DEFAULT_MAX_BYTES = 500000000;

    public function __construct(public string $path, public string $name, public int $sizeBytes, public array $identity, public int $maxUploadBytes = self::DEFAULT_MAX_BYTES)
    {
    }
}
