<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Chat\CallEnricher;
use Bitrix24\CLI\Application\Chat\CallMessage;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Port\CurrentUserGateway;
use Bitrix24\CLI\Application\Task\Port\TaskChatGateway;
use Bitrix24\CLI\Application\Task\Request\DeleteTaskChatMessageRequest;
use Bitrix24\CLI\Application\Task\Request\ListTaskChatRequest;
use Bitrix24\CLI\Application\Task\Request\SendTaskChatMessageRequest;
use Bitrix24\CLI\Application\Task\Request\UpdateTaskChatMessageRequest;

final readonly class TaskChatService
{
    /** Messages read after the send while looking for the sent one (#223). */
    public const READBACK_BUDGET = 200;

    public function __construct(private ChatSupport $support, private TaskChatGateway $chat, private CallEnricher $calls, private CurrentUserGateway $currentUser)
    {
    }

    public function listRoutes(?ListTaskChatRequest $request = null): array
    {
        $routes = [new Route('tasks.task.get', 3, 'read'), new Route('im.dialog.get', 1, 'read'), new Route('im.dialog.messages.get', 1, 'read')];
        if (!$request instanceof ListTaskChatRequest || $request->callDetails) {
            // Requested only when the window contains call messages; call.followup.get is optional (scope call).
            return [...$routes, new Route('im.call.get', 1, 'read'), new Route('im.user.list.get', 1, 'read'), new Route('call.followup.get', 3, 'read')];
        }

        return $routes;
    }

    public function list(ListTaskChatRequest $request): PreparedOperation
    {
        $chatId = $this->support->chatId($request->taskId);
        return new PreparedOperation([], function () use ($chatId, $request): OperationResult {
            $scan = $this->support->scan($chatId, $request->before, $request->after, $request->limit, $request->maxMessages);
            $data = ['items' => CallMessage::mark($scan->data['items'])];
            $meta = $scan->meta;
            $exitStatus = $scan->exitStatus;
            // The server unread flag is kept as is; a missing or non-boolean value is unknown, never "read" (#148).
            $unknown = 0;
            foreach ($data['items'] as $index => $item) {
                if (!is_bool($item['unread'] ?? null)) {
                    $data['items'][$index]['unread'] = null;
                    $unknown++;
                }
            }

            if ($unknown > 0) {
                $meta['complete'] = false;
                $meta['reason'] ??= 'message-unread-unavailable';
                $meta['unreadUnavailable'] = $unknown;
                $exitStatus = $exitStatus === 0 ? 3 : $exitStatus;
            }

            if ($request->callDetails) {
                $calls = $this->calls->enrich($chatId, $scan->data['items']);
                $data['calls'] = $calls['calls'];
                $meta['calls'] = $calls['meta'];
            } else {
                $meta['calls'] = ['found' => count(array_unique(array_column(array_column($data['items'], 'callRef'), 'callId'))), 'details' => 'disabled'];
            }

            return new OperationResult($scan->profile, $data, $meta, $exitStatus);
        });
    }

    public function sendRoutes(): array
    {
        return [new Route('tasks.task.get', 3, 'read'), new Route('im.dialog.get', 1, 'read'), new Route('tasks.task.chat.message.send', 3, 'write'), new Route('im.dialog.messages.get', 1, 'read')];
    }

    /**
     * tasks.task.chat.message.send answers only true (#223). The task chat binding is proven
     * before the write, the latest message ID is remembered right before it, and afterwards
     * the only newer message of the connection user with the sent text is taken as the sent
     * one. Any other readback outcome is reported as incomplete; the send is never repeated.
     */
    public function send(SendTaskChatMessageRequest $request): PreparedOperation
    {
        $chatId = $this->support->chatId($request->taskId);
        $plan = [
            Plan::step($request->taskId, 'im.dialog.get', 1, 'read', ['dialogId' => 'chat' . $chatId, 'readback' => 'baseline last_message_id']),
            Plan::step($request->taskId, 'tasks.task.chat.message.send', 3, 'write', ['text' => $request->text], 2),
            Plan::step($request->taskId, 'im.dialog.messages.get', 1, 'read', ['dialogId' => 'chat' . $chatId, 'readback' => 'FIRST_ID = baseline, authorId = connection user, same text'], 3),
        ];

        return new PreparedOperation($plan, function () use ($request, $chatId): OperationResult {
            $authorId = $this->currentUser->id();
            $baseline = $this->chat->lastMessageId($chatId);
            $this->chat->send($request->taskId, $request->text);
            $readback = $this->readback($chatId, $baseline, $authorId, $request->text);
            $verified = $readback['status'] === 'verified';
            $result = OperationResult::mutation($request->taskId, $readback['messageId'], 'send', ['text' => $request->text]);
            $data = $result->data;
            $data['operation'] += ['chatId' => $chatId, 'dialogId' => 'chat' . $chatId, 'messageId' => $readback['messageId'], 'readback' => $readback['status']];
            $data['readback'] = $readback;
            return new OperationResult($result->profile, $data, [
                'complete' => $verified,
                'reason' => $verified ? null : 'readback-unverified',
                'outcomeUnknown' => false,
            ], $verified ? 0 : 3);
        }, 'task ' . $request->taskId);
    }

    /** @return array{status: string, messageId: ?int, chatId: int, baselineMessageId: int, scanned: int, candidates: list<int>, error?: string} */
    private function readback(int $chatId, int $baseline, int $authorId, string $text): array
    {
        $result = ['status' => 'failed', 'messageId' => null, 'chatId' => $chatId, 'baselineMessageId' => $baseline, 'scanned' => 0, 'candidates' => []];
        try {
            $scan = $this->support->scan($chatId, null, $baseline, null, self::READBACK_BUDGET);
        } catch (Failure $failure) {
            if ($failure->exitStatus === 130) {
                throw $failure;
            }

            return [...$result, 'error' => $failure->errorCode];
        }

        $expected = $this->normalize($text);
        $candidates = [];
        foreach ($scan->data['items'] as $row) {
            if ($row['id'] > $baseline && (int) ($row['authorId'] ?? 0) === $authorId && is_string($row['text'] ?? null) && $this->normalize($row['text']) === $expected) {
                $candidates[] = $row['id'];
            }
        }

        $result['scanned'] = (int) ($scan->meta['scanned'] ?? 0);
        $result['candidates'] = $candidates;
        $result['status'] = match (true) {
            count($candidates) > 1 => 'ambiguous',
            ($scan->meta['complete'] ?? false) !== true => 'incomplete',
            count($candidates) === 1 => 'verified',
            default => 'missing',
        };
        if ($result['status'] === 'verified') {
            $result['messageId'] = $candidates[0];
        }

        return $result;
    }

    private function normalize(string $text): string
    {
        return trim(str_replace("\r\n", "\n", $text));
    }

    public function updateRoutes(): array
    {
        return [new Route('tasks.task.get', 3, 'read'), new Route('im.dialog.get', 1, 'read'), new Route('im.dialog.messages.get', 1, 'read'), new Route('im.message.update', 1, 'write')];
    }

    public function update(UpdateTaskChatMessageRequest $request): PreparedOperation
    {
        $chatId = $this->support->requireMessage($request->taskId, $request->messageId);
        return new PreparedOperation([Plan::step($request->taskId, 'im.message.update', 1, 'write', ['messageId' => $request->messageId, 'text' => $request->text])], function () use ($request, $chatId): OperationResult {
            $this->chat->update($request->messageId, $request->text);
            $result = OperationResult::mutation($request->taskId, $request->messageId, 'update', ['messageId' => $request->messageId, 'text' => $request->text]);
            $data = $result->data;
            $data['operation'] += ['chatId' => $chatId, 'messageId' => $request->messageId];
            return new OperationResult($result->profile, $data);
        }, 'chat message ' . $request->messageId . ' in task ' . $request->taskId);
    }

    public function deleteRoutes(): array
    {
        return [new Route('tasks.task.get', 3, 'read'), new Route('im.dialog.get', 1, 'read'), new Route('im.dialog.messages.get', 1, 'read'), new Route('im.message.delete', 1, 'delete')];
    }

    public function delete(DeleteTaskChatMessageRequest $request): PreparedOperation
    {
        $this->support->requireMessage($request->taskId, $request->messageId);
        return new PreparedOperation([Plan::step($request->taskId, 'im.message.delete', 1, 'delete', ['messageId' => $request->messageId])], function () use ($request): OperationResult {
            $this->chat->delete($request->messageId);
            return OperationResult::mutation($request->taskId, $request->messageId, 'delete', ['messageId' => $request->messageId]);
        }, 'chat message ' . $request->messageId . ' in task ' . $request->taskId);
    }
}
