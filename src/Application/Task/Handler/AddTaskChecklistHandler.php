<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\AddTaskChecklistRequest;
use Bitrix24\CLI\Application\Task\Plan;
use Bitrix24\CLI\Application\Task\Port\ChecklistGateway;

final readonly class AddTaskChecklistHandler
{
    public function __construct(private ChecklistGateway $checklists)
    {
    }

    public function routes(): array
    {
        return [new Route('task.checklistitem.add', 1, 'write')];
    }

    public function prepare(AddTaskChecklistRequest $request): PreparedOperation
    {

        return new PreparedOperation([Plan::step($request->taskId, 'task.checklistitem.add', 1, 'write', ['TITLE' => $request->title, 'PARENT_ID' => 0])], function () use ($request): OperationResult {
            $id = $this->checklists->add($request->taskId, 0, $request->title);
            return OperationResult::mutation($request->taskId, $id, 'add', ['TITLE' => $request->title, 'PARENT_ID' => 0]);
        }, 'task ' . $request->taskId);
    }
}
