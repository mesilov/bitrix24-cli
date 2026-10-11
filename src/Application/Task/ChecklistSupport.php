<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Task\Port\ChecklistGateway;

final readonly class ChecklistSupport
{
    public function __construct(private ChecklistGateway $checklists)
    {
    }

    public function tree(int $taskId): ChecklistTree
    {
        return new ChecklistTree($taskId, $this->checklists->nodes($taskId));
    }
}
