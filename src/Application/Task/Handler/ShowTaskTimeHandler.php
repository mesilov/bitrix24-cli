<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\ShowTaskTimeRequest;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;

final readonly class ShowTaskTimeHandler
{
    public function __construct(private TaskGateway $tasks)
    {
    }

    public function routes(): array
    {
        return [new Route('tasks.task.get', 3, 'read')];
    }

    public function prepare(ShowTaskTimeRequest $request): PreparedOperation
    {
        return new PreparedOperation([], function () use ($request): OperationResult {
            $task = $this->tasks->get($request->taskId, ['elapsedTime']);
            $present = array_key_exists('elapsedTime', $task);
            return new OperationResult('time-context', ['time' => ['taskId' => $request->taskId, 'elapsedTime' => $task['elapsedTime'] ?? null]], ['scope' => 'task-elapsedTime-relation', 'historyComplete' => null, 'complete' => $present, 'reason' => $present ? null : 'Selected elapsedTime was not returned.'], $present ? 0 : 3);
        });
    }
}
