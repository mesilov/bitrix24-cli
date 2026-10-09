<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\DeleteTaskTimeEntryRequest;
use Bitrix24\CLI\Application\Task\Plan;
use Bitrix24\CLI\Application\Task\Port\TimeEntryGateway;

final readonly class DeleteTaskTimeEntryHandler
{
    public function __construct(private TimeEntryGateway $entries, private \Bitrix24\CLI\Application\Task\TimeSupport $support)
    {
    }

    public function routes(): array
    {
        return [new Route('task.elapseditem.getlist', 1, 'read'), new Route('task.elapseditem.delete', 1, 'delete')];
    }

    public function prepare(DeleteTaskTimeEntryRequest $request): PreparedOperation
    {
        $this->support->requireEntry($request->taskId, $request->entryId);
        return new PreparedOperation([Plan::step($request->taskId, 'task.elapseditem.delete', 1, 'delete', ['entryId' => $request->entryId])], function () use ($request): OperationResult {
            $this->entries->delete($request->taskId, $request->entryId);
            return OperationResult::mutation($request->taskId, $request->entryId, 'delete', ['entryId' => $request->entryId]);
        }, 'time entry ' . $request->entryId . ' in task ' . $request->taskId);
    }
}
