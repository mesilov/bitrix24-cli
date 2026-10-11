<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Port\ChecklistGateway;
use Bitrix24\CLI\Application\Task\Request\AddTaskChecklistItemRequest;
use Bitrix24\CLI\Application\Task\Request\ChecklistItemReference;
use Bitrix24\CLI\Application\Task\Request\ListTaskChecklistItemsRequest;
use Bitrix24\CLI\Application\Task\Request\UpdateTaskChecklistItemRequest;

final readonly class ChecklistItemService
{
    public function __construct(private ChecklistGateway $checklists, private ChecklistSupport $support)
    {
    }

    public function addRoutes(): array
    {
        return [new Route('task.checklistitem.getlist', 1, 'read'), new Route('task.checklistitem.add', 1, 'write')];
    }

    public function add(AddTaskChecklistItemRequest $request): PreparedOperation
    {
        $parent = $this->support->tree($request->taskId)->parent($request->checklistId, $request->parentId);
        return new PreparedOperation([Plan::step($request->taskId, 'task.checklistitem.add', 1, 'write', ['TITLE' => $request->title, 'PARENT_ID' => $parent])], function () use ($request, $parent): OperationResult {
            $id = $this->checklists->add($request->taskId, $parent, $request->title);
            return OperationResult::mutation($request->taskId, $id, 'add', ['title' => $request->title, 'parentId' => $parent]);
        }, 'item in checklist ' . $request->checklistId);
    }

    public function listRoutes(): array
    {
        return [new Route('task.checklistitem.getlist', 1, 'read')];
    }

    public function list(ListTaskChecklistItemsRequest $request): PreparedOperation
    {
        $tree = $this->support->tree($request->taskId);
        $tree->root($request->checklistId);
        return new PreparedOperation([], fn (): OperationResult => new OperationResult('checklist-items', ['items' => $tree->descendants($request->checklistId)], ['complete' => true]));
    }

    public function updateRoutes(): array
    {
        return [new Route('task.checklistitem.getlist', 1, 'read'), new Route('task.checklistitem.update', 1, 'write')];
    }

    public function update(UpdateTaskChecklistItemRequest $request): PreparedOperation
    {
        $tree = $this->support->tree($request->taskId);
        $tree->item($request->itemId);
        return new PreparedOperation([Plan::step($request->taskId, 'task.checklistitem.update', 1, 'write', ['itemId' => $request->itemId, 'title' => $request->title])], function () use ($request): OperationResult {
            $this->checklists->update($request->taskId, $request->itemId, $request->title);
            return OperationResult::mutation($request->taskId, $request->itemId, 'update', ['itemId' => $request->itemId, 'title' => $request->title]);
        }, 'checklist item ' . $request->itemId . ' in task ' . $request->taskId);
    }

    public function completeRoutes(): array
    {
        return [new Route('task.checklistitem.getlist', 1, 'read'), new Route('task.checklistitem.complete', 1, 'write')];
    }

    public function complete(ChecklistItemReference $request): PreparedOperation
    {
        $tree = $this->support->tree($request->taskId);
        $tree->item($request->itemId);
        return new PreparedOperation([Plan::step($request->taskId, 'task.checklistitem.complete', 1, 'write', ['itemId' => $request->itemId])], function () use ($request): OperationResult {
            $this->checklists->complete($request->taskId, $request->itemId);
            return OperationResult::mutation($request->taskId, $request->itemId, 'complete', ['itemId' => $request->itemId]);
        }, 'checklist item ' . $request->itemId . ' in task ' . $request->taskId);
    }

    public function renewRoutes(): array
    {
        return [new Route('task.checklistitem.getlist', 1, 'read'), new Route('task.checklistitem.renew', 1, 'write')];
    }

    public function renew(ChecklistItemReference $request): PreparedOperation
    {
        $tree = $this->support->tree($request->taskId);
        $tree->item($request->itemId);
        return new PreparedOperation([Plan::step($request->taskId, 'task.checklistitem.renew', 1, 'write', ['itemId' => $request->itemId])], function () use ($request): OperationResult {
            $this->checklists->renew($request->taskId, $request->itemId);
            return OperationResult::mutation($request->taskId, $request->itemId, 'renew', ['itemId' => $request->itemId]);
        }, 'checklist item ' . $request->itemId . ' in task ' . $request->taskId);
    }

    public function deleteRoutes(): array
    {
        return [new Route('task.checklistitem.getlist', 1, 'read'), new Route('task.checklistitem.delete', 1, 'delete')];
    }

    public function delete(ChecklistItemReference $request): PreparedOperation
    {
        $tree = $this->support->tree($request->taskId);
        $tree->item($request->itemId);

        $subtree = [$request->itemId, ...array_column($tree->descendants($request->itemId), 'id')];
        return new PreparedOperation([Plan::step($request->taskId, 'task.checklistitem.delete', 1, 'delete', ['itemId' => $request->itemId, 'subtreeIds' => $subtree])], function () use ($request, $subtree): OperationResult {
            $this->checklists->delete($request->taskId, $request->itemId);
            return OperationResult::mutation($request->taskId, $request->itemId, 'delete', ['itemId' => $request->itemId, 'subtreeIds' => $subtree]);
        }, 'checklist item ' . $request->itemId . ' in task ' . $request->taskId . ' (subtree: ' . implode(', ', $subtree) . ')');
    }
}
