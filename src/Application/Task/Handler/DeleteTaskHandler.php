<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\DeleteTaskRequest;
use Bitrix24\CLI\Application\Task\Plan;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;

final readonly class DeleteTaskHandler
{
    public function __construct(private TaskGateway $tasks)
    {
    }

    public function routes(): array
    {
        return [new Route('tasks.task.get', 3, 'read'), new Route('tasks.task.delete', 3, 'delete')];
    }

    public function prepare(DeleteTaskRequest $request): PreparedOperation
    {
        $task = $this->tasks->get($request->taskId, ['title']);
        return new PreparedOperation([Plan::step($request->taskId, 'tasks.task.delete', 3, 'delete', [])], function () use ($request): OperationResult {
            $this->tasks->delete($request->taskId);
            return OperationResult::mutation($request->taskId, $request->taskId, 'delete', []);
        }, 'task ' . $request->taskId . ': ' . ($task['title'] ?? ''));
    }
}
