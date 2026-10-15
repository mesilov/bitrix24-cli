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
        if (!is_array($rows) || !array_is_list($rows) || array_any($rows, static fn (mixed $row): bool => !is_array($row))) {
            throw new Failure('api-error', 'The task history response has an unsupported shape.');
        }

        $items = array_map(static fn (array $row): array => [
            'id' => isset($row['id']) ? (int) $row['id'] : null, 'createdDate' => $row['createdDate'] ?? null,
            'userId' => $row['user']['id'] ?? null, 'field' => $row['field'] ?? null,
            'from' => $row['value']['from'] ?? null, 'to' => $row['value']['to'] ?? null,
        ], $rows);
        // REST1: absent next marks the terminal page; total is optional, never row-count guessing.
        $returned = $offset + count($rows);
        $consistent = $result->total === null || ($result->next === null ? $returned === $result->total : $returned < $result->total);
        return new Page($items, $result->next, $consistent);
    }
}
