<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

use Bitrix24\CLI\Application\Task\LocalFile;

interface LocalFileGateway
{
    public function inspect(string $path, ?string $name = null, int $maxUploadBytes = LocalFile::DEFAULT_MAX_BYTES): LocalFile;
    public function encode(LocalFile $file): string|\Bitrix24\CLI\Application\Task\StreamingBase64;
}
