<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\ListTaskChecklistItemsRequest;

final readonly class ListTaskChecklistItemsHandler
{
    public function __construct(private \Bitrix24\CLI\Application\Task\ChecklistSupport $support)
    {
    }

    public function routes(): array
    {
        return [new Route('task.checklistitem.getlist', 1, 'read')];
    }

    public function prepare(ListTaskChecklistItemsRequest $request): PreparedOperation
    {
        $tree = $this->support->tree($request->taskId);
        $tree->root($request->checklistId);
        return new PreparedOperation([], fn (): OperationResult => new OperationResult('checklist-items', ['items' => $tree->descendants($request->checklistId)], ['complete' => true]));
    }
}
