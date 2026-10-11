<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Request;

final readonly class UpdateTaskTimeEntryRequest
{
    public function __construct(public int $taskId, public int $entryId, public int $seconds, public ?string $text)
    {
    }
}
