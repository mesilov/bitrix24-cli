<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\AddTaskChecklistItemRequest;
use Bitrix24\CLI\Application\Task\Plan;
use Bitrix24\CLI\Application\Task\Port\ChecklistGateway;

final readonly class AddTaskChecklistItemHandler
{
    public function __construct(private ChecklistGateway $checklists, private \Bitrix24\CLI\Application\Task\ChecklistSupport $support)
    {
    }

    public function routes(): array
    {
        return [new Route('task.checklistitem.getlist', 1, 'read'), new Route('task.checklistitem.add', 1, 'write')];
    }

    public function prepare(AddTaskChecklistItemRequest $request): PreparedOperation
    {
        $parent = $this->support->tree($request->taskId)->parent($request->checklistId, $request->parentId);
        return new PreparedOperation([Plan::step($request->taskId, 'task.checklistitem.add', 1, 'write', ['TITLE' => $request->title, 'PARENT_ID' => $parent])], function () use ($request, $parent): OperationResult {
            $id = $this->checklists->add($request->taskId, $parent, $request->title);
            return OperationResult::mutation($request->taskId, $id, 'add', ['title' => $request->title, 'parentId' => $parent]);
        }, 'item in checklist ' . $request->checklistId);
    }
}
