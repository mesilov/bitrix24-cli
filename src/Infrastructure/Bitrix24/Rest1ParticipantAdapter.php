<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\Port\ParticipantGateway;

final readonly class Rest1ParticipantAdapter implements ParticipantGateway
{
    public function __construct(private ApiTransport $api)
    {
    }

    public function ids(int $taskId, array $roles): array
    {
        $task = $this->api->call('tasks.task.get', 1, ['taskId' => $taskId, 'select' => ['ID', ...array_map(strtoupper(...), $roles)]])->result['task'] ?? null;
        if (!is_array($task) || $this->id($task['id'] ?? null) !== $taskId) {
            throw new Failure('api-error', 'Bitrix24 did not return participant IDs for the selected task.');
        }

        $result = [];
        foreach ($roles as $role) {
            $ids = $task[$role] ?? null;
            if (!is_array($ids) || !array_is_list($ids)) {
                throw new Failure('api-error', 'The participant ID list has an unsupported shape.');
            }

            $result[$role] = array_values(array_unique(array_map($this->id(...), $ids)));
        }

        return $result;
    }

    private function id(mixed $value): int
    {
        if ((!is_int($value) && (!is_string($value) || !ctype_digit($value))) || filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1) {
            throw new Failure('api-error', 'The participant response contains an invalid ID.');
        }

        return (int) $value;
    }

    public function set(int $taskId, ?array $accomplices, ?array $auditors): void
    {
        $fields = [];
        if ($accomplices !== null) {
            $fields['ACCOMPLICES'] = $accomplices;
        }

        if ($auditors !== null) {
            $fields['AUDITORS'] = $auditors;
        }

        $result = $this->api->call('tasks.task.update', 1, ['taskId' => $taskId, 'fields' => $fields], 'write')->result;
        $id = $result['task']['id'] ?? null;
        if ((!is_int($id) && (!is_string($id) || !ctype_digit($id))) || (int) $id !== $taskId) {
            throw new Failure('api-error', 'Bitrix24 did not return the updated task.');
        }
    }
}
