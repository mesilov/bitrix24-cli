<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\UpdateTaskRequest;
use Bitrix24\CLI\Application\Task\Plan;
use Bitrix24\CLI\Application\Task\FieldSchema;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;

final readonly class UpdateTaskHandler
{
    public function __construct(private TaskGateway $tasks)
    {
    }

    public function routes(UpdateTaskRequest $request): array
    {
        return [new Route('tasks.task.update', 3, 'write'), ...($request->advanced ? [new Route('tasks.task.field.list', 3)] : [])];
    }

    public function prepare(UpdateTaskRequest $request): PreparedOperation
    {
        FieldSchema::localPatch($request->fields, false);
        if (!in_array($request->operation, ['task:update', 'task:assign', 'task:deadline:set'], true) || ($request->operation === 'task:assign' && array_keys($request->fields) !== ['responsibleId']) || ($request->operation === 'task:deadline:set' && array_keys($request->fields) !== ['deadline'])) {
            throw Failure::usage('This convenience operation accepts only its designated field.');
        }

        if ($request->advanced) {
            FieldSchema::validateWritable($this->tasks, $request->fields);
        }

        return new PreparedOperation([Plan::step($request->taskId, 'tasks.task.update', 3, 'write', $request->fields)], function () use ($request): OperationResult {
            $this->tasks->update($request->taskId, $request->fields);
            return OperationResult::mutation($request->taskId, $request->taskId, 'update', $request->fields);
        }, 'task ' . $request->taskId);
    }
}
