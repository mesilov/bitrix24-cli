<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\UpdateTaskTimeEntryRequest;
use Bitrix24\CLI\Application\Task\Plan;
use Bitrix24\CLI\Application\Task\Port\TimeEntryGateway;

final readonly class UpdateTaskTimeEntryHandler
{
    public function __construct(private TimeEntryGateway $entries, private \Bitrix24\CLI\Application\Task\TimeSupport $support)
    {
    }

    public function routes(): array
    {
        return [new Route('task.elapseditem.getlist', 1, 'read'), new Route('task.elapseditem.update', 1, 'write')];
    }

    public function prepare(UpdateTaskTimeEntryRequest $request): PreparedOperation
    {
        $this->support->requireEntry($request->taskId, $request->entryId);
        return new PreparedOperation([Plan::step($request->taskId, 'task.elapseditem.update', 1, 'write', ['entryId' => $request->entryId, 'seconds' => $request->seconds, ...($request->text === null ? [] : ['text' => $request->text])])], function () use ($request): OperationResult {
            $this->entries->update($request->taskId, $request->entryId, $request->seconds, $request->text);
            return OperationResult::mutation($request->taskId, $request->entryId, 'update', ['entryId' => $request->entryId, 'seconds' => $request->seconds, ...($request->text === null ? [] : ['text' => $request->text])]);
        }, 'time entry ' . $request->entryId . ' in task ' . $request->taskId);
    }
}
