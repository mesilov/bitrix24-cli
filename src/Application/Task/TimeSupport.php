<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\Task\Port\TimeEntryGateway;

final readonly class TimeSupport
{
    public function __construct(private TimeEntryGateway $entries)
    {
    }

    public function requireEntry(int $taskId, int $entryId): void
    {
        $result = $this->scan($taskId, [], false, $entryId);
        if (array_any($result->data['items'], static fn (array $row): bool => $row['id'] === $entryId)) {
            return;
        }

        throw Failure::binding('The time entry is not verified inside this task.');
    }

    public function scan(int $taskId, array $query, bool $singlePage, ?int $findId = null): OperationResult
    {
        $pageNumber = $query['params']['NAV_PARAMS']['iNumPage'] ?? 1;
        $items = [];
        $seen = [];
        $scanned = 0;
        $complete = true;
        $reason = null;
        do {
            try {
                $page = $this->entries->page($taskId, $query, $pageNumber);
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
                if ($scanned >= 10000) {
                    $reason = 'entry-budget-exhausted';
                    $complete = false;
                    break;
                }

                $scanned++;
                if (($row['taskId'] ?? null) !== $taskId || !is_int($row['id'] ?? null) || $row['id'] < 1) {
                    throw Failure::binding('A time entry is not verified inside the selected task.');
                }

                if (isset($seen[$row['id']])) {
                    continue;
                }

                $seen[$row['id']] = true;
                $progress++;
                $items[] = $row;
                if ($findId === $row['id']) {
                    return new OperationResult('time-entries', ['items' => [$row]], ['complete' => true]);
                }
            }

            if ($singlePage || $page->next === null) {
                break;
            }

            if ($scanned >= 10000 || $progress === 0 || !is_int($page->next) || $page->next <= $pageNumber) {
                $reason = $scanned >= 10000 ? 'entry-budget-exhausted' : 'pagination-no-progress';
                $complete = false;
                break;
            }

            $pageNumber = $page->next;
        } while (true);

        return new OperationResult('time-entries', ['items' => $items], ['scope' => $singlePage ? 'selected-time-page' : 'visible-task-time-entries', 'complete' => $complete, 'scanned' => $scanned, 'returned' => count($items), 'reason' => $reason], $complete ? 0 : 3);
    }
}
