<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\DeleteTaskChatMessageRequest;
use Bitrix24\CLI\Application\Task\Plan;
use Bitrix24\CLI\Application\Task\Port\TaskChatGateway;

final readonly class DeleteTaskChatMessageHandler
{
    public function __construct(private TaskChatGateway $chat, private \Bitrix24\CLI\Application\Task\ChatSupport $support)
    {
    }

    public function routes(): array
    {
        return [new Route('tasks.task.get', 3, 'read'), new Route('im.dialog.messages.get', 1, 'read'), new Route('im.message.delete', 1, 'delete')];
    }

    public function prepare(DeleteTaskChatMessageRequest $request): PreparedOperation
    {
        $this->support->requireMessage($request->taskId, $request->messageId);
        return new PreparedOperation([Plan::step($request->taskId, 'im.message.delete', 1, 'delete', ['messageId' => $request->messageId])], function () use ($request): OperationResult {
            $this->chat->delete($request->messageId);
            return OperationResult::mutation($request->taskId, $request->messageId, 'delete', ['messageId' => $request->messageId]);
        }, 'chat message ' . $request->messageId . ' in task ' . $request->taskId);
    }
}
