<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\ListTasksRequest;

final readonly class ListTasksHandler
{
    public function __construct(private \Bitrix24\CLI\Application\Task\TaskListScanner $scanner)
    {
    }

    public function routes(ListTasksRequest $request): array
    {
        return [new Route('tasks.task.list', $request->query->deadlineWindow instanceof \Bitrix24\CLI\Application\Task\DeadlineWindow ? 1 : 3, 'read'), ...(!$request->query->deadlineWindow instanceof \Bitrix24\CLI\Application\Task\DeadlineWindow && array_key_exists('groupId', $request->query->where) ? [new Route('tasks.task.get', 3, 'read')] : []), ...($request->query->role?->participantField() === null ? [] : [new Route('tasks.task.get', 1, 'read')])];
    }

    public function prepare(ListTasksRequest $request): PreparedOperation
    {
        return new PreparedOperation([], fn (): OperationResult => $this->scanner->scan($request->query));
    }
}
