<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\UpdateTaskChecklistItemRequest;
use Bitrix24\CLI\Application\Task\Plan;
use Bitrix24\CLI\Application\Task\Port\ChecklistGateway;

final readonly class UpdateTaskChecklistItemHandler
{
    public function __construct(private ChecklistGateway $checklists, private \Bitrix24\CLI\Application\Task\ChecklistSupport $support)
    {
    }

    public function routes(): array
    {
        return [new Route('task.checklistitem.getlist', 1, 'read'), new Route('task.checklistitem.update', 1, 'write')];
    }

    public function prepare(UpdateTaskChecklistItemRequest $request): PreparedOperation
    {
        $tree = $this->support->tree($request->taskId);
        $tree->item($request->itemId);
        return new PreparedOperation([Plan::step($request->taskId, 'task.checklistitem.update', 1, 'write', ['itemId' => $request->itemId, 'title' => $request->title])], function () use ($request): OperationResult {
            $this->checklists->update($request->taskId, $request->itemId, $request->title);
            return OperationResult::mutation($request->taskId, $request->itemId, 'update', ['itemId' => $request->itemId, 'title' => $request->title]);
        }, 'checklist item ' . $request->itemId . ' in task ' . $request->taskId);
    }
}
