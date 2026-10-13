<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Port\TaskResultGateway;
use Bitrix24\CLI\Application\Task\Request\AddTaskResultRequest;
use Bitrix24\CLI\Application\Task\Request\DeleteTaskResultRequest;
use Bitrix24\CLI\Application\Task\Request\ListTaskResultsRequest;
use Bitrix24\CLI\Application\Task\Request\UpdateTaskResultRequest;

final readonly class TaskResultService
{
    public function __construct(private TaskResultGateway $results)
    {
    }

    public function routes(string $action): array
    {
        $routes = [new Route('tasks.task.result.' . $action, 3, $action === 'list' ? 'read' : ($action === 'delete' ? 'delete' : 'write'))];
        if (in_array($action, ['update', 'delete'], true)) {
            array_unshift($routes, new Route('tasks.task.result.list', 3, 'read'));
        }

        return $routes;
    }

    public function add(AddTaskResultRequest $request): PreparedOperation
    {
        return new PreparedOperation([Plan::step($request->taskId, 'tasks.task.result.add', 3, 'write', ['text' => $request->text])], function () use ($request): OperationResult {
            $id = $this->results->add($request->taskId, $request->text);
            return OperationResult::mutation($request->taskId, $id, 'add', ['text' => $request->text]);
        }, 'task ' . $request->taskId);
    }

    public function list(ListTaskResultsRequest $request): PreparedOperation
    {
        return new PreparedOperation([], fn (): OperationResult => $this->scan($request));
    }

    private function scan(ListTaskResultsRequest $request): OperationResult
    {
        $offset = 0;
        $scanned = 0;
        $items = [];
        $seen = [];
        $reason = null;
        do {
            try {
                $page = $this->results->page($request->taskId, $offset, min(50, $request->maxScan - $scanned));
            } catch (Failure $failure) {
                if ($scanned === 0 || $failure->exitStatus === 130) {
                    throw $failure;
                }

                $reason = 'page-error';
                break;
            }

            $progress = 0;
            foreach ($page->items as $item) {
                $scanned++;
                if (!isset($seen[$item['id']])) {
                    $seen[$item['id']] = true;
                    $items[] = $item;
                    $progress++;
                }
            }

            if ($page->next !== null && ($progress === 0 || !is_int($page->next) || $page->next <= $offset)) {
                $reason = 'pagination-no-progress';
                break;
            }

            if ($page->next !== null && $scanned >= $request->maxScan) {
                $reason = 'scan-budget-exhausted';
                break;
            }

            $offset = $page->next ?? 0;
        } while ($page->next !== null);

        usort($items, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);
        $matched = count($items);
        $items = $request->limit === null ? $items : array_slice($items, 0, $request->limit);
        return new OperationResult('task-results', ['items' => $items], [
            'scope' => 'visible-task-results', 'taskId' => $request->taskId, 'complete' => $reason === null,
            'scanned' => $scanned, 'matched' => $matched, 'returned' => count($items),
            'limitApplied' => count($items) < $matched, 'reason' => $reason,
        ], $reason === null ? 0 : 3);
    }

    private function requireResult(int $taskId, int $id): void
    {
        $page = $this->results->page($taskId, 0, 2, $id);
        if (!$page->complete || $page->next !== null || count($page->items) !== 1 || $page->items[0]['id'] !== $id || $page->items[0]['taskId'] !== $taskId) {
            throw Failure::binding('The result could not be verified in the selected task; use task:result:list to select its ID.');
        }
    }

    public function update(UpdateTaskResultRequest $request): PreparedOperation
    {
        $this->requireResult($request->taskId, $request->id);
        return new PreparedOperation([Plan::step($request->taskId, 'tasks.task.result.update', 3, 'write', ['id' => $request->id, 'text' => $request->text])], function () use ($request): OperationResult {
            $this->results->update($request->taskId, $request->id, $request->text);
            return OperationResult::mutation($request->taskId, $request->id, 'update', ['text' => $request->text]);
        }, 'result ' . $request->id . ' in task ' . $request->taskId);
    }

    public function delete(DeleteTaskResultRequest $request): PreparedOperation
    {
        $this->requireResult($request->taskId, $request->id);
        return new PreparedOperation([Plan::step($request->taskId, 'tasks.task.result.delete', 3, 'delete', ['id' => $request->id])], function () use ($request): OperationResult {
            $this->results->delete($request->id);
            return OperationResult::mutation($request->taskId, $request->id, 'delete');
        }, 'result ' . $request->id . ' in task ' . $request->taskId);
    }
}
