<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\Page;
use Bitrix24\CLI\Application\Task\Port\TaskResultGateway;

final readonly class Rest3TaskResultAdapter implements TaskResultGateway
{
    public const FIELDS = ['id', 'taskId', 'text', 'authorId', 'createdAt', 'updatedAt', 'status', 'fileIds', 'rights', 'messageId'];

    public function __construct(private ApiTransport $api)
    {
    }

    public function add(int $taskId, string $text): int
    {
        $result = $this->api->call('tasks.task.result.add', 3, ['fields' => ['taskId' => $taskId, 'text' => $text]], 'write')->result;
        return $this->item($result['item'] ?? null, $taskId, null, true)['id'];
    }

    public function page(int $taskId, int $offset, int $limit, ?int $id = null): Page
    {
        $filter = [['taskId', $taskId]];
        if ($id !== null) {
            $filter[] = ['id', $id];
        }

        $result = $this->api->call('tasks.task.result.list', 3, [
            'filter' => $filter, 'select' => self::FIELDS, 'order' => ['id' => 'ASC'],
            'pagination' => ['offset' => $offset, 'limit' => $limit],
        ])->result;
        $items = $result['items'] ?? null;
        if (!is_array($items) || !array_is_list($items) || count($items) > $limit) {
            throw new Failure('api-error', 'The task result list response has an unsupported shape.');
        }

        $rows = array_map(fn ($item): array => $this->item($item, $taskId), $items);
        return new Page($rows, count($rows) >= $limit ? $offset + count($rows) : null);
    }

    public function update(int $taskId, int $id, string $text): void
    {
        $result = $this->api->call('tasks.task.result.update', 3, ['id' => $id, 'fields' => ['text' => $text]], 'write')->result;
        $this->item($result['item'] ?? null, $taskId, $id, true);
    }

    public function delete(int $id): void
    {
        if (($this->api->call('tasks.task.result.delete', 3, ['id' => $id], 'delete')->result['result'] ?? null) !== true) {
            throw new Failure('api-error', 'Bitrix24 did not confirm task result deletion; inspect the result before retrying.', 1, [], true);
        }
    }

    private function item(mixed $item, int $taskId, ?int $id = null, bool $mutation = false): array
    {
        if (!is_array($item) || !is_int($item['id'] ?? null) || $item['id'] < 1
            || ($item['taskId'] ?? null) !== $taskId || ($id !== null && $item['id'] !== $id)
            || !is_string($item['text'] ?? null) || !in_array($item['status'] ?? null, ['open', 'closed'], true)) {
            throw new Failure('api-error', 'Bitrix24 returned an invalid task result; inspect the result before retrying a write.', 1, [], $mutation);
        }

        return $item;
    }
}
