<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\ShowTaskRequest;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;

final readonly class ShowTaskHandler
{
    public function __construct(private TaskGateway $tasks)
    {
    }

    public function routes(): array
    {
        return [new Route('tasks.task.get', 3, 'read')];
    }

    public function prepare(ShowTaskRequest $request): PreparedOperation
    {
        return new PreparedOperation([], fn (): OperationResult => new OperationResult('task-card', ['task' => $this->tasks->get($request->taskId, $request->select)]));
    }
}
