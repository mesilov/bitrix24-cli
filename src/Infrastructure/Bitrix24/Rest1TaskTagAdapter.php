<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\Port\TaskTagGateway;

final readonly class Rest1TaskTagAdapter implements TaskTagGateway
{
    public function __construct(private ApiTransport $api)
    {
    }

    public function set(int $taskId, array $TAGS): void
    {
        $result = $this->api->call('tasks.task.update', 1, ['taskId' => $taskId, 'fields' => ['TAGS' => $TAGS]], 'write')->result;
        $id = $result['task']['id'] ?? null;
        if ((!is_int($id) && (!is_string($id) || !ctype_digit($id))) || (int) $id !== $taskId) {
            throw new Failure('api-error', 'Bitrix24 did not confirm tag changes for the selected task; inspect its tags before retrying.', 1, ['taskId' => $taskId], true);
        }
    }
}
