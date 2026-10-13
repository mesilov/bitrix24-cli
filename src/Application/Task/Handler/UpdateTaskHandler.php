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
    public function __construct(private TaskGateway $tasks, private \Bitrix24\CLI\Application\Task\TaskTagMutation $tags, private \Bitrix24\CLI\Application\Task\Port\TaskUserFieldGateway $custom, private \Bitrix24\CLI\Application\RuntimeState $state)
    {
    }

    public function routes(UpdateTaskRequest $request): array
    {
        return [...($request->fields !== [] ? [new Route('tasks.task.update', 3, 'write')] : []), ...($request->TAGS !== null ? [new Route('tasks.task.update', 1, 'write')] : []), ...($request->userFields === null ? [] : [new Route('tasks.task.getFields', 1), new Route('tasks.task.update', 1, 'write')])];
    }

    public function prepare(UpdateTaskRequest $request): PreparedOperation
    {
        if ($request->fields !== [] || ($request->TAGS === null && $request->userFields === null)) {
            FieldSchema::localPatch($request->fields, false);
        }

        if ((($request->TAGS !== null || $request->userFields !== null) && $request->operation !== 'task:update') || !in_array($request->operation, ['task:update', 'task:assign', 'task:deadline:set'], true) || ($request->operation === 'task:assign' && array_keys($request->fields) !== ['responsibleId']) || ($request->operation === 'task:deadline:set' && array_keys($request->fields) !== ['deadline'])) {
            throw Failure::usage('This convenience operation accepts only its designated field.');
        }

        $steps = [];
        if ($request->userFields !== null) {
            \Bitrix24\CLI\Application\Task\CustomTaskFields::validate($request->userFields, $this->custom->fields());
            $steps[] = Plan::step($request->taskId, 'tasks.task.getFields', 1, 'read');
        }

        if ($request->fields !== []) {
            $steps[] = Plan::step($request->taskId, 'tasks.task.update', 3, 'write', $request->fields, count($steps) + 1);
        }

        if ($request->userFields !== null) {
            $steps[] = Plan::step($request->taskId, 'tasks.task.update', 1, 'write', $request->userFields, count($steps) + 1);
        }

        if ($request->TAGS !== null) {
            $steps[] = Plan::step($request->taskId, 'tasks.task.update', 1, 'write', ['TAGS' => $request->TAGS], count($steps) + 1);
        }

        return new PreparedOperation($steps, function () use ($request): OperationResult {
            $result = OperationResult::mutation($request->taskId, $request->taskId, 'update', $request->fields);
            if ($request->fields !== []) {
                $this->tasks->update($request->taskId, $request->fields);
            }

            if ($request->userFields === null) {
                return $request->TAGS === null ? $result : $this->tags->finish($request->taskId, $request->fields, $request->TAGS, 'update', $request->fields === [] ? null : $result->data['operation']);
            }

            $executionLedger = new \Bitrix24\CLI\Application\ExecutionLedger();
            if ($request->fields !== []) {
                $executionLedger->confirm($result->data['operation']);
            }

            try {
                $this->state->checkCancellation();
                $this->custom->update($request->taskId, $request->userFields);
                $combined = OperationResult::mutation($request->taskId, $request->taskId, 'update', [...$request->fields, ...$request->userFields]);
                $executionLedger->confirm(OperationResult::mutation($request->taskId, $request->taskId, 'custom:set', $request->userFields)->data['operation']);
                if ($request->TAGS !== null) {
                    $this->state->checkCancellation();
                    $tagged = $this->tags->finish($request->taskId, [...$request->fields, ...$request->userFields], $request->TAGS, 'update', $combined->data['operation']);
                    return new OperationResult($tagged->profile, $tagged->data, [...$tagged->meta, 'ledger' => [...$executionLedger->result()->meta['ledger'], ...array_slice($tagged->meta['ledger'], 1)]], $tagged->exitStatus);
                }

                return new OperationResult($combined->profile, $combined->data, $executionLedger->result()->meta);
            } catch (Failure $failure) {
                return $executionLedger->stop($request->taskId, $request->TAGS === null ? [] : [$request->taskId], new Failure($failure->errorCode, $failure->getMessage() . ' Inspect task:show ' . $request->taskId . ' --select userFields.key --select userFields.value; retry only task:update after checking its values.', $failure->exitStatus, [...$failure->details, 'taskId' => $request->taskId], $failure->outcomeUnknown));
            }
        }, 'task ' . $request->taskId);
    }
}
