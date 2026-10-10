<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\SendTaskChatMessageRequest;
use Bitrix24\CLI\Application\Task\Plan;
use Bitrix24\CLI\Application\Task\Port\TaskChatGateway;

final readonly class SendTaskChatMessageHandler
{
    public function __construct(private TaskChatGateway $chat)
    {
    }

    public function routes(): array
    {
        return [new Route('tasks.task.chat.message.send', 3, 'write')];
    }

    public function prepare(SendTaskChatMessageRequest $request): PreparedOperation
    {

        return new PreparedOperation([Plan::step($request->taskId, 'tasks.task.chat.message.send', 3, 'write', ['text' => $request->text])], function () use ($request): OperationResult {
            $this->chat->send($request->taskId, $request->text);
            return OperationResult::mutation($request->taskId, null, 'send', ['text' => $request->text]);
        }, 'task ' . $request->taskId);
    }
}
