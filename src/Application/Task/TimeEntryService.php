<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Port\TimeEntryGateway;
use Bitrix24\CLI\Application\Task\Request\AddTaskTimeEntryRequest;
use Bitrix24\CLI\Application\Task\Request\DeleteTaskTimeEntryRequest;
use Bitrix24\CLI\Application\Task\Request\ListTaskTimeEntriesRequest;
use Bitrix24\CLI\Application\Task\Request\UpdateTaskTimeEntryRequest;

final readonly class TimeEntryService
{
    public function __construct(private TimeEntryGateway $entries, private TimeSupport $support)
    {
    }

    public function addRoutes(): array
    {
        return [new Route('task.elapseditem.add', 1, 'write')];
    }

    public function add(AddTaskTimeEntryRequest $request): PreparedOperation
    {

        return new PreparedOperation([Plan::step($request->taskId, 'task.elapseditem.add', 1, 'write', ['seconds' => $request->seconds, ...($request->text === null ? [] : ['text' => $request->text])])], function () use ($request): OperationResult {
            $id = $this->entries->add($request->taskId, $request->seconds, $request->text);
            return OperationResult::mutation($request->taskId, $id, 'add', ['seconds' => $request->seconds, ...($request->text === null ? [] : ['text' => $request->text])]);
        }, 'task ' . $request->taskId);
    }

    public function listRoutes(): array
    {
        return [new Route('task.elapseditem.getlist', 1, 'read')];
    }

    public function list(ListTaskTimeEntriesRequest $request): PreparedOperation
    {
        return new PreparedOperation([], fn (): OperationResult => $this->support->scan($request->taskId, $request->query, $request->singlePage));
    }

    public function updateRoutes(): array
    {
        return [new Route('task.elapseditem.getlist', 1, 'read'), new Route('task.elapseditem.update', 1, 'write')];
    }

    public function update(UpdateTaskTimeEntryRequest $request): PreparedOperation
    {
        $this->support->requireEntry($request->taskId, $request->entryId);
        return new PreparedOperation([Plan::step($request->taskId, 'task.elapseditem.update', 1, 'write', ['entryId' => $request->entryId, 'seconds' => $request->seconds, ...($request->text === null ? [] : ['text' => $request->text])])], function () use ($request): OperationResult {
            $this->entries->update($request->taskId, $request->entryId, $request->seconds, $request->text);
            return OperationResult::mutation($request->taskId, $request->entryId, 'update', ['entryId' => $request->entryId, 'seconds' => $request->seconds, ...($request->text === null ? [] : ['text' => $request->text])]);
        }, 'time entry ' . $request->entryId . ' in task ' . $request->taskId);
    }

    public function deleteRoutes(): array
    {
        return [new Route('task.elapseditem.getlist', 1, 'read'), new Route('task.elapseditem.delete', 1, 'delete')];
    }

    public function delete(DeleteTaskTimeEntryRequest $request): PreparedOperation
    {
        $this->support->requireEntry($request->taskId, $request->entryId);
        return new PreparedOperation([Plan::step($request->taskId, 'task.elapseditem.delete', 1, 'delete', ['entryId' => $request->entryId])], function () use ($request): OperationResult {
            $this->entries->delete($request->taskId, $request->entryId);
            return OperationResult::mutation($request->taskId, $request->entryId, 'delete', ['entryId' => $request->entryId]);
        }, 'time entry ' . $request->entryId . ' in task ' . $request->taskId);
    }
}
