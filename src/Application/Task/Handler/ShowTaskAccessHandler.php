<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\ShowTaskAccessRequest;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;

final readonly class ShowTaskAccessHandler
{
    public function __construct(private TaskGateway $tasks)
    {
    }

    public function routes(): array
    {
        return [new Route('tasks.task.access.get', 3, 'read')];
    }

    public function prepare(ShowTaskAccessRequest $request): PreparedOperation
    {
        return new PreparedOperation([], fn (): OperationResult => new OperationResult('task-access', ['access' => $this->tasks->access($request->taskId)]));
    }
}
