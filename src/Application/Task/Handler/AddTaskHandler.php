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
    public function __construct(private TaskGateway $tasks, private ParticipantGateway $participants, private RuntimeState $state, private \Bitrix24\CLI\Application\Task\TaskTagMutation $tags, private \Bitrix24\CLI\Application\Task\Port\TaskUserFieldGateway $custom)
    {
    }

    public function routes(AddTaskRequest $request): array
    {
        return [...($request->userFields === null ? [] : [new Route('tasks.task.getFields', 1)]), new Route('tasks.task.add', $request->userFields === null ? 3 : 1, 'write'),
            ...($request->TAGS === null ? [] : [new Route('tasks.task.update', 1, 'write')]),
            ...($request->observerId === null ? [] : [new Route('tasks.task.get', 1), new Route('tasks.task.update', 1, 'write')]),
        ];
    }

    public function prepare(AddTaskRequest $request): PreparedOperation
    {
        $version = $request->userFields === null ? 3 : 1;
        $fields = $request->fields;
        $plan = [];
        if ($request->userFields !== null) {
            \Bitrix24\CLI\Application\Task\LegacyTaskFields::validate($fields);
            \Bitrix24\CLI\Application\Task\CustomTaskFields::validate($request->userFields, $this->custom->fields());
            $fields = [...$fields, ...$request->userFields];
            $plan[] = Plan::step(null, 'tasks.task.getFields', 1, 'read');
        } else {
            FieldSchema::localPatch($fields, true);
        }

        $createStep = count($plan) + 1;
        $plan[] = Plan::step(null, 'tasks.task.add', $version, 'write', $fields, $createStep);
        if ($request->TAGS !== null) {
            $plan[] = [...Plan::step(null, 'tasks.task.update', 1, 'write', ['TAGS' => $request->TAGS], count($plan) + 1), 'taskIdFromStep' => $createStep];
        }

        if ($request->observerId !== null) {
            $plan[] = [...Plan::step(null, 'tasks.task.get', 1, 'read', ['select' => ['ID', 'AUDITORS']], count($plan) + 1), 'taskIdSource' => 'created-task'];
            $plan[] = [...Plan::step(null, 'tasks.task.update', 1, 'write', ['AUDITORS' => [$request->observerId]], count($plan) + 1), 'taskIdSource' => 'created-task', 'mergeWithExisting' => true];
        }

        return new PreparedOperation($plan, function () use ($request, $fields, $version): OperationResult {
            $id = $request->userFields === null ? $this->tasks->add($fields) : $this->custom->add($request->fields, $request->userFields);
            $result = OperationResult::mutation($id, $id, 'add', $fields);
            if ($request->TAGS !== null) {
                $result = $this->tags->finish($id, $fields, $request->TAGS, 'add', $result->data['operation']);
            }

            if ($request->observerId === null) {
                return $result;
            }

            if ($result->exitStatus !== 0) {
                return new OperationResult($result->profile, $result->data, [...$result->meta, 'observerId' => $request->observerId, 'ledger' => [...$result->meta['ledger'],
                    ['method' => 'tasks.task.get', 'apiVersion' => '1.0', 'taskId' => $id, 'status' => 'skipped'],
                    ['method' => 'tasks.task.update', 'apiVersion' => '1.0', 'taskId' => $id, 'status' => 'skipped'],
                ]], $result->exitStatus);
            }

            $ledger = $result->meta['ledger'] ?? [['method' => 'tasks.task.add', 'apiVersion' => $version . '.0', 'taskId' => $id, 'status' => 'confirmed']];
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
