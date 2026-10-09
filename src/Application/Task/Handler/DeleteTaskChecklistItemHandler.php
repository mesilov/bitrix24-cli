<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\DeleteTaskChecklistItemRequest;
use Bitrix24\CLI\Application\Task\Plan;
use Bitrix24\CLI\Application\Task\Port\ChecklistGateway;

final readonly class DeleteTaskChecklistItemHandler
{
    public function __construct(private ChecklistGateway $checklists, private \Bitrix24\CLI\Application\Task\ChecklistSupport $support)
    {
    }

    public function routes(): array
    {
        return [new Route('task.checklistitem.getlist', 1, 'read'), new Route('task.checklistitem.delete', 1, 'delete')];
    }

    public function prepare(DeleteTaskChecklistItemRequest $request): PreparedOperation
    {
        $tree = $this->support->tree($request->taskId);
        $tree->item($request->itemId);

        $subtree = [$request->itemId, ...array_column($tree->descendants($request->itemId), 'id')];
        return new PreparedOperation([Plan::step($request->taskId, 'task.checklistitem.delete', 1, 'delete', ['itemId' => $request->itemId, 'subtreeIds' => $subtree])], function () use ($request, $subtree): OperationResult {
            $this->checklists->delete($request->taskId, $request->itemId);
            return OperationResult::mutation($request->taskId, $request->itemId, 'delete', ['itemId' => $request->itemId, 'subtreeIds' => $subtree]);
        }, 'checklist item ' . $request->itemId . ' in task ' . $request->taskId . ' (subtree: ' . implode(', ', $subtree) . ')');
    }
}
