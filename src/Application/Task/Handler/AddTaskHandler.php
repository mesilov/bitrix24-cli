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
    public function __construct(private TaskGateway $tasks, private \Bitrix24\CLI\Application\Task\TaskTagMutation $tags)
    {
    }

    public function routes(AddTaskRequest $request): array
    {
        return [new Route('tasks.task.add', 3, 'write'), ...($request->TAGS !== null ? [new Route('tasks.task.update', 1, 'write')] : []), ...($request->advanced ? [new Route('tasks.task.field.list', 3)] : [])];
    }

    public function prepare(AddTaskRequest $request): PreparedOperation
    {
        if ($request->advanced) {
            FieldSchema::validateWritable($this->tasks, $request->fields);
        }

        $steps = [Plan::step(null, 'tasks.task.add', 3, 'write', $request->fields)];
        if ($request->TAGS !== null) {
            $steps[] = [...Plan::step(null, 'tasks.task.update', 1, 'write', ['TAGS' => $request->TAGS], 2), 'taskIdFromStep' => 1];
        }

        return new PreparedOperation($steps, function () use ($request): OperationResult {
            $id = $this->tasks->add($request->fields);
            $result = OperationResult::mutation($id, $id, 'add', $request->fields);
            return $request->TAGS === null ? $result : $this->tags->finish($id, $request->fields, $request->TAGS, 'add', $result->data['operation']);
        }, 'new task');
    }
}
