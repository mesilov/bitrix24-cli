<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Application\Task\Port\CurrentUserGateway;
use Bitrix24\CLI\Application\Task\Port\ProjectMemberGateway;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;
use Bitrix24\CLI\Application\Task\Request\HandoverTasksRequest;
use Bitrix24\CLI\Application\Task\Request\TaskLifecycleRequest;

final readonly class TaskHandoverService
{
    private const SELECT = ['id', 'responsible.id', 'group.id', 'status'];

    public function __construct(private TaskListScanner $scanner, private TaskGateway $tasks, private ProjectMemberGateway $members, private CurrentUserGateway $currentUser, private RuntimeState $state, private TaskLifecycleService $lifecycle)
    {
    }

    public function routes(?HandoverTasksRequest $request = null): array
    {
        $routes = [new Route('sonet_group.user.get', 1), new Route('tasks.task.list', 3), new Route('tasks.task.get', 3), new Route('tasks.task.update', 3, 'write')];
        if ($request?->targetStatus !== null) {
            $routes[] = new Route('tasks.task.access.get', 3);
            foreach (HandoverStatusPlan::actions($request->targetStatus) as $action) {
                $routes[] = new Route($action->method(), 1, 'write');
            }

            if (in_array($request->targetStatus, ['completed', 'supposedly_completed'], true)) {
                $routes[] = new Route('tasks.task.result.list', 3);
            }
        }

        return $routes;
    }

    public function prepare(HandoverTasksRequest $request): PreparedOperation
    {
        $from = $request->fromUserId ?? $this->currentUser->id();
        if ($from === $request->responsibleId) {
            throw Failure::usage('Source and responsible user must be different.');
        }

        $this->assertMembers($request, $from);
        $where = ['responsibleId' => $from, 'groupId' => $request->groupId];
        if ($request->sourceStatus !== null) {
            $where['status'] = $request->sourceStatus;
        }

        $selection = $this->scanner->scan(new TaskQuery(where: $where, limit: null, maxScan: $request->maxScan, openOnly: true, verifyExhaustionAtBudget: true));
        if (($selection->meta['complete'] ?? false) !== true || ($selection->meta['limitApplied'] ?? true)) {
            throw new Failure('partial-result', 'No tasks were changed: the task selection is incomplete. Increase --max-scan or verify access and predicate fields.', 3, ['selection' => $selection->meta]);
        }

        $rows = array_values(array_filter($selection->data['items'], static fn (array $row): bool => !in_array($row['id'], $request->excludeIds, true)));
        $items = array_map(static fn (array $row): array => [
            'taskId' => $row['id'], 'title' => $row['title'], 'fromUserId' => $from, 'responsibleId' => $request->responsibleId,
            'sourceStatus' => $row['status'], 'targetStatus' => $request->targetStatus ?? $row['status'], 'actualStatus' => null,
            'assignmentStatus' => 'skipped', 'transitionStatus' => $request->targetStatus === null ? 'not_requested' : 'skipped',
            'verificationStatus' => 'skipped', 'transitionActions' => [], 'status' => 'skipped', 'reason' => null,
        ], $rows);
        $plan = [Plan::step(null, 'sonet_group.user.get', 1, 'read', ['ID' => $request->groupId]), Plan::step(null, 'tasks.task.list', 3, 'read', $where, 2)];
        foreach ($items as $item) {
            $plan[] = Plan::step($item['taskId'], 'sonet_group.user.get', 1, 'read', ['ID' => $request->groupId], count($plan) + 1);
            $plan[] = Plan::step($item['taskId'], 'tasks.task.get', 3, 'read', ['select' => self::SELECT], count($plan) + 1);
            $plan[] = Plan::step($item['taskId'], 'tasks.task.update', 3, 'write', ['responsibleId' => $request->responsibleId], count($plan) + 1);
            $plan[] = Plan::step($item['taskId'], 'tasks.task.get', 3, 'read', ['select' => self::SELECT, 'expectedStatus' => $item['sourceStatus']], count($plan) + 1);
            if ($request->targetStatus !== null && $request->targetStatus !== $item['sourceStatus']) {
                foreach (HandoverStatusPlan::actions($request->targetStatus) as $action) {
                    $condition = ['conditional' => true, 'targetStatus' => $request->targetStatus, 'action' => $action->value, 'rightsAfterAssignment' => 'unverified'];
                    $plan[] = Plan::step($item['taskId'], 'tasks.task.get', 3, 'read', $condition, count($plan) + 1);
                    $plan[] = Plan::step($item['taskId'], 'tasks.task.access.get', 3, 'read', $condition, count($plan) + 1);
                    if ($action === LifecycleAction::Complete) {
                        $plan[] = Plan::step($item['taskId'], 'tasks.task.result.list', 3, 'read', [...$condition, 'when' => 'requireResult=true', 'autocompleteSubTasks' => 'server-side-effects-unverified'], count($plan) + 1);
                    }

                    $plan[] = Plan::step($item['taskId'], $action->method(), 1, 'write', [...$condition, 'taskId' => $item['taskId']], count($plan) + 1);
                    $plan[] = Plan::step($item['taskId'], 'tasks.task.get', 3, 'read', $condition, count($plan) + 1);
                }

                $plan[] = Plan::step($item['taskId'], 'tasks.task.get', 3, 'read', ['select' => self::SELECT, 'expectedStatus' => $request->targetStatus], count($plan) + 1);
            }
        }

        return new PreparedOperation($plan, fn (): OperationResult => $this->execute($request, $from, $items), 'project ' . $request->groupId . ', user ' . $from . ' -> ' . $request->responsibleId . '; excluded IDs: ' . implode(',', $request->excludeIds) . '; target: ' . ($request->targetStatus ?? 'preserve source status'));
    }

    private function execute(HandoverTasksRequest $request, int $from, array $items): OperationResult
    {
        $unknown = false;
        $reason = null;
        $exit = 0;
        foreach ($items as $index => &$item) {
            $ack = false;
            $writing = false;
            $phase = 'assignment';
            try {
                $this->state->checkCancellation();
                $this->assertMembers($request, $from);
                $before = $this->tasks->get($item['taskId'], [...self::SELECT, ...($request->targetStatus === 'supposedly_completed' ? ['needsControl'] : [])]);
                $this->assertCard($before, $request, $from);
                $this->assertStatus($before, $item['sourceStatus']);
                if ($request->targetStatus === 'supposedly_completed' && ($before['needsControl'] ?? null) !== true) {
                    throw new Failure('target-status-unavailable', 'Waiting for review requires needsControl=true.', 4);
                }

                $this->state->checkCancellation();
                $writing = true;
                $this->tasks->update($item['taskId'], ['responsibleId' => $request->responsibleId]);
                $writing = false;
                $ack = true;
                $item['assignmentStatus'] = 'unverified';
                $this->state->checkCancellation();
                $card = $this->tasks->get($item['taskId'], self::SELECT);
                $this->assertCard($card, $request, $request->responsibleId);
                $item['assignmentStatus'] = 'confirmed';
                $item['actualStatus'] = $card['status'] ?? null;
                $phase = 'verification';
                $this->assertStatus($card, $item['sourceStatus']);
                if ($request->targetStatus !== null && $card['status'] !== $request->targetStatus) {
                    $phase = 'transition';
                    $this->changeStatus($request, $item, $card, $writing);
                    $phase = 'verification';
                    $this->state->checkCancellation();
                    $card = $this->tasks->get($item['taskId'], self::SELECT);
                    $item['actualStatus'] = $card['status'] ?? null;
                    $this->assertCard($card, $request, $request->responsibleId);
                } elseif ($request->targetStatus !== null) {
                    $item['transitionStatus'] = 'not_needed';
                }

                $this->assertStatus($card, $item['targetStatus']);
                $item['verificationStatus'] = 'confirmed';
                $item['status'] = 'confirmed';
            } catch (Failure $failure) {
                $unknown = $failure->outcomeUnknown || ($writing && $failure->errorCode === 'api-error' && !isset($failure->details['apiErrorCode']));
                if ($phase === 'assignment') {
                    $item['assignmentStatus'] = $ack ? 'unverified' : ($unknown ? 'unknown' : ($failure->exitStatus === 130 ? 'skipped' : 'failed'));
                } elseif ($phase === 'transition' && $item['transitionStatus'] !== 'unverified') {
                    $item['transitionStatus'] = $unknown ? 'unknown' : 'failed';
                    $last = array_key_last($item['transitionActions']);
                    if ($last !== null && $item['transitionActions'][$last]['status'] === 'skipped') {
                        $item['transitionActions'][$last]['status'] = $item['transitionStatus'];
                    }
                }

                $item['verificationStatus'] = $failure->errorCode === 'handover-status-conflict' ? 'failed' : 'unverified';
                $item['status'] = $unknown ? 'unknown' : ($ack ? ($phase === 'transition' ? 'partial' : 'unverified') : ($failure->exitStatus === 130 ? 'skipped' : 'failed'));
                $item['reason'] = $failure->errorCode . ': ' . $failure->getMessage();
                foreach (array_keys($items) as $remaining) {
                    if ($remaining > $index) {
                        $items[$remaining]['reason'] = 'Not attempted after the handover stopped at task ' . $item['taskId'] . '.';
                    }
                }

                $reason = 'Stopped. Inspect task:show ' . $item['taskId'] . ' before deciding whether to retry. ' . $failure->getMessage();
                $exit = $failure->exitStatus === 130 ? 130 : 3;
                break;
            }
        }

        unset($item);

        return new OperationResult('task-handover', ['items' => $items], [
            'scope' => 'tasks-visible-to-connection', 'groupId' => $request->groupId, 'fromUserId' => $from, 'responsibleId' => $request->responsibleId,
            'sourceStatus' => $request->sourceStatus, 'excludedIds' => $request->excludeIds, 'targetStatus' => $request->targetStatus,
            'selected' => count($items), 'confirmed' => count(array_filter($items, static fn (array $item): bool => $item['status'] === 'confirmed')),
            'assigned' => count(array_filter($items, static fn (array $item): bool => $item['assignmentStatus'] === 'confirmed')),
            'selectionComplete' => true, 'complete' => $exit === 0, 'outcomeUnknown' => $unknown, 'reason' => $reason,
        ], $exit);
    }

    private function changeStatus(HandoverTasksRequest $request, array &$item, array $card, bool &$writing): void
    {
        $performed = [];
        while ($card['status'] !== $request->targetStatus) {
            $this->state->checkCancellation();
            $action = HandoverStatusPlan::next($card['status'], $request->targetStatus);
            if (in_array($action, $performed, true) || count($performed) >= 3) {
                throw new Failure('target-status-unavailable', 'The requested target was not reached; no lifecycle action will be repeated.', 4);
            }

            $performed[] = $action;
            $item['transitionActions'][] = ['action' => $action->value, 'status' => 'skipped', 'actualStatus' => null];
            $last = array_key_last($item['transitionActions']);
            $operation = $this->lifecycle->prepare(new TaskLifecycleRequest($item['taskId'], $action));
            $this->state->checkCancellation();
            $current = $this->tasks->get($item['taskId'], self::SELECT);
            $this->assertCard($current, $request, $request->responsibleId);
            $this->assertStatus($current, $card['status']);
            $item['actualStatus'] = null;
            $writing = true;
            $result = ($operation->execute)();
            $writing = false;
            $item['transitionActions'][$last]['status'] = $result->exitStatus === 0 ? 'confirmed' : 'unverified';
            $item['actualStatus'] = $result->data['task']['status'];
            $item['transitionActions'][$last]['actualStatus'] = $item['actualStatus'];
            if ($result->exitStatus !== 0) {
                $item['transitionStatus'] = 'unverified';
                throw new Failure('handover-state-unverified', $result->meta['reason'] ?? 'The transition state could not be verified.', $result->exitStatus);
            }

            $this->state->checkCancellation();
            $card = $this->tasks->get($item['taskId'], self::SELECT);
            $item['actualStatus'] = $card['status'] ?? null;
            $this->assertCard($card, $request, $request->responsibleId);
            if (!is_string($card['status'] ?? null) || TaskStatus::tryFrom($card['status']) === null) {
                throw new Failure('handover-status-conflict', 'The actual task status could not be verified.', 4);
            }
        }

        $item['transitionStatus'] = 'confirmed';
    }

    private function assertStatus(array $card, string $expected): void
    {
        if (($card['status'] ?? null) !== $expected) {
            throw new Failure('handover-status-conflict', 'The task status differs from the expected status ' . $expected . '.', 4);
        }
    }

    private function assertMembers(HandoverTasksRequest $request, int $from): void
    {
        $ids = $this->members->ids($request->groupId);
        if (!in_array($from, $ids, true) || !in_array($request->responsibleId, $ids, true)) {
            throw new Failure('project-membership-unverified', 'Both source and responsible user must be active members of the selected project.', 4);
        }
    }

    private function assertCard(array $card, HandoverTasksRequest $request, int $responsibleId): void
    {
        $groupId = $card['group']['id'] ?? $card['groupId'] ?? null;
        if (!$this->sameId($card['responsibleId'] ?? null, $responsibleId) || !$this->sameId($groupId, $request->groupId)) {
            throw new Failure('handover-conflict', 'The task project, responsible user or open state does not match the handover.', 4);
        }
    }

    private function sameId(mixed $value, int $expected): bool
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value))) && (string) $value === (string) $expected;
    }
}
