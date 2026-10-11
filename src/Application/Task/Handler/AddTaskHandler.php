<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\AddTaskRequest;
use Bitrix24\CLI\Application\Task\Plan;
use Bitrix24\CLI\Application\Task\FieldSchema;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;

final readonly class AddTaskHandler
{
    public function __construct(private TaskGateway $tasks)
    {
    }

    public function routes(AddTaskRequest $request): array
    {
        return [new Route('tasks.task.add', 3, 'write'), ...($request->advanced ? [new Route('tasks.task.field.list', 3)] : [])];
    }

    public function prepare(AddTaskRequest $request): PreparedOperation
    {
        if ($request->advanced) {
            FieldSchema::validateWritable($this->tasks, $request->fields);
        }

        return new PreparedOperation([Plan::step(null, 'tasks.task.add', 3, 'write', $request->fields)], function () use ($request): OperationResult {
            $id = $this->tasks->add($request->fields);
            return OperationResult::mutation($id, $id, 'add', $request->fields);
        }, 'new task');
    }
}
