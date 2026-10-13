<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\{Clock, Failure, OperationResult, PreparedOperation, Route};
use Bitrix24\CLI\Application\Task\{HistoryScanner, StatusDurationCalculator};
use Bitrix24\CLI\Application\Task\Port\TaskGateway;
use Bitrix24\CLI\Application\Task\Request\StatusDurationsTaskRequest;

final readonly class StatusDurationsTaskHandler
{
    public function __construct(private HistoryScanner $scanner, private TaskGateway $tasks, private StatusDurationCalculator $calculator, private Clock $clock)
    {
    }

    public function routes(): array
    {
        return [new Route('tasks.task.history.list', 1, 'read'), new Route('tasks.task.get', 3, 'read')];
    }

    public function prepare(StatusDurationsTaskRequest $request): PreparedOperation
    {
        return new PreparedOperation([], function () use ($request): OperationResult {
            $select = ['status', 'statusChanged', 'changed'];
            $before = $this->tasks->get($request->taskId, $select);
            $history = $this->scanner->scan($request->taskId, ['order' => ['createdDate' => 'ASC']], true);
            $reasons = $history->meta['reasons'];
            try {
                $after = $this->tasks->get($request->taskId, $select);
            } catch (Failure $failure) {
                if ($failure->exitStatus === 130 || $history->data['evidence'] === []) {
                    throw $failure;
                }

                $after = [];
                $reasons[] = 'snapshot-read-error';
            }

            $result = $this->calculator->calculate($request->taskId, $history->data['evidence'], $history->meta['complete'], $reasons, $before, $after, $request->asOf);
            return new OperationResult($result->profile, $result->data, [...$result->meta, 'collectedAt' => $this->clock->now()->format('Y-m-d\TH:i:s.uP'), 'snapshots' => ['before' => $before, 'after' => $after], 'snapshotAtomic' => false], $result->exitStatus);
        });
    }
}
