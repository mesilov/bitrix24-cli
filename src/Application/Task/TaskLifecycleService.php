<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;
use Bitrix24\CLI\Application\Task\Port\TaskLifecycleGateway;
use Bitrix24\CLI\Application\Task\Request\TaskLifecycleRequest;

final readonly class TaskLifecycleService
{
    private const STATE_FIELDS = ['status', 'needsControl', 'requireResult'];

    public function __construct(private TaskGateway $tasks, private TaskLifecycleGateway $lifecycle, private RuntimeState $state)
    {
    }

    public function routes(TaskLifecycleRequest $request): array
    {
        return [new Route('tasks.task.get', 3), new Route('tasks.task.access.get', 3), new Route($request->action->method(), 1, 'write'),
            ...($request->action === LifecycleAction::Complete ? [new Route('tasks.task.result.list', 3)] : [])];
    }

    public function prepare(TaskLifecycleRequest $request): PreparedOperation
    {
        $this->state->checkCancellation();
        $task = $this->tasks->get($request->taskId, [...self::STATE_FIELDS, 'containsResults', 'autocompleteSubTasks']);
        foreach (self::STATE_FIELDS as $field) {
            if (!$this->validStateField($task, $field)) {
                throw new Failure('lifecycle-unavailable', 'A required lifecycle field was not returned with its documented type: ' . $field . '.', 4);
            }
        }

        foreach (['containsResults', 'autocompleteSubTasks'] as $field) {
            if (!is_bool($task[$field] ?? null)) {
                throw new Failure('lifecycle-unavailable', 'A required lifecycle setting was not returned: ' . $field . '.', 4);
            }
        }

        $this->state->checkCancellation();
        $access = $this->tasks->access($request->taskId);
        $allowed = $access[$request->action->value] ?? null;
        if (!is_bool($allowed)) {
            throw new Failure('lifecycle-unavailable', 'The action-specific permission was not verified.', 4);
        }

        if (!$allowed) {
            throw new Failure('permission-denied', 'This connection cannot perform task:' . $request->action->value . '.');
        }

        if (in_array($request->action, [LifecycleAction::Approve, LifecycleAction::Disapprove], true) && (!$task['needsControl'] || $task['status'] !== TaskStatus::SupposedlyCompleted->value)) {
            throw new Failure('lifecycle-unavailable', 'Task review requires needsControl=true and status=supposedly_completed.', 4);
        }

        if ($request->action === LifecycleAction::Complete && $task['requireResult']) {
            try {
                $this->state->checkCancellation();
                $hasResult = $this->lifecycle->hasNativeResult($request->taskId);
            } catch (Failure $failure) {
                if ($failure->exitStatus === 130) {
                    throw $failure;
                }

                throw new Failure('required-result-unverified', 'The required native result could not be verified; provide a result before completing the task.', 4, ['cause' => $failure->errorCode]);
            }

            if (!$hasResult) {
                throw new Failure('required-result-unverified', 'This task requires an existing native result before completion.', 4);
            }
        }

        $fields = ['taskId' => $request->taskId, 'action' => $request->action->value, 'currentStatus' => $task['status'],
            'needsControl' => $task['needsControl'], 'requireResult' => $task['requireResult'], 'autocompleteSubTasks' => $task['autocompleteSubTasks']];
        return new PreparedOperation([
            Plan::step($request->taskId, $request->action->method(), 1, 'write', $fields),
            Plan::step($request->taskId, 'tasks.task.get', 3, 'read', ['id' => $request->taskId, 'select' => self::STATE_FIELDS], 2),
        ], fn (): OperationResult => $this->execute($request), 'task ' . $request->taskId . ': ' . $request->action->value . ' (predicted action; server checks conditions and subtask effects)');
    }

    private function execute(TaskLifecycleRequest $request): OperationResult
    {
        $this->state->checkCancellation();
        try {
            $this->lifecycle->transition($request->taskId, $request->action);
        } catch (Failure $failure) {
            if ($failure->outcomeUnknown) {
                throw new Failure($failure->errorCode, $failure->getMessage() . ' Verify with task:show ' . $request->taskId . ' before retrying.', $failure->exitStatus, $failure->details, true);
            }

            throw $failure;
        }

        $task = ['id' => $request->taskId, 'status' => null, 'needsControl' => null, 'requireResult' => null];
        try {
            $this->state->checkCancellation();
            $actual = $this->tasks->get($request->taskId, self::STATE_FIELDS);
            foreach (self::STATE_FIELDS as $field) {
                if ($this->validStateField($actual, $field)) {
                    $task[$field] = $actual[$field];
                }
            }

            if (in_array(null, array_intersect_key($task, array_flip(self::STATE_FIELDS)), true)) {
                return $this->result($request, $task, 3, 'Confirmed action; current task fields are incomplete. Inspect with task:show ' . $request->taskId . '.');
            }
        } catch (Failure $failure) {
            return $this->result($request, $task, $failure->exitStatus === 130 ? 130 : 3, 'Confirmed action; state read failed (' . $failure->errorCode . '). Inspect with task:show ' . $request->taskId . '.');
        }

        return $this->result($request, $task);
    }

    private function validStateField(array $task, string $field): bool
    {
        return $field === 'status' ? is_string($task[$field] ?? null) && TaskStatus::tryFrom($task[$field]) !== null : is_bool($task[$field] ?? null);
    }

    private function result(TaskLifecycleRequest $request, array $task, int $exit = 0, ?string $reason = null): OperationResult
    {
        return new OperationResult(
            'task-lifecycle',
            ['operation' => ['taskId' => $request->taskId, 'action' => $request->action->value, 'confirmed' => true], 'task' => $task],
            ['complete' => $exit === 0, 'outcomeUnknown' => false, 'reason' => $reason],
            $exit
        );
    }
}
