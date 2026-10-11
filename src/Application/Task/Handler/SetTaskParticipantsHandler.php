<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Request\SetTaskParticipantsRequest;
use Bitrix24\CLI\Application\Task\Plan;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;
use Bitrix24\CLI\Application\Task\Port\ParticipantGateway;

final readonly class SetTaskParticipantsHandler
{
    public function __construct(private ParticipantGateway $participants, private TaskGateway $tasks)
    {
    }

    public function routes(): array
    {
        return [new Route('tasks.task.get', 3, 'read'), new Route('tasks.task.update', 1, 'write')];
    }

    public function prepare(SetTaskParticipantsRequest $request): PreparedOperation
    {
        $task = $this->tasks->get($request->taskId, ['title']);
        $fields = [];
        if ($request->accomplices !== null) {
            $fields['ACCOMPLICES'] = $request->accomplices;
        }

        if ($request->auditors !== null) {
            $fields['AUDITORS'] = $request->auditors;
        }

        return new PreparedOperation([Plan::step($request->taskId, 'tasks.task.update', 1, 'write', $fields)], function () use ($request, $fields): OperationResult {
            $this->participants->set($request->taskId, $request->accomplices, $request->auditors);
            return OperationResult::mutation($request->taskId, $request->taskId, 'participants:set', $fields);
        }, 'participants of task ' . $request->taskId . ': ' . ($task['title'] ?? ''));
    }
}
