<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\FindTasksRequest;

final readonly class FindTasksHandler
{
    public function __construct(private \Bitrix24\CLI\Application\Task\TaskListScanner $scanner)
    {
    }

    public function routes(): array
    {
        return [new Route('tasks.task.list', 3, 'read')];
    }

    public function prepare(FindTasksRequest $request): PreparedOperation
    {
        return new PreparedOperation([], fn (): OperationResult => $this->scanner->scan($request->query, $request->title));
    }
}
