<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\UpdateTaskChatMessageRequest;
use Bitrix24\CLI\Application\Task\Plan;
use Bitrix24\CLI\Application\Task\Port\TaskChatGateway;

final readonly class UpdateTaskChatMessageHandler
{
    public function __construct(private TaskChatGateway $chat, private \Bitrix24\CLI\Application\Task\ChatSupport $support)
    {
    }

    public function routes(): array
    {
        return [new Route('tasks.task.get', 3, 'read'), new Route('im.dialog.messages.get', 1, 'read'), new Route('im.message.update', 1, 'write')];
    }

    public function prepare(UpdateTaskChatMessageRequest $request): PreparedOperation
    {
        $this->support->requireMessage($request->taskId, $request->messageId);
        return new PreparedOperation([Plan::step($request->taskId, 'im.message.update', 1, 'write', ['messageId' => $request->messageId, 'text' => $request->text])], function () use ($request): OperationResult {
            $this->chat->update($request->messageId, $request->text);
            return OperationResult::mutation($request->taskId, $request->messageId, 'update', ['messageId' => $request->messageId, 'text' => $request->text]);
        }, 'chat message ' . $request->messageId . ' in task ' . $request->taskId);
    }
}
