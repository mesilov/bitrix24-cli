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

    public function scan(int $taskId, array $query, bool $withEvidence = false): OperationResult
    {
        $offset = 0;
        $items = [];
        $seen = [];
        $reason = null;
        $evidence = [];
        $evidenceReasons = [];
        $collectionReasons = [];
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
                $collectionReasons[] = 'page-error';
                break;
            }

            $progress = 0;
            foreach ($page->items as $row) {
                if (count($seen) >= 10000) {
                    $complete = false;
                    $reason = 'history-budget-exhausted';
                    $collectionReasons[] = 'history-budget-exhausted';
                    break;
                }

                if ($withEvidence && count($evidence) < 10000) {
                    $evidence[] = $row;
                } elseif ($withEvidence) {
                    $evidenceReasons[] = 'history-evidence-budget-exhausted';
                }

                if (!is_int($row['id'] ?? null) || $row['id'] < 1) {
                    $complete = false;
                    $reason = 'missing-history-id';
                    $collectionReasons[] = 'missing-history-id';
                    continue;
                }

                if (!isset($seen[$row['id']])) {
                    $seen[$row['id']] = $row;
                    $items[] = $row;
                    $progress++;
                }
            }

            if (!$page->complete) {
                $complete = false;
                $reason = 'history-navigation-unverified';
                $collectionReasons[] = 'history-navigation-unverified';
            }

            if ($page->next !== null && ($progress === 0 || !is_int($page->next) || $page->next <= $offset || count($seen) >= 10000)) {
                $complete = false;
                $reason = 'pagination-no-progress-or-budget';
                $collectionReasons[] = 'pagination-no-progress-or-budget';
                break;
            }

            $offset = $page->next ?? 0;
        } while ($page->next !== null);

        if ($withEvidence) {
            $byId = [];
            foreach ($evidence as $row) {
                $id = $row['id'] ?? null;
                if (is_int($id) && isset($byId[$id]) && $byId[$id] !== $row) {
                    $evidenceReasons[] = 'conflicting-history-id';
                }

                if (is_int($id)) {
                    $byId[$id] = $row;
                }
            }
        }

        $asc = strtoupper($query['order']['createdDate'] ?? 'DESC') === 'ASC';
        usort($items, static fn (array $a, array $b): int => ($asc ? 1 : -1) * ([$a['createdDate'], $a['id']] <=> [$b['createdDate'], $b['id']]));
        if ($withEvidence) {
            $reasons = array_values(array_unique([...$collectionReasons, ...$evidenceReasons]));
            $complete = $complete && $evidenceReasons === [];
            return new OperationResult('task-history', ['items' => $items, 'evidence' => $evidence], ['complete' => $complete, 'reasons' => $reasons], $complete ? 0 : 3);
        }

        return new OperationResult('task-history', ['items' => $items], ['scope' => 'visible-task-change-history', 'complete' => $complete, 'reason' => $reason, 'returned' => count($items)], $complete ? 0 : 3);
    }
}
