<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat;

use Bitrix24\CLI\Application\Chat\Port\ChatDialogGateway;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Application\Task\CalendarInstant;

/**
 * Reads a period of chat history with bounded LAST_ID pagination.
 *
 * The walk starts at last_message_id + 1: without LAST_ID the API returns the window of
 * the first unread message, not the latest messages. Message dates are not proven to be
 * monotonic by ID, so the walk never stops on an old timestamp: complete means the start
 * of the available history was reached; otherwise the result is partial.
 */
final readonly class ChatHistoryScanner
{
    public const PAGE = 50;

    public function __construct(private ChatDialogGateway $dialogs, private RuntimeState $state)
    {
    }

    /** @param array{destination: array<string, mixed>, dialog: array<string, mixed>} $resolved */
    public function scan(array $resolved, MessagePeriod $period, ?int $limit, int $budget): OperationResult
    {
        $chatId = (int) $resolved['destination']['chatId'];
        $last = $resolved['dialog']['last_message_id'] ?? null;
        if ((!is_int($last) && (!is_string($last) || !ctype_digit($last))) || (int) $last < 0) {
            throw new Failure('api-error', 'im.dialog.get did not return last_message_id for the selected chat.');
        }

        $lastId = (int) $last + 1;
        $seen = [];
        $items = [];
        $users = [];
        $scanned = 0;
        $pages = 0;
        $oldest = null;
        $reason = null;
        while (true) {
            if ($this->state->cancelled) {
                $reason = 'interrupted';
                break;
            }

            if ($scanned >= $budget) {
                $reason = 'message-budget-exhausted';
                break;
            }

            $pageLimit = min(self::PAGE, $budget - $scanned);
            try {
                $page = $this->dialogs->messages($chatId, $lastId, $pageLimit);
            } catch (Failure $failure) {
                if ($pages === 0 || $failure->exitStatus === 130 || $failure->exitStatus === 4) {
                    throw $failure;
                }

                $reason = 'page-error';
                break;
            }

            $pages++;
            $users += $page['users'];
            if ($page['items'] === []) {
                break;
            }

            $ids = [];
            foreach ($page['items'] as $row) {
                $id = $row['id'] ?? null;
                $date = CalendarInstant::parse($row['createdDate'] ?? null);
                if (!is_int($id) || $id < 1 || !$date instanceof \DateTimeImmutable || (int) ($row['chatId'] ?? $chatId) !== $chatId) {
                    throw Failure::binding('A message is not verified inside the selected chat.', true);
                }

                $ids[] = $id;
                if (isset($seen[$id])) {
                    if ($seen[$id] !== $row) {
                        $reason = 'conflicting-rows';
                        break 2;
                    }

                    continue;
                }

                $seen[$id] = $row;
                $scanned++;
                $oldest = !$oldest instanceof \DateTimeImmutable || $date < $oldest ? $date : $oldest;
                if ($period->contains($date)) {
                    // params stay internal: ChatMessagesService turns them into kind/callRef and call records.
                    $items[] = ['id' => $id, 'createdDate' => $row['createdDate'], 'authorId' => $row['authorId'] ?? null, 'text' => $row['text'] ?? null, 'chatId' => $chatId, 'params' => $row['params'] ?? null];
                }
            }

            if (max($ids) >= $lastId) {
                $reason = 'pagination-no-progress';
                break;
            }

            $lastId = min($ids);
            if (count($page['items']) < $pageLimit) {
                break;
            }
        }

        usort($items, static fn (array $a, array $b): int => [CalendarInstant::parse($a['createdDate']), $a['id']] <=> [CalendarInstant::parse($b['createdDate']), $b['id']]);
        $matched = count($items);
        if ($limit !== null && $matched > $limit) {
            $items = array_slice($items, 0, $limit);
            $reason ??= 'output-limit';
        }

        $authors = [];
        $authorIds = array_unique(array_filter(array_column($items, 'authorId'), static fn (mixed $id): bool => is_int($id) && $id > 0));
        sort($authorIds);
        foreach ($authorIds as $authorId) {
            $authors[] = ['id' => $authorId, 'name' => $users[$authorId] ?? null];
        }

        $complete = $reason === null;
        return new OperationResult('chat-messages', [
            'destination' => $resolved['destination'],
            'period' => $period->toArray(),
            'items' => $items,
            'authors' => $authors,
        ], [
            'scope' => 'chat-period',
            'complete' => $complete,
            'reason' => $reason,
            'scanned' => $scanned,
            'matched' => $matched,
            'returned' => count($items),
            'pages' => $pages,
            'oldestScannedDate' => $oldest?->format(\DATE_ATOM),
            'periodStartPassed' => $oldest instanceof \DateTimeImmutable && $oldest < $period->from,
        ], $reason === 'interrupted' ? 130 : ($complete ? 0 : 3));
    }
}
