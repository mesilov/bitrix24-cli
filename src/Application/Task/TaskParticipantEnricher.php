<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\Task\Port\ParticipantGateway;
use Bitrix24\CLI\Application\Task\Port\UserGateway;

final readonly class TaskParticipantEnricher
{
    public function __construct(private ParticipantGateway $participants, private UserGateway $users)
    {
    }

    public static function projections(array $select): array
    {
        $roles = [];
        foreach ($select as $path) {
            [$root, $field] = array_pad(explode('.', $path), 2, null);
            if (!in_array($root, ['auditors', 'accomplices'], true)) {
                continue;
            }

            if (!in_array($field, ['id', 'name', 'email', 'gender', 'externalAuthId'], true)) {
                throw Failure::usage('Participant enrichment supports id/name/email/gender/externalAuthId projections.');
            }

            $roles[$root] = array_values(array_unique([...($roles[$root] ?? ['id']), $field]));
        }

        return $roles;
    }

    public function enrich(int $taskId, array $task, array $roles): OperationResult
    {
        if ($roles === []) {
            return new OperationResult('task-card', ['task' => $task]);
        }

        foreach ($roles as $role => $fields) {
            $task[$role] = null;
        }

        try {
            $members = $this->participants->ids($taskId, array_keys($roles));
        } catch (Failure $failure) {
            if ($failure->exitStatus === 130) {
                throw $failure;
            }

            return new OperationResult('task-card', ['task' => $task], ['complete' => false, 'reason' => 'Task participant IDs could not be read.', 'participantEnrichment' => ['errorCode' => $failure->errorCode]], 3);
        }

        $ids = array_values(array_unique(array_merge(...array_values($members))));
        $fields = array_values(array_diff(array_unique(array_merge(...array_values($roles))), ['id']));
        $users = [];
        $errorCode = null;
        if ($ids !== [] && $fields !== []) {
            try {
                $users = $this->users->byIds($ids, $fields);
            } catch (Failure $failure) {
                if ($failure->exitStatus === 130) {
                    throw $failure;
                }

                $errorCode = $failure->errorCode;
            }
        }

        $missingIds = [];
        $missingFields = [];
        foreach ($roles as $role => $projections) {
            $task[$role] = [];
            foreach ($members[$role] as $id) {
                $user = ['id' => $id];
                foreach (array_diff($projections, ['id']) as $field) {
                    $user[$field] = $users[$id][$field] ?? null;
                    if (!array_key_exists($field, $users[$id] ?? [])) {
                        $missingFields[$id][] = $field;
                    }

                    if (!isset($users[$id])) {
                        $missingIds[] = $id;
                    }
                }

                $task[$role][] = $user;
            }
        }

        $complete = $missingFields === [] && $errorCode === null;
        return new OperationResult('task-card', ['task' => $task], [
            'complete' => $complete,
            'reason' => $complete ? null : 'Some participant users or selected fields could not be read; known IDs are retained.',
            'participantEnrichment' => ['roles' => array_keys($roles), 'missingUserIds' => array_values(array_unique($missingIds)), 'missingFields' => array_map(static fn ($fields): array => array_values(array_unique($fields)), $missingFields), 'errorCode' => $errorCode],
        ], $complete ? 0 : 3);
    }
}
