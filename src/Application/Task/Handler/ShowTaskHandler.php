<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\ShowTaskRequest;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;
use Bitrix24\CLI\Application\Task\TaskParticipantEnricher;

final readonly class ShowTaskHandler
{
    public function __construct(private TaskGateway $tasks, private TaskParticipantEnricher $enricher)
    {
    }

    public function routes(ShowTaskRequest $request): array
    {
        $routes = [new Route('tasks.task.get', 3, 'read')];
        $roles = TaskParticipantEnricher::projections($request->select);
        if ($roles !== []) {
            $routes[] = new Route('tasks.task.get', 1, 'read');
            if (array_diff(array_merge(...array_values($roles)), ['id']) !== []) {
                $routes[] = new Route('user.get', 1, 'read');
            }
        }

        return $routes;
    }

    public function prepare(ShowTaskRequest $request): PreparedOperation
    {
        $roles = TaskParticipantEnricher::projections($request->select);
        $select = array_values(array_filter($request->select, static fn ($field): bool => !array_key_exists(explode('.', $field)[0], $roles)));
        return new PreparedOperation([], fn (): OperationResult => $this->enricher->enrich($request->taskId, $this->tasks->get($request->taskId, $select ?: ['id']), $roles));
    }
}
