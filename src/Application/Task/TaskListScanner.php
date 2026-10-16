<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Application\Task\Port\CurrentUserGateway;
use Bitrix24\CLI\Application\Task\Port\DeadlineTaskGateway;
use Bitrix24\CLI\Application\Task\Port\ParticipantGateway;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;

final readonly class TaskListScanner
{
    public function __construct(private TaskGateway $tasks, private CurrentUserGateway $currentUser, private ParticipantGateway $participants, private RuntimeState $state, private DeadlineTaskGateway $deadlineTasks)
    {
    }

    public function scan(TaskQuery $query, ?string $title = null): OperationResult
    {
        $filter = $query->ids === [] ? [] : [['id', 'in', $query->ids]];
        $deadlineWindow = $query->deadlineWindow;
        $dueBefore = $query->dueBefore === null ? null : new \DateTimeImmutable($query->dueBefore);
        if ($deadlineWindow instanceof \Bitrix24\CLI\Application\Task\DeadlineWindow) {
            $filter = [...$deadlineWindow->filter(), ...($query->ids === [] ? [] : ['ID' => $query->ids]), ...(isset($query->where['groupId']) ? ['GROUP_ID' => $query->where['groupId']] : [])];
        }

        $scalarRole = $query->role?->scalarField();
        $participantRole = $query->role?->participantField();
        $fields = array_values(array_unique([...($title === null ? FieldSchema::LIST_FIELDS : ['id', 'title']), ...$query->select, ...array_keys($query->where), ...($query->dueBefore === null && !$deadlineWindow instanceof \Bitrix24\CLI\Application\Task\DeadlineWindow ? [] : ['deadline']), ...($deadlineWindow?->overdue || $query->openOnly ? ['status'] : [])]));
        $hiddenRoleField = $scalarRole !== null && !in_array($scalarRole, $fields, true) ? $scalarRole : null;
        if ($hiddenRoleField !== null) {
            $fields[] = $hiddenRoleField;
        }

        if ($deadlineWindow instanceof \Bitrix24\CLI\Application\Task\DeadlineWindow) {
            $this->deadlineTasks->validate($fields, $query->order);
        }

        $userId = $query->role instanceof \Bitrix24\CLI\Application\Task\TaskRole && !$deadlineWindow?->empty() ? $this->currentUser->id() : null;

        $offset = 0;
        $scanned = 0;
        $matches = [];
        $seen = [];
        $reason = null;
        $complete = true;
        do {
            if ($deadlineWindow?->empty()) {
                break;
            }

            $this->state->checkCancellation();
            try {
                $page = $deadlineWindow instanceof \Bitrix24\CLI\Application\Task\DeadlineWindow
                    ? $this->deadlineTasks->page($filter, $fields, $query->order, $offset)
                    : $this->tasks->page($filter, $fields, $query->order, $offset, min(50, $query->maxScan - $scanned));
            } catch (Failure $failure) {
                if ($scanned === 0 || $failure->exitStatus === 130) {
                    throw $failure;
                }

                $reason = 'page-error';
                $complete = false;
                break;
            }

            $progress = 0;
            foreach ($page->items as $row) {
                $this->state->checkCancellation();
                if ($scanned >= $query->maxScan) {
                    $reason = 'scan-budget-exhausted';
                    $complete = false;
                    break;
                }

                $scanned++;
                $id = $row['id'] ?? null;
                if (!is_int($id) || $id < 1) {
                    $reason = 'missing-id';
                    $complete = false;
                    continue;
                }

                if (isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;
                $progress++;
                $required = ['title', ...($deadlineWindow instanceof \Bitrix24\CLI\Application\Task\DeadlineWindow ? array_keys($query->where) : array_diff(array_keys($query->where), ['groupId'])), ...($query->dueBefore === null && !$deadlineWindow instanceof \Bitrix24\CLI\Application\Task\DeadlineWindow ? [] : ['deadline']), ...($deadlineWindow?->overdue || $query->openOnly ? ['status'] : [])];
                if (array_diff($required, array_keys($row)) !== [] || !is_string($row['title'] ?? null)) {
                    $reason = 'missing-predicate-field';
                    $complete = false;
                    continue;
                }

                if ($deadlineWindow instanceof \Bitrix24\CLI\Application\Task\DeadlineWindow && array_any(array_keys($query->where), static fn (string $field): bool => $row[$field] === null)) {
                    $reason = 'missing-predicate-field';
                    $complete = false;
                    continue;
                }

                if (isset($query->where['responsibleId']) && ((!is_int($row['responsibleId']) && (!is_string($row['responsibleId']) || !ctype_digit($row['responsibleId']))) || filter_var($row['responsibleId'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false)) {
                    $reason = 'missing-predicate-field';
                    $complete = false;
                    continue;
                }

                $matched = $title === null || mb_stripos($row['title'], $title) !== false;
                foreach ($query->where as $field => $value) {
                    if ($field !== 'groupId' || isset($row['groupId']) || $deadlineWindow instanceof \Bitrix24\CLI\Application\Task\DeadlineWindow) {
                        $matched = $matched && (string) $row[$field] === (string) $value;
                    }
                }

                if ($query->openOnly) {
                    if (!in_array($row['status'], [...DeadlineWindow::OPEN_STATUSES, 'completed', 'declined'], true)) {
                        $reason = 'missing-predicate-field';
                        $complete = false;
                        continue;
                    }

                    $matched = $matched && in_array($row['status'], DeadlineWindow::OPEN_STATUSES, true);
                }

                if ($dueBefore instanceof \DateTimeImmutable) {
                    try {
                        $deadline = $row['deadline'] === null ? null : new \DateTimeImmutable(FieldSchema::date($row['deadline']));
                    } catch (\Throwable) {
                        $reason = 'missing-predicate-field';
                        $complete = false;
                        continue;
                    }

                    $matched = $matched && $deadline instanceof \DateTimeImmutable && $deadline < $dueBefore;
                }

                if ($deadlineWindow instanceof \Bitrix24\CLI\Application\Task\DeadlineWindow) {
                    $deadlineMatch = $deadlineWindow->matches($row['deadline']);
                    if ($deadlineMatch === null || ($deadlineWindow->overdue && !is_string($row['status']))) {
                        $reason = 'missing-predicate-field';
                        $complete = false;
                        continue;
                    }

                    $matched = $matched && $deadlineMatch && (!$deadlineWindow->overdue || in_array($row['status'], DeadlineWindow::OPEN_STATUSES, true));
                    if ($query->ids !== []) {
                        $matched = $matched && in_array($id, $query->ids, true);
                    }
                }

                if ($scalarRole !== null) {
                    $value = $row[$scalarRole] ?? null;
                    if ((!is_int($value) && (!is_string($value) || !ctype_digit($value))) || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                        $reason = 'missing-predicate-field';
                        $complete = false;
                        continue;
                    }

                    $matched = $matched && (int) $value === $userId;
                }

                if ($matched && $participantRole !== null) {
                    try {
                        $this->state->checkCancellation();
                        $members = $this->participants->ids($id, [$participantRole]);
                        $matched = in_array($userId, $members[$participantRole], true);
                    } catch (Failure $failure) {
                        if ($failure->exitStatus === 130) {
                            throw $failure;
                        }

                        $reason = 'role-read-error';
                        $complete = false;
                        break 2;
                    }
                }

                if ($matched && !$deadlineWindow instanceof \Bitrix24\CLI\Application\Task\DeadlineWindow && array_key_exists('groupId', $query->where)) {
                    if (!isset($row['groupId'])) {
                        try {
                            $card = $this->tasks->get($id, ['group.id']);
                            $group = $card['group'] ?? null;
                            if (!array_key_exists('group', $card) || ($group !== null && (!is_array($group) || !is_int($group['id'] ?? null) || $group['id'] < 1))) {
                                throw new Failure('api-error', 'Task group membership was not returned.');
                            }

                            $row['groupId'] = $group['id'] ?? null;
                        } catch (Failure $failure) {
                            if ($failure->exitStatus === 130) {
                                throw $failure;
                            }

                            $reason = 'group-membership-unverified';
                            $complete = false;
                            continue;
                        }
                    }

                    $matched = (string) $row['groupId'] === (string) $query->where['groupId'];
                }

                if ($matched) {
                    if ($hiddenRoleField !== null) {
                        unset($row[$hiddenRoleField]);
                    }

                    $matches[] = $title === null ? $row : ['id' => $id, 'title' => $row['title']];
                }
            }

            $next = $page->next;
            if ($next !== null && ($progress === 0 || !is_int($next) || $next <= $offset)) {
                $reason = 'pagination-no-progress';
                $complete = false;
                break;
            }

            $offset = $next ?? 0;
            if ($next !== null && $scanned >= $query->maxScan) {
                if ($query->verifyExhaustionAtBudget && !$deadlineWindow instanceof \Bitrix24\CLI\Application\Task\DeadlineWindow) {
                    $this->state->checkCancellation();
                    try {
                        $probe = $this->tasks->page($filter, $fields, $query->order, $next, 1);
                        if ($probe->items === [] && $probe->next === null) {
                            $next = null;
                            continue;
                        }
                    } catch (Failure $failure) {
                        if ($failure->exitStatus === 130) {
                            throw $failure;
                        }

                        $reason = 'page-error';
                        $complete = false;
                        break;
                    }
                }

                $reason = 'scan-budget-exhausted';
                $complete = false;
                break;
            }
        } while ($next !== null);

        $matchedCount = count($matches);
        $items = $query->limit === null ? $matches : array_slice($matches, 0, $query->limit);
        return new OperationResult($title === null ? 'task-list' : 'title-matches', ['items' => $items], [
            'scope' => $query->ids === [] ? 'tasks-visible-to-connection' : 'explicit-task-ids',
            'complete' => $complete, 'scanned' => $scanned, 'matched' => $matchedCount, 'returned' => count($items),
            'limitApplied' => count($items) < $matchedCount, 'reason' => $reason,
            ...($query->role instanceof \Bitrix24\CLI\Application\Task\TaskRole ? ['roleFilter' => ['role' => $query->role->value, 'userId' => $userId]] : []),
            'order' => $query->order,
            ...($deadlineWindow instanceof \Bitrix24\CLI\Application\Task\DeadlineWindow ? ['deadlineFilter' => [...$deadlineWindow->metadata(), 'groupApplied' => isset($query->where['groupId']) ? 'server' : null]] : []),
            ...($reason === 'scan-budget-exhausted' ? ['hint' => 'Increase --max-scan to inspect more tasks; --all only removes the output limit. An incomplete search does not prove that no duplicate exists.'] : []),
        ], $complete ? 0 : 3);
    }
}
