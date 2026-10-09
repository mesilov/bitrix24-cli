<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Request;

final readonly class AddTaskChecklistItemRequest
{
    public function __construct(public int $taskId, public int $checklistId, public string $title, public ?int $parentId)
    {
    }
}
