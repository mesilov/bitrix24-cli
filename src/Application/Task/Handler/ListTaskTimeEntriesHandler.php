<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\ListTaskTimeEntriesRequest;

final readonly class ListTaskTimeEntriesHandler
{
    public function __construct(private \Bitrix24\CLI\Application\Task\TimeSupport $support)
    {
    }

    public function routes(): array
    {
        return [new Route('task.elapseditem.getlist', 1, 'read')];
    }

    public function prepare(ListTaskTimeEntriesRequest $request): PreparedOperation
    {
        return new PreparedOperation([], fn (): OperationResult => $this->support->scan($request->taskId, $request->query, $request->singlePage));
    }
}
