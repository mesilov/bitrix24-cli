<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Port\LegacyCommentGateway;
use Bitrix24\CLI\Application\Task\Request\ListTaskLegacyCommentsRequest;

/**
 * Reads the comment feed of the old task card (#182). It is a separate source from the task chat:
 * an error or a missing method is never reported as an empty feed.
 */
final readonly class TaskLegacyCommentService
{
    public const SOURCE = 'legacy-comments';

    public function __construct(private LegacyCommentGateway $comments)
    {
    }

    public function listRoutes(): array
    {
        return [new Route('task.commentitem.getlist', 1, 'read')];
    }

    public function list(ListTaskLegacyCommentsRequest $request): PreparedOperation
    {
        return new PreparedOperation([], fn (): OperationResult => $this->scan($request));
    }

    private function scan(ListTaskLegacyCommentsRequest $request): OperationResult
    {
        // The latest window and --before walk down by ID; --after and the full feed walk up.
        $direction = $request->before !== null || ($request->after === null && $request->limit !== null) ? 'desc' : 'asc';
        $cursor = $direction === 'desc' ? $request->before : $request->after;
        $items = [];
        $scanned = 0;
        $complete = true;
        $hasMore = false;
        $reason = null;
        while (true) {
            try {
                $rows = $this->comments->page($request->taskId, $direction, $cursor);
            } catch (Failure $failure) {
                if ($scanned === 0 || in_array($failure->exitStatus, [4, 130], true)) {
                    throw $this->unavailable($failure, $request->taskId);
                }

                [$complete, $hasMore, $reason] = [false, true, 'page-error'];
                break;
            }

            if ($rows === []) {
                break;
            }

            $progress = 0;
            $next = $cursor;
            foreach ($rows as $row) {
                $id = $row['id'];
                if (isset($items[$id]) || ($cursor !== null && ($direction === 'asc' ? $id <= $cursor : $id >= $cursor))) {
                    continue;
                }

                if ($scanned >= $request->maxComments) {
                    [$complete, $hasMore, $reason] = [false, true, 'comment-budget-exhausted'];
                    break 2;
                }

                $scanned++;
                $progress++;
                $items[$id] = $row;
                $next = $next === null ? $id : ($direction === 'asc' ? max($next, $id) : min($next, $id));
                if ($request->limit !== null && count($items) >= $request->limit) {
                    // A full window cannot prove the end of the feed without another request.
                    $hasMore = true;
                    break 2;
                }
            }

            if ($progress === 0) {
                [$complete, $hasMore, $reason] = [false, true, 'pagination-no-progress'];
                break;
            }

            $cursor = $next;
        }

        ksort($items);
        $items = array_values($items);
        return new OperationResult('task-legacy-comments', ['items' => $items], [
            'source' => self::SOURCE,
            'legacy' => true,
            'taskId' => $request->taskId,
            'scope' => $request->limit === null ? 'legacy-comment-history' : 'selected-comment-window',
            'complete' => $complete,
            'scanned' => $scanned,
            'returned' => count($items),
            'hasMore' => $hasMore,
            'reason' => $reason,
        ], $complete ? 0 : 3);
    }

    private function unavailable(Failure $failure, int $taskId): Failure
    {
        $code = $failure->details['apiErrorCode'] ?? null;
        $details = [...$failure->details, 'source' => self::SOURCE, 'taskId' => $taskId];
        return match (true) {
            $failure->errorCode === 'api-error' && $code === 'ERROR_METHOD_NOT_FOUND' => new Failure('legacy-comments-unavailable', 'The legacy comment feed was not checked: the portal does not provide task.commentitem.getlist.', 1, $details),
            $failure->errorCode === 'api-error' && $code === 'ERROR_CORE' => new Failure('legacy-comments-unavailable', 'The legacy comment feed was not checked: the task is missing, not accessible to this connection, or the portal rejected the request; verify TASK_ID with task:show.', 1, $details),
            default => $failure,
        };
    }
}
