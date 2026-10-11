<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;

final readonly class TaskListScanner
{
    public function __construct(private TaskGateway $tasks)
    {
    }

    public function scan(TaskQuery $query, ?string $title = null): OperationResult
    {
        $filter = $query->ids === [] ? [] : [['id', 'in', $query->ids]];
        $fields = array_values(array_unique([...($title === null ? FieldSchema::LIST_FIELDS : ['id', 'title']), ...$query->select, ...array_keys($query->where), ...($query->dueBefore === null ? [] : ['deadline'])]));
        $offset = 0;
        $scanned = 0;
        $matches = [];
        $seen = [];
        $reason = null;
        $complete = true;
        do {
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

                if ($matched) {
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
        if ($title !== null) {
            usort($matches, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);
        }

        $items = $query->limit === null ? $matches : array_slice($matches, 0, $query->limit);
        return new OperationResult($title === null ? 'task-list' : 'title-matches', ['items' => $items], [
            'scope' => $query->ids === [] ? 'tasks-visible-to-connection' : 'explicit-task-ids',
            'complete' => $complete, 'scanned' => $scanned, 'matched' => $matchedCount, 'returned' => count($items),
            'limitApplied' => count($items) < $matchedCount, 'reason' => $reason,
        ], $complete ? 0 : 3);
    }
}
