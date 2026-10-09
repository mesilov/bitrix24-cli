<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Task\Page;
use Bitrix24\CLI\Application\Task\Port\TimeEntryGateway;

final readonly class Rest1TimeEntryAdapter implements TimeEntryGateway
{
    public function __construct(private ApiTransport $api)
    {
    }

    public function add(int $taskId, int $seconds, ?string $text): int
    {
        $fields = ['SECONDS' => $seconds];
        if ($text !== null) {
            $fields['COMMENT_TEXT'] = $text;
        }

        return ResponseNormalizer::id($this->api->call('task.elapseditem.add', 1, ['TASKID' => $taskId, 'ARFIELDS' => $fields], 'write')->result);
    }

    public function page(int $taskId, array $query, int $page): Page
    {
        $size = $query['params']['NAV_PARAMS']['nPageSize'] ?? 50;
        $select = array_values(array_unique(['ID', 'TASK_ID', 'USER_ID', 'SECONDS', 'COMMENT_TEXT', 'CREATED_DATE', ...($query['select'] ?? [])]));
        $response = $this->api->call('task.elapseditem.getlist', 1, [
            $taskId, (object) ($query['order'] ?? ['ID' => 'ASC']), (object) ($query['filter'] ?? []),
            $select, ['NAV_PARAMS' => ['nPageSize' => $size, 'iNumPage' => $page]],
        ]);
        $items = array_map(static fn (array $row): array => ResponseNormalizer::fields($row, [
            'ID' => 'id', 'TASK_ID' => 'taskId', 'USER_ID' => 'userId', 'SECONDS' => 'seconds',
            'COMMENT_TEXT' => 'text', 'CREATED_DATE' => 'createdDate',
        ]), $response->result);
        $finished = $response->total !== null ? $page * $size >= $response->total : count($items) < $size;
        return new Page($items, $finished ? null : $page + 1);
    }

    public function update(int $taskId, int $entryId, int $seconds, ?string $text): void
    {
        $fields = ['SECONDS' => $seconds];
        if ($text !== null) {
            $fields['COMMENT_TEXT'] = $text;
        }

        ResponseNormalizer::ack($this->api->call('task.elapseditem.update', 1, ['TASKID' => $taskId, 'ITEMID' => $entryId, 'ARFIELDS' => $fields], 'write')->result);
    }

    public function delete(int $taskId, int $entryId): void
    {
        ResponseNormalizer::ack($this->api->call('task.elapseditem.delete', 1, ['TASKID' => $taskId, 'ITEMID' => $entryId], 'delete')->result);
    }
}
