<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;
use Bitrix24\CLI\Application\Task\Request\ListTaskFieldsRequest;
use Bitrix24\CLI\Application\Task\Request\TaskReference;

final readonly class TaskMetadataService
{
    public function __construct(private TaskGateway $tasks)
    {
    }

    public function fieldsRoutes(ListTaskFieldsRequest $request): array
    {
        return [new Route($request->name === null ? 'tasks.task.field.list' : 'tasks.task.field.get', 3)];
    }

    public function fields(ListTaskFieldsRequest $request): PreparedOperation
    {
        return new PreparedOperation([], fn (): OperationResult => new OperationResult('task-fields', ['items' => $this->tasks->fields($request->name)], ['complete' => true]));
    }

    public function accessRoutes(): array
    {
        return [new Route('tasks.task.access.get', 3, 'read')];
    }

    public function access(TaskReference $request): PreparedOperation
    {
        return new PreparedOperation([], fn (): OperationResult => new OperationResult('task-access', ['access' => $this->tasks->access($request->taskId)]));
    }

    public function timeRoutes(): array
    {
        return [new Route('tasks.task.get', 3, 'read')];
    }

    public function time(TaskReference $request): PreparedOperation
    {
        return new PreparedOperation([], function () use ($request): OperationResult {
            $task = $this->tasks->get($request->taskId, ['elapsedTime']);
            $present = array_key_exists('elapsedTime', $task);
            return new OperationResult('time-context', ['time' => ['taskId' => $request->taskId, 'elapsedTime' => $task['elapsedTime'] ?? null]], ['scope' => 'task-elapsedTime-relation', 'historyComplete' => null, 'complete' => $present, 'reason' => $present ? null : 'Selected elapsedTime was not returned.'], $present ? 0 : 3);
        });
    }
}
