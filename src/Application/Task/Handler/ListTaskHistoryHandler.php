<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\ListTaskHistoryRequest;

final readonly class ListTaskHistoryHandler
{
    public function __construct(private \Bitrix24\CLI\Application\Task\HistoryScanner $scanner)
    {
    }

    public function routes(): array
    {
        return [new Route('tasks.task.history.list', 1, 'read')];
    }

    public function prepare(ListTaskHistoryRequest $request): PreparedOperation
    {
        return new PreparedOperation([], fn (): OperationResult => $this->scanner->scan($request->taskId, $request->query));
    }
}
