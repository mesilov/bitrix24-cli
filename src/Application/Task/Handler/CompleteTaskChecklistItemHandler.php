<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\CompleteTaskChecklistItemRequest;
use Bitrix24\CLI\Application\Task\Plan;
use Bitrix24\CLI\Application\Task\Port\ChecklistGateway;

final readonly class CompleteTaskChecklistItemHandler
{
    public function __construct(private ChecklistGateway $checklists, private \Bitrix24\CLI\Application\Task\ChecklistSupport $support)
    {
    }

    public function routes(): array
    {
        return [new Route('task.checklistitem.getlist', 1, 'read'), new Route('task.checklistitem.complete', 1, 'write')];
    }

    public function prepare(CompleteTaskChecklistItemRequest $request): PreparedOperation
    {
        $tree = $this->support->tree($request->taskId);
        $tree->item($request->itemId);
        return new PreparedOperation([Plan::step($request->taskId, 'task.checklistitem.complete', 1, 'write', ['itemId' => $request->itemId])], function () use ($request): OperationResult {
            $this->checklists->complete($request->taskId, $request->itemId);
            return OperationResult::mutation($request->taskId, $request->itemId, 'complete', ['itemId' => $request->itemId]);
        }, 'checklist item ' . $request->itemId . ' in task ' . $request->taskId);
    }
}
