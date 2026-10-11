<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\Task\Port\TaskHistoryGateway;

final readonly class HistoryScanner
{
    public function __construct(private TaskHistoryGateway $history)
    {
    }

    public function scan(int $taskId, array $query): OperationResult
    {
        $offset = 0;
        $items = [];
        $seen = [];
        $reason = null;
        $complete = true;
        do {
            try {
                $page = $this->history->page($taskId, $query, $offset);
            } catch (Failure $failure) {
                if ($items === [] || $failure->exitStatus === 130) {
                    throw $failure;
                }

                $complete = false;
                $reason = 'page-error';
                break;
            }

            $progress = 0;
            foreach ($page->items as $row) {
                if (count($seen) >= 10000) {
                    $complete = false;
                    $reason = 'history-budget-exhausted';
                    break;
                }

                if (!is_int($row['id'] ?? null) || $row['id'] < 1) {
                    $complete = false;
                    $reason = 'missing-history-id';
                    continue;
                }

                if (!isset($seen[$row['id']])) {
                    $seen[$row['id']] = true;
                    $items[] = $row;
                    $progress++;
                }
            }

            if (!$page->complete) {
                $complete = false;
                $reason = 'history-navigation-unverified';
            }

            if ($page->next !== null && ($progress === 0 || !is_int($page->next) || $page->next <= $offset || count($seen) >= 10000)) {
                $complete = false;
                $reason = 'pagination-no-progress-or-budget';
                break;
            }

            $offset = $page->next ?? 0;
        } while ($page->next !== null);

        $asc = strtoupper($query['order']['createdDate'] ?? 'DESC') === 'ASC';
        usort($items, static fn (array $a, array $b): int => ($asc ? 1 : -1) * ([$a['createdDate'], $a['id']] <=> [$b['createdDate'], $b['id']]));
        return new OperationResult('task-history', ['items' => $items], ['scope' => 'visible-task-change-history', 'complete' => $complete, 'reason' => $reason, 'returned' => count($items)], $complete ? 0 : 3);
    }
}
