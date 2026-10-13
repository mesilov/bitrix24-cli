<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\LifecycleAction;
use Bitrix24\CLI\Application\Task\Port\TaskLifecycleGateway;

final readonly class Rest1TaskLifecycleAdapter implements TaskLifecycleGateway
{
    public function __construct(private ApiTransport $api)
    {
    }

    public function transition(int $taskId, LifecycleAction $action): void
    {
        $result = $this->api->call($action->method(), 1, ['taskId' => $taskId], 'write')->result;
        $task = $result['task'] ?? null;
        $id = is_array($task) ? ($task['id'] ?? null) : null;
        if ((!is_int($id) && (!is_string($id) || !ctype_digit($id))) || (int) $id !== $taskId || (string) (int) $id !== ltrim((string) $id, '0')) {
            throw new Failure('api-error', 'The lifecycle response did not confirm the selected task; inspect it with task:show ' . $taskId . '.', 1, [], true);
        }
    }

    public function hasNativeResult(int $taskId): bool
    {
        $items = $this->api->call('tasks.task.result.list', 3, [
            'filter' => [['taskId', $taskId]], 'select' => ['id', 'taskId', 'status'],
            'order' => (object) ['id' => 'ASC'], 'pagination' => ['offset' => 0, 'limit' => 1],
        ])->result['items'] ?? null;
        if (!is_array($items) || !array_is_list($items) || count($items) > 1) {
            throw new Failure('required-result-unverified', 'The native result response has an unsupported shape.', 4);
        }

        foreach ($items as $item) {
            if (!is_array($item) || !is_int($item['id'] ?? null) || $item['id'] < 1 || ($item['taskId'] ?? null) !== $taskId || !in_array($item['status'] ?? null, ['open', 'closed'], true)) {
                throw new Failure('required-result-unverified', 'A native result belonging to this task was not verified.', 4);
            }
        }

        return $items !== [];
    }
}
