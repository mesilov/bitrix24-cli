<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\ListTaskChatRequest;

final readonly class ListTaskChatHandler
{
    public function __construct(private \Bitrix24\CLI\Application\Task\ChatSupport $support)
    {
    }

    public function routes(): array
    {
        return [new Route('tasks.task.get', 3, 'read'), new Route('im.dialog.messages.get', 1, 'read')];
    }

    public function prepare(ListTaskChatRequest $request): PreparedOperation
    {
        $chatId = $this->support->chatId($request->taskId);
        return new PreparedOperation([], fn (): OperationResult => $this->support->scan($chatId, $request->before, $request->after, $request->limit, $request->maxMessages));
    }
}
