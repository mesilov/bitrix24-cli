<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;

/** Responses reproduce pinned SDK/documented shapes; no real portal is implied. */
final class FixturePortal
{
    public array $task = ['id' => 123, 'title' => 'Согласовать договор', 'description' => 'original', 'creator' => ['id' => 1], 'responsible' => ['id' => 2], 'group' => null, 'status' => 'pending', 'deadline' => null, 'chat' => ['id' => 456, 'entityId' => 123, 'entityType' => 'TASKS_TASK'], 'elapsedTime' => []];
    public array $nodes = [
        ['ID' => '10', 'TASK_ID' => '123', 'PARENT_ID' => '0', 'TITLE' => 'Root', 'SORT_INDEX' => '0', 'IS_COMPLETE' => 'N'],
        ['ID' => '11', 'TASK_ID' => '123', 'PARENT_ID' => '10', 'TITLE' => 'Item', 'SORT_INDEX' => '20', 'IS_COMPLETE' => 'N'],
        ['ID' => '12', 'TASK_ID' => '123', 'PARENT_ID' => '11', 'TITLE' => 'Nested', 'SORT_INDEX' => '10', 'IS_COMPLETE' => 'Y'],
    ];

    public function respond(string $method, int $version, array $params): ApiResponse
    {
        return match ($method) {
            'tasks.task.add' => new ApiResponse(['item' => ['id' => 124, ...$params['fields']]]),
            'tasks.task.get' => new ApiResponse(['item' => $this->task]),
            'tasks.task.list' => new ApiResponse(['items' => [['id' => 123, 'title' => $this->task['title'], 'responsibleId' => 2, 'groupId' => null, 'status' => 'pending', 'deadline' => null]]]),
            'tasks.task.field.list' => new ApiResponse(['items' => [['name' => 'title', 'type' => 'string', 'editable' => true, 'filterable' => false, 'sortable' => true], ['name' => 'id', 'type' => 'integer', 'editable' => false, 'filterable' => true, 'sortable' => true]]]),
            'tasks.task.field.get' => new ApiResponse(['item' => ['name' => $params['name'], 'type' => 'integer', 'editable' => false, 'filterable' => true, 'sortable' => true]]),
            'tasks.task.access.get' => new ApiResponse(['read' => true, 'edit' => false, 'delete' => null]),
            'im.dialog.messages.get' => new ApiResponse(['chat_id' => 456, 'messages' => [['id' => 900, 'chat_id' => 456, 'author_id' => 2, 'text' => 'Original', 'date' => '2026-10-09T12:00:00+06:00']]]),
            'task.elapseditem.add' => new ApiResponse([30]),
            'task.elapseditem.getlist' => new ApiResponse([['ID' => '30', 'TASK_ID' => '123', 'USER_ID' => '2', 'SECONDS' => '60', 'COMMENT_TEXT' => 'Original', 'CREATED_DATE' => '2026-10-09T12:00:00+06:00']], null, 1),
            'task.checklistitem.getlist' => new ApiResponse($this->nodes),
            'task.checklistitem.add' => new ApiResponse([13]),
            'tasks.task.history.list' => new ApiResponse(['list' => [['id' => 70, 'createdDate' => '2026-10-09T12:00:00+06:00', 'field' => 'TITLE', 'user' => ['id' => 2], 'value' => ['from' => 'old', 'to' => 'new']]]], null, 1),
            default => new ApiResponse($version === 3 ? ['result' => true] : [true]),
        };
    }
}
