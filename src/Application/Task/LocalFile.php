<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

final readonly class LocalFile
{
    public function __construct(public string $path, public string $name, public int $sizeBytes, public array $identity)
    {
    }
}
