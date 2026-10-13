<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Application\Task\Request\AddTaskRequest;
use Bitrix24\CLI\Application\Task\Plan;
use Bitrix24\CLI\Application\Task\FieldSchema;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;
use Bitrix24\CLI\Application\Task\Port\ParticipantGateway;

final readonly class AddTaskHandler
{
    public function __construct(private TaskGateway $tasks, private ParticipantGateway $participants, private RuntimeState $state)
    {
    }

    public function routes(AddTaskRequest $request): array
    {
        return [new Route('tasks.task.add', 3, 'write'), ...($request->advanced ? [new Route('tasks.task.field.list', 3)] : []),
            ...($request->observerId === null ? [] : [new Route('tasks.task.get', 1), new Route('tasks.task.update', 1, 'write')]),
        ];
    }

    public function prepare(AddTaskRequest $request): PreparedOperation
    {
        if ($request->advanced) {
            FieldSchema::validateWritable($this->tasks, $request->fields);
        }

        $plan = [Plan::step(null, 'tasks.task.add', 3, 'write', $request->fields)];
        if ($request->observerId !== null) {
            $plan[] = [...Plan::step(null, 'tasks.task.get', 1, 'read', ['select' => ['ID', 'AUDITORS']], 2), 'taskIdSource' => 'created-task'];
            $plan[] = [...Plan::step(null, 'tasks.task.update', 1, 'write', ['AUDITORS' => [$request->observerId]], 3), 'taskIdSource' => 'created-task', 'mergeWithExisting' => true];
        }

        return new PreparedOperation($plan, function () use ($request): OperationResult {
            $id = $this->tasks->add($request->fields);
            $result = OperationResult::mutation($id, $id, 'add', $request->fields);
            if ($request->observerId === null) {
                return $result;
            }

            $ledger = [['method' => 'tasks.task.add', 'apiVersion' => '3.0', 'taskId' => $id, 'status' => 'confirmed']];
            $method = 'tasks.task.get';
            try {
                $this->state->checkCancellation();
                $auditors = $this->participants->ids($id, ['auditors'])['auditors'];
                $ledger[] = ['method' => $method, 'apiVersion' => '1.0', 'taskId' => $id, 'status' => 'confirmed'];
                $method = 'tasks.task.update';
                $this->state->checkCancellation();
                $present = in_array($request->observerId, $auditors, true);
                if (!$present) {
                    $this->participants->set($id, null, [...$auditors, $request->observerId]);
                }

                $ledger[] = ['method' => $method, 'apiVersion' => '1.0', 'taskId' => $id, 'status' => $present ? 'already-present' : 'confirmed'];
            } catch (Failure $failure) {
                $ledger[] = ['method' => $method, 'apiVersion' => '1.0', 'taskId' => $id, 'status' => $failure->outcomeUnknown ? 'unknown' : 'failed'];
                if ($method === 'tasks.task.get') {
                    $ledger[] = ['method' => 'tasks.task.update', 'apiVersion' => '1.0', 'taskId' => $id, 'status' => 'skipped'];
                }

                return new OperationResult($result->profile, $result->data, ['complete' => false, 'ledger' => $ledger, 'observerId' => $request->observerId, 'outcomeUnknown' => $failure->outcomeUnknown,
                    'reason' => 'Task ' . $id . ' was created; observer assignment is incomplete. ' . $failure->getMessage() . ' Inspect task:show ' . $id . ' before retrying.',
                ], $failure->exitStatus === 130 ? 130 : 3);
            }

            return new OperationResult($result->profile, $result->data, ['complete' => true, 'ledger' => $ledger, 'observerId' => $request->observerId]);
        }, 'new task');
    }
}
