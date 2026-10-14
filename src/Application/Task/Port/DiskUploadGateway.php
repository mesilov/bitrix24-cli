<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

interface DiskUploadGateway
{
    /** @return array{ID: int, NAME: string} */
    public function upload(int $folderId, string $name, string|\Bitrix24\CLI\Application\Task\StreamingBase64 $base64): array;
}
