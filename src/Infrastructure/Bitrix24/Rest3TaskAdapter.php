<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\Page;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;

final readonly class Rest3TaskAdapter implements TaskGateway
{
    public function __construct(private ApiTransport $api)
    {
    }

    public function add(array $fields): int
    {
        return ResponseNormalizer::id($this->api->call('tasks.task.add', 3, ['fields' => $fields], 'write')->result);
    }

    public function get(int $id, array $select = []): array
    {
        $params = ['id' => $id];
        if ($select !== []) {
            $params['select'] = array_values(array_unique(['id', ...$select]));
        }

        $item = $this->api->call('tasks.task.get', 3, $params)->result['item'] ?? null;
        if (!is_array($item) || (int) ($item['id'] ?? 0) !== $id) {
            throw new Failure('api-error', 'The requested task was not returned by Bitrix24.');
        }

        return ResponseNormalizer::task($item);
    }

    public function page(array $filter, array $select, array $order, int $offset, int $limit): Page
    {
        $response = $this->api->call('tasks.task.list', 3, [
            'filter' => $filter, 'select' => $select, 'order' => (object) $order,
            'pagination' => ['offset' => $offset, 'limit' => $limit],
        ]);
        $items = $response->result['items'] ?? null;
        if (!is_array($items)) {
            throw new Failure('api-error', 'The task list response has an unsupported shape.');
        }

        return new Page(array_map(ResponseNormalizer::task(...), $items), count($items) >= $limit ? $offset + count($items) : null);
    }

    public function update(int $id, array $fields): void
    {
        ResponseNormalizer::ack($this->api->call('tasks.task.update', 3, ['id' => $id, 'fields' => $fields], 'write')->result);
    }

    public function delete(int $id): void
    {
        ResponseNormalizer::ack($this->api->call('tasks.task.delete', 3, ['id' => $id], 'delete')->result);
    }

    public function fields(?string $name = null): array
    {
        $result = $name === null
            ? $this->api->call('tasks.task.field.list', 3, [])->result
            : $this->api->call('tasks.task.field.get', 3, ['name' => $name])->result;
        $rows = $name === null ? ($result['items'] ?? null) : [$result['item'] ?? null];
        if (!is_array($rows) || array_any($rows, static fn ($row): bool => !is_array($row))) {
            throw new Failure('api-error', 'The field metadata response has an unsupported shape.');
        }

        return array_values($rows);
    }

    public function access(int $id): array
    {
        $result = $this->api->call('tasks.task.access.get', 3, ['id' => $id])->result;
        foreach ($result as $value) {
            if (!is_bool($value) && $value !== null) {
                throw new Failure('api-error', 'The permissions response has an unsupported shape.');
            }
        }

        return $result;
    }

    public function attach(int $id, int $fileId): void
    {
        ResponseNormalizer::ack($this->api->call('tasks.task.file.attach', 3, ['taskId' => $id, 'fileIds' => [$fileId]], 'write')->result);
    }
}
