<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Application\Task\Port\CurrentUserGateway;
use Bitrix24\CLI\Application\Task\Port\ParticipantGateway;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;

final readonly class TaskListScanner
{
    public function __construct(private TaskGateway $tasks, private CurrentUserGateway $currentUser, private ParticipantGateway $participants, private RuntimeState $state)
    {
    }

    public function scan(TaskQuery $query, ?string $title = null): OperationResult
    {
        $filter = $query->ids === [] ? [] : [['id', 'in', $query->ids]];
        $scalarRole = $query->role?->scalarField();
        $participantRole = $query->role?->participantField();
        $userId = $query->role instanceof \Bitrix24\CLI\Application\Task\TaskRole ? $this->currentUser->id() : null;
        $fields = array_values(array_unique([...($title === null ? FieldSchema::LIST_FIELDS : ['id', 'title']), ...$query->select, ...array_keys($query->where), ...($query->dueBefore === null ? [] : ['deadline'])]));
        $hiddenRoleField = $scalarRole !== null && !in_array($scalarRole, $fields, true) ? $scalarRole : null;
        if ($hiddenRoleField !== null) {
            $fields[] = $hiddenRoleField;
        }

        $offset = 0;
        $scanned = 0;
        $matches = [];
        $seen = [];
        $reason = null;
        $complete = true;
        do {
            $this->state->checkCancellation();
            try {
                $page = $this->tasks->page($filter, $fields, $query->order, $offset, min(50, $query->maxScan - $scanned));
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
                $required = ['title', ...array_keys($query->where), ...($query->dueBefore === null ? [] : ['deadline'])];
                if (array_diff($required, array_keys($row)) !== [] || !is_string($row['title'] ?? null)) {
                    $reason = 'missing-predicate-field';
                    $complete = false;
                    continue;
                }

                $matched = $title === null || mb_stripos($row['title'], $title) !== false;
                foreach ($query->where as $field => $value) {
                    $matched = $matched && (string) $row[$field] === (string) $value;
                }

                if ($query->dueBefore !== null) {
                    $matched = $matched && $row['deadline'] !== null && strtotime((string) $row['deadline']) !== false && strtotime((string) $row['deadline']) < strtotime($query->dueBefore);
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
            ...($reason === 'scan-budget-exhausted' ? ['hint' => 'Increase --max-scan to inspect more tasks; --all only removes the output limit. An incomplete search does not prove that no duplicate exists.'] : []),
        ], $complete ? 0 : 3);
    }
}
