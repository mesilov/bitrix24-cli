<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\ListTaskFieldsRequest;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;

final readonly class ListTaskFieldsHandler
{
    public function __construct(private TaskGateway $tasks)
    {
    }

    public function routes(ListTaskFieldsRequest $request): array
    {
        return [new Route($request->name === null ? 'tasks.task.field.list' : 'tasks.task.field.get', 3)];
    }

    public function prepare(ListTaskFieldsRequest $request): PreparedOperation
    {
        return new PreparedOperation([], fn (): OperationResult => new OperationResult('task-fields', ['items' => $this->tasks->fields($request->name)], ['complete' => true]));
    }
}
