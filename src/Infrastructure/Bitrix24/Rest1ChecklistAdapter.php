<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\Port\ChecklistGateway;

final readonly class Rest1ChecklistAdapter implements ChecklistGateway
{
    public function __construct(private ApiTransport $api)
    {
    }

    public function nodes(int $taskId): array
    {
        $result = $this->api->call('task.checklistitem.getlist', 1, ['TASKID' => $taskId, 'ORDER' => ['SORT_INDEX' => 'ASC', 'ID' => 'ASC']]);
        if ($result->next !== null) {
            throw Failure::binding('The checklist tree is incomplete.');
        }

        return array_map(static function (array $row): array {
            $node = ResponseNormalizer::fields($row, [
                'ID' => 'id', 'TASK_ID' => 'taskId', 'PARENT_ID' => 'parentId', 'TITLE' => 'title',
                'SORT_INDEX' => 'sortIndex', 'IS_COMPLETE' => 'isComplete',
            ]);
            $complete = $node['isComplete'] ?? null;
            $node['isComplete'] = match ($complete) {
                'Y', true => true, 'N', false => false, default => null
            };
            return $node;
        }, array_values($result->result));
    }

    public function add(int $taskId, int $parentId, string $title): int
    {
        return ResponseNormalizer::id($this->api->call('task.checklistitem.add', 1, [
            'TASKID' => $taskId, 'FIELDS' => ['TITLE' => $title, 'PARENT_ID' => $parentId, 'IS_COMPLETE' => 'N'],
        ], 'write')->result);
    }

    public function update(int $taskId, int $itemId, string $title): void
    {
        ResponseNormalizer::ack($this->api->call('task.checklistitem.update', 1, ['TASKID' => $taskId, 'ITEMID' => $itemId, 'FIELDS' => ['TITLE' => $title]], 'write')->result);
    }

    public function complete(int $taskId, int $itemId): void
    {
        ResponseNormalizer::ack($this->api->call('task.checklistitem.complete', 1, ['TASKID' => $taskId, 'ITEMID' => $itemId], 'write')->result);
    }

    public function renew(int $taskId, int $itemId): void
    {
        ResponseNormalizer::ack($this->api->call('task.checklistitem.renew', 1, ['TASKID' => $taskId, 'ITEMID' => $itemId], 'write')->result);
    }

    public function delete(int $taskId, int $itemId): void
    {
        ResponseNormalizer::ack($this->api->call('task.checklistitem.delete', 1, ['TASKID' => $taskId, 'ITEMID' => $itemId], 'delete')->result);
    }
}
