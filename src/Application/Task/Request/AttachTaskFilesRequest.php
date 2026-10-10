<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Request;

final readonly class AttachTaskFilesRequest
{
    public function __construct(public int $taskId, public array $fileIds)
    {
    }
}
