<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\Page;
use Bitrix24\CLI\Application\Task\Port\TaskHistoryGateway;

final readonly class Rest1TaskHistoryAdapter implements TaskHistoryGateway
{
    public function __construct(private ApiTransport $api)
    {
    }

    public function page(int $taskId, array $query, int $offset): Page
    {
        $result = $this->api->call('tasks.task.history.list', 1, ['taskId' => $taskId, 'start' => $offset, ...$query]);
        $rows = $result->result['list'] ?? null;
        if (!is_array($rows)) {
            throw new Failure('api-error', 'The task history response has an unsupported shape.');
        }

        $items = array_map(static fn (array $row): array => [
            'id' => isset($row['id']) ? (int) $row['id'] : null, 'createdDate' => $row['createdDate'] ?? null,
            'userId' => $row['user']['id'] ?? null, 'field' => $row['field'] ?? null,
            'from' => $row['value']['from'] ?? null, 'to' => $row['value']['to'] ?? null,
        ], $rows);
        // Empty response proves exhaustion; a nonempty page without navigation does not.
        return new Page($items, $result->next, $rows === [] || $result->next !== null || ($result->total !== null && $offset + count($rows) >= $result->total));
    }
}
