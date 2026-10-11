<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\AttachTaskFilesRequest;
use Bitrix24\CLI\Application\Task\Plan;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;

final readonly class AttachTaskFilesHandler
{
    public function __construct(private TaskGateway $tasks)
    {
    }

    public function routes(): array
    {
        return [new Route('tasks.task.file.attach', 3, 'write')];
    }

    public function prepare(AttachTaskFilesRequest $request): PreparedOperation
    {
        $steps = [];
        foreach ($request->fileIds as $index => $id) {
            $steps[] = Plan::step($request->taskId, 'tasks.task.file.attach', 3, 'write', ['fileIds' => [$id]], $index + 1);
        }

        return new PreparedOperation($steps, function () use ($request): OperationResult {
            $executionLedger = new \Bitrix24\CLI\Application\ExecutionLedger();
            foreach ($request->fileIds as $index => $id) {
                try {
                    $this->tasks->attach($request->taskId, $id);
                    $executionLedger->confirm(['taskId' => $request->taskId, 'resourceId' => $id, 'action' => 'attach', 'changedFields' => ['fileIds'], 'confirmed' => true]);
                } catch (Failure $failure) {
                    return $executionLedger->stop($id, array_slice($request->fileIds, $index + 1), $failure);
                }
            }

            return $executionLedger->result();
        }, 'files in task ' . $request->taskId);
    }
}
