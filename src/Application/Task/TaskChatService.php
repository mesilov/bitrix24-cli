<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Chat\CallEnricher;
use Bitrix24\CLI\Application\Chat\CallMessage;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Port\TaskChatGateway;
use Bitrix24\CLI\Application\Task\Request\DeleteTaskChatMessageRequest;
use Bitrix24\CLI\Application\Task\Request\ListTaskChatRequest;
use Bitrix24\CLI\Application\Task\Request\SendTaskChatMessageRequest;
use Bitrix24\CLI\Application\Task\Request\UpdateTaskChatMessageRequest;

final readonly class TaskChatService
{
    public function __construct(private ChatSupport $support, private TaskChatGateway $chat, private CallEnricher $calls)
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
        return [new Route('tasks.task.chat.message.send', 3, 'write')];
    }

    public function send(SendTaskChatMessageRequest $request): PreparedOperation
    {

        return new PreparedOperation([Plan::step($request->taskId, 'tasks.task.chat.message.send', 3, 'write', ['text' => $request->text])], function () use ($request): OperationResult {
            $this->chat->send($request->taskId, $request->text);
            return OperationResult::mutation($request->taskId, null, 'send', ['text' => $request->text]);
        }, 'task ' . $request->taskId);
    }

    public function updateRoutes(): array
    {
        return [new Route('tasks.task.get', 3, 'read'), new Route('im.dialog.get', 1, 'read'), new Route('im.dialog.messages.get', 1, 'read'), new Route('im.message.update', 1, 'write')];
    }

    public function update(UpdateTaskChatMessageRequest $request): PreparedOperation
    {
        $this->support->requireMessage($request->taskId, $request->messageId);
        return new PreparedOperation([Plan::step($request->taskId, 'im.message.update', 1, 'write', ['messageId' => $request->messageId, 'text' => $request->text])], function () use ($request): OperationResult {
            $this->chat->update($request->messageId, $request->text);
            return OperationResult::mutation($request->taskId, $request->messageId, 'update', ['messageId' => $request->messageId, 'text' => $request->text]);
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
