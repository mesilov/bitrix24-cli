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
    public function __construct(private TaskGateway $tasks, private \Bitrix24\CLI\Application\Task\TaskTagMutation $tags)
    {
    }

    public function routes(UpdateTaskRequest $request): array
    {
        return [...($request->fields !== [] ? [new Route('tasks.task.update', 3, 'write')] : []), ...($request->TAGS !== null ? [new Route('tasks.task.update', 1, 'write')] : []), ...($request->advanced ? [new Route('tasks.task.field.list', 3)] : [])];
    }

    public function prepare(UpdateTaskRequest $request): PreparedOperation
    {
        if ($request->fields !== [] || $request->TAGS === null) {
            FieldSchema::localPatch($request->fields, false);
        }

        if (($request->TAGS !== null && $request->operation !== 'task:update') || !in_array($request->operation, ['task:update', 'task:assign', 'task:deadline:set'], true) || ($request->operation === 'task:assign' && array_keys($request->fields) !== ['responsibleId']) || ($request->operation === 'task:deadline:set' && array_keys($request->fields) !== ['deadline'])) {
            throw Failure::usage('This convenience operation accepts only its designated field.');
        }

        if ($request->advanced) {
            FieldSchema::validateWritable($this->tasks, $request->fields);
        }

        $steps = $request->fields === [] ? [] : [Plan::step($request->taskId, 'tasks.task.update', 3, 'write', $request->fields)];
        if ($request->TAGS !== null) {
            $steps[] = Plan::step($request->taskId, 'tasks.task.update', 1, 'write', ['TAGS' => $request->TAGS], count($steps) + 1);
        }

        return new PreparedOperation($steps, function () use ($request): OperationResult {
            $result = OperationResult::mutation($request->taskId, $request->taskId, 'update', $request->fields);
            if ($request->fields !== []) {
                $this->tasks->update($request->taskId, $request->fields);
            }

            return $request->TAGS === null ? $result : $this->tags->finish($request->taskId, $request->fields, $request->TAGS, 'update', $request->fields === [] ? null : $result->data['operation']);
        }, 'task ' . $request->taskId);
    }
}
