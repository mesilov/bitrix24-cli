<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\Task\Port\TaskChatGateway;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;

final readonly class ChatSupport
{
    public function __construct(private TaskGateway $tasks, private TaskChatGateway $chat)
    {
    }

    public function chatId(int $taskId): int
    {
        $task = $this->tasks->get($taskId, ['chat.id', 'chat.entityId', 'chat.entityType']);
        $chat = $task['chat'] ?? [];
        if (!is_int($chat['id'] ?? null) || $chat['id'] < 1 || (int) ($chat['entityId'] ?? 0) !== $taskId || ($chat['entityType'] ?? null) !== 'TASKS_TASK') {
            throw Failure::binding('The task chat association is unverified; inspect task:show first.', true);
        }

        return $chat['id'];
    }

    public function requireMessage(int $taskId, int $messageId): void
    {
        $result = $this->scan($this->chatId($taskId), null, null, null, 10000, $messageId);
        foreach ($result->data['items'] as $message) {
            if ($message['id'] === $messageId) {
                return;
            }
        }

        throw Failure::binding('Message membership is unverified; use task:chat:list to inspect the selected task.', true);
    }

    public function scan(int $chatId, ?int $before, ?int $after, ?int $limit, int $budget, ?int $findId = null): OperationResult
    {
        $items = [];
        $seen = [];
        $scanned = 0;
        $reason = null;
        $complete = true;
        $hasMore = false;
        do {
            $pageLimit = min(50, $budget - $scanned, $limit === null ? 50 : max(1, $limit - count($items)));
            try {
                $page = $this->chat->messages($chatId, $before, $after, $pageLimit);
            } catch (Failure $failure) {
                if ($scanned === 0 || $failure->exitStatus === 130 || $failure->exitStatus === 4) {
                    throw $failure;
                }

                $complete = false;
                $reason = 'page-error';
                break;
            }

            $progress = 0;
            foreach ($page->items as $row) {
                if ($scanned >= $budget) {
                    $complete = false;
                    $reason = 'message-budget-exhausted';
                    break;
                }

                $scanned++;
                $id = $row['id'] ?? null;
                if (!is_int($id) || $id < 1 || (isset($row['chatId']) && (int) $row['chatId'] !== $chatId)) {
                    throw Failure::binding('A message is not verified inside the selected chat.', true);
                }

                if (isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;
                $progress++;
                $items[] = $row;
                if ($findId === $id) {
                    return new OperationResult('chat-messages', ['items' => [$row]], ['complete' => true]);
                }
            }

            $hasMore = $page->next !== null;
            if ($limit !== null && count($items) >= $limit) {
                break;
            }

            if ($page->next !== null && ($progress === 0 || $page->next === ($after ?? $before))) {
                $complete = false;
                $reason = 'pagination-no-progress';
                break;
            }

            if ($hasMore && $scanned >= $budget) {
                $complete = false;
                $reason = 'message-budget-exhausted';
                break;
            }

            if ($after === null) {
                $before = $page->next;
            } else {
                $after = $page->next;
            }
        } while ($hasMore);

        $returned = $limit === null ? $items : array_slice($items, 0, $limit);
        usort($returned, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);
        return new OperationResult('chat-messages', ['items' => $returned], ['scope' => $limit === null ? 'visible-chat-history' : 'selected-message-window', 'complete' => $complete, 'scanned' => $scanned, 'returned' => count($returned), 'hasMore' => $hasMore, 'reason' => $reason], $complete ? 0 : 3);
    }
}
