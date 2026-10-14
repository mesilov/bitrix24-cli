<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\CustomTaskFields;
use Bitrix24\CLI\Application\Task\LegacyTaskFields;
use Bitrix24\CLI\Application\Task\Port\TaskUserFieldGateway;

final readonly class Rest1TaskUserFieldAdapter implements TaskUserFieldGateway
{
    public function __construct(private ApiTransport $api)
    {
    }

    public function fields(): array
    {
        $fields = $this->api->call('tasks.task.getFields', 1, [])->result['fields'] ?? null;
        if (!is_array($fields)) {
            throw new Failure('api-error', 'Unsupported custom field schema response.');
        }

        return $fields;
    }

    public function add(array $ordinary, array $custom): int
    {
        LegacyTaskFields::validate($ordinary);
        CustomTaskFields::input($custom);
        $result = $this->api->call('tasks.task.add', 1, ['fields' => [...$ordinary, ...$custom]], 'write')->result;
        $id = $result['task']['id'] ?? null;
        if ((!is_int($id) && (!is_string($id) || !ctype_digit($id))) || (int) $id < 1) {
            throw new Failure('api-error', 'Custom task creation was not confirmed; inspect the portal before retrying.', 1, [], true);
        }

        return (int) $id;
    }

    public function update(int $taskId, array $custom): void
    {
        CustomTaskFields::input($custom);
        $result = $this->api->call('tasks.task.update', 1, ['taskId' => $taskId, 'fields' => $custom], 'write')->result;
        $id = $result['task']['id'] ?? null;
        if ((!is_int($id) && (!is_string($id) || !ctype_digit($id))) || (int) $id !== $taskId) {
            throw new Failure('api-error', 'Custom changes were not confirmed for the selected task; inspect it before retrying.', 1, ['taskId' => $taskId], true);
        }
    }
}
