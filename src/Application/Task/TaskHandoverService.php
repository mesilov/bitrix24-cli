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

final readonly class TaskHandoverService
{
    private const SELECT = ['id', 'responsible.id', 'group.id', 'status'];

    public function __construct(private TaskListScanner $scanner, private TaskGateway $tasks, private ProjectMemberGateway $members, private CurrentUserGateway $currentUser, private RuntimeState $state)
    {
    }

    public function routes(): array
    {
        return [new Route('sonet_group.user.get', 1), new Route('tasks.task.list', 3), new Route('tasks.task.get', 3), new Route('tasks.task.update', 3, 'write')];
    }

    public function prepare(HandoverTasksRequest $request): PreparedOperation
    {
        $from = $request->fromUserId ?? $this->currentUser->id();
        if ($from === $request->responsibleId) {
            throw Failure::usage('Source and responsible user must be different.');
        }

        $this->assertMembers($request, $from);
        $selection = $this->scanner->scan(new TaskQuery(where: ['responsibleId' => $from, 'groupId' => $request->groupId], limit: null, maxScan: $request->maxScan, openOnly: true));
        if (($selection->meta['complete'] ?? false) !== true || ($selection->meta['limitApplied'] ?? true)) {
            throw new Failure('partial-result', 'No tasks were changed: the task selection is incomplete. Increase --max-scan or verify access and predicate fields.', 3, ['selection' => $selection->meta]);
        }

        $items = array_map(static fn (array $row): array => ['taskId' => $row['id'], 'title' => $row['title'], 'fromUserId' => $from, 'responsibleId' => $request->responsibleId, 'status' => 'skipped', 'reason' => null], $selection->data['items']);
        $plan = [Plan::step(null, 'sonet_group.user.get', 1, 'read', ['ID' => $request->groupId]), Plan::step(null, 'tasks.task.list', 3, 'read', ['responsibleId' => $from, 'groupId' => $request->groupId], 2)];
        foreach ($items as $item) {
            $plan[] = Plan::step($item['taskId'], 'sonet_group.user.get', 1, 'read', ['ID' => $request->groupId], count($plan) + 1);
            $plan[] = Plan::step($item['taskId'], 'tasks.task.get', 3, 'read', ['select' => self::SELECT], count($plan) + 1);
            $plan[] = Plan::step($item['taskId'], 'tasks.task.update', 3, 'write', ['responsibleId' => $request->responsibleId], count($plan) + 1);
            $plan[] = Plan::step($item['taskId'], 'tasks.task.get', 3, 'read', ['select' => self::SELECT], count($plan) + 1);
        }

        return new PreparedOperation($plan, fn (): OperationResult => $this->execute($request, $from, $items), 'project ' . $request->groupId . ', user ' . $from . ' -> ' . $request->responsibleId);
    }

    private function execute(HandoverTasksRequest $request, int $from, array $items): OperationResult
    {
        $unknown = false;
        $reason = null;
        $exit = 0;
        foreach ($items as $index => $item) {
            $ack = false;
            $writing = false;
            try {
                $this->state->checkCancellation();
                $this->assertMembers($request, $from);
                $this->assertCard($this->tasks->get($item['taskId'], self::SELECT), $request, $from, true);
                $this->state->checkCancellation();
                $writing = true;
                $this->tasks->update($item['taskId'], ['responsibleId' => $request->responsibleId]);
                $writing = false;
                $ack = true;
                $this->state->checkCancellation();
                $this->assertCard($this->tasks->get($item['taskId'], self::SELECT), $request, $request->responsibleId, false);
                $items[$index]['status'] = 'confirmed';
            } catch (Failure $failure) {
                $unknown = $failure->outcomeUnknown || ($writing && $failure->errorCode === 'api-error' && !isset($failure->details['apiErrorCode']));
                $items[$index]['status'] = $ack ? 'unverified' : ($unknown ? 'unknown' : ($failure->exitStatus === 130 ? 'skipped' : 'failed'));
                $items[$index]['reason'] = $failure->errorCode . ': ' . $failure->getMessage();
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

        return new OperationResult('task-handover', ['items' => $items], [
            'scope' => 'tasks-visible-to-connection', 'groupId' => $request->groupId, 'fromUserId' => $from, 'responsibleId' => $request->responsibleId,
            'selected' => count($items), 'confirmed' => count(array_filter($items, static fn (array $item): bool => $item['status'] === 'confirmed')),
            'selectionComplete' => true, 'complete' => $exit === 0, 'outcomeUnknown' => $unknown, 'reason' => $reason,
        ], $exit);
    }

    private function assertMembers(HandoverTasksRequest $request, int $from): void
    {
        $ids = $this->members->ids($request->groupId);
        if (!in_array($from, $ids, true) || !in_array($request->responsibleId, $ids, true)) {
            throw new Failure('project-membership-unverified', 'Both source and responsible user must be active members of the selected project.', 4);
        }
    }

    private function assertCard(array $card, HandoverTasksRequest $request, int $responsibleId, bool $before): void
    {
        $groupId = $card['group']['id'] ?? $card['groupId'] ?? null;
        if (!$this->sameId($card['responsibleId'] ?? null, $responsibleId) || !$this->sameId($groupId, $request->groupId) || ($before && !in_array($card['status'] ?? null, DeadlineWindow::OPEN_STATUSES, true))) {
            throw new Failure('handover-conflict', 'The task project, responsible user or open state does not match the handover.', 4);
        }
    }

    private function sameId(mixed $value, int $expected): bool
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value))) && (string) $value === (string) $expected;
    }
}
