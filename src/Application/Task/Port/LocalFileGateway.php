<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

use Bitrix24\CLI\Application\Task\LocalFile;

interface LocalFileGateway
{
    public function inspect(string $path, ?string $name = null): LocalFile;
    public function encode(LocalFile $file): string;
}
