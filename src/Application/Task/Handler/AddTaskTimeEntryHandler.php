<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\AddTaskTimeEntryRequest;
use Bitrix24\CLI\Application\Task\Plan;
use Bitrix24\CLI\Application\Task\Port\TimeEntryGateway;

final readonly class AddTaskTimeEntryHandler
{
    public function __construct(private TimeEntryGateway $entries)
    {
    }

    public function routes(): array
    {
        return [new Route('task.elapseditem.add', 1, 'write')];
    }

    public function prepare(AddTaskTimeEntryRequest $request): PreparedOperation
    {

        return new PreparedOperation([Plan::step($request->taskId, 'task.elapseditem.add', 1, 'write', ['seconds' => $request->seconds, ...($request->text === null ? [] : ['text' => $request->text])])], function () use ($request): OperationResult {
            $id = $this->entries->add($request->taskId, $request->seconds, $request->text);
            return OperationResult::mutation($request->taskId, $id, 'add', ['seconds' => $request->seconds, ...($request->text === null ? [] : ['text' => $request->text])]);
        }, 'task ' . $request->taskId);
    }
}
