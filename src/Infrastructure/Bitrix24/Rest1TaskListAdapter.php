<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\Page;
use Bitrix24\CLI\Application\Task\Port\DeadlineTaskGateway;

final readonly class Rest1TaskListAdapter implements DeadlineTaskGateway, \Bitrix24\CLI\Application\Task\Port\WeeklyReportTaskGateway
{
    private const FIELDS = [
        'id' => 'ID', 'title' => 'TITLE', 'description' => 'DESCRIPTION', 'creatorId' => 'CREATED_BY',
        'responsibleId' => 'RESPONSIBLE_ID', 'groupId' => 'GROUP_ID', 'deadline' => 'DEADLINE', 'status' => 'STATUS',
        'created' => 'CREATED_DATE', 'started' => 'DATE_START', 'changed' => 'CHANGED_DATE', 'closed' => 'CLOSED_DATE',
        'activity' => 'ACTIVITY_DATE', 'startPlan' => 'START_DATE_PLAN', 'endPlan' => 'END_DATE_PLAN',
        'estimatedTime' => 'TIME_ESTIMATE', 'priority' => 'PRIORITY', 'mark' => 'MARK',
        'allowsChangeDeadline' => 'ALLOW_CHANGE_DEADLINE', 'allowsTimeTracking' => 'ALLOW_TIME_TRACKING',
    ];
    private const RESPONSE_FIELDS = [
        'creatorId' => 'createdBy', 'created' => 'createdDate', 'started' => 'dateStart', 'changed' => 'changedDate',
        'closed' => 'closedDate', 'activity' => 'activityDate', 'startPlan' => 'startDatePlan', 'endPlan' => 'endDatePlan',
        'estimatedTime' => 'timeEstimate', 'allowsChangeDeadline' => 'allowChangeDeadline', 'allowsTimeTracking' => 'allowTimeTracking',
    ];
    private const SORT_FIELDS = [
        'id', 'title', 'created', 'deadline', 'startPlan', 'endPlan', 'groupId', 'priority', 'status', 'started',
        'estimatedTime', 'changed', 'closed', 'activity', 'mark', 'allowsChangeDeadline', 'allowsTimeTracking',
    ];
    private const STATUSES = ['2' => 'pending', '3' => 'in_progress', '4' => 'supposedly_completed', '5' => 'completed', '6' => 'deferred', '7' => 'declined'];

    public function __construct(private ApiTransport $api)
    {
    }

    public function validate(array $select, array $order): void
    {
        if (array_diff($select, array_keys(self::FIELDS)) !== []) {
            throw Failure::usage('The REST 1.0 deadline route supports --select: ' . implode(', ', array_keys(self::FIELDS)) . '.');
        }

        if (array_diff(array_keys($order), self::SORT_FIELDS) !== []) {
            throw Failure::usage('The REST 1.0 deadline route supports --order: ' . implode(', ', self::SORT_FIELDS) . '.');
        }
    }

    public function page(array $filter, array $select, array $order, int $offset): Page
    {
        $this->validate($select, $order);
        $sort = [];
        foreach ($order as $field => $direction) {
            $sort[$field === 'status' ? 'REAL_STATUS' : self::FIELDS[$field]] = $direction;
        }

        $page = $this->read($filter, array_values(array_unique(array_map(static fn (string $field): string => self::FIELDS[$field], $select))), $sort, $offset);
        return new Page(array_map(fn (array $row): array => $this->normalize($row, $select), $page->items), $page->next);
    }

    public function reportPage(array $filter, int $offset): Page
    {
        $select = ['id', 'title', 'responsibleId', 'groupId', 'status', 'created', 'closed'];
        $page = $this->read($filter, array_map(static fn (string $field): string => self::FIELDS[$field], $select), ['ID' => 'ASC'], $offset);
        $items = [];
        foreach ($page->items as $row) {
            $item = $this->normalize($row, $select);
            foreach (['created' => 'createdDate', 'closed' => 'closedDate'] as $internal => $native) {
                if (array_key_exists($internal, $item)) {
                    $item[$native] = $item[$internal];
                    unset($item[$internal]);
                }
            }

            $items[] = $item;
        }

        return new Page($items, $page->next);
    }

    private function read(array $filter, array $select, array $order, int $offset): Page
    {
        $response = $this->api->call('tasks.task.list', 1, [
            'filter' => (object) $filter, 'select' => $select, 'order' => (object) $order, 'start' => $offset,
        ]);
        $rows = $response->result['tasks'] ?? null;
        if (!is_array($rows) || !array_is_list($rows) || array_any($rows, static fn ($row): bool => !is_array($row))) {
            throw new Failure('api-error', 'The REST 1.0 task list response has an unsupported shape.');
        }

        return new Page($rows, $response->next);
    }

    private function normalize(array $row, array $select): array
    {
        $result = [];
        foreach ($select as $field) {
            $source = self::RESPONSE_FIELDS[$field] ?? $field;
            if (!array_key_exists($source, $row) && !array_key_exists(self::FIELDS[$field], $row)) {
                continue;
            }

            $value = $row[$source] ?? $row[self::FIELDS[$field]] ?? null;
            $result[$field] = match ($field) {
                'status' => is_int($value) || is_string($value) ? (self::STATUSES[(string) $value] ?? null) : null,
                'priority' => is_int($value) || is_string($value) ? (['0' => 'low', '1' => 'average', '2' => 'high'][(string) $value] ?? null) : null,
                'mark' => is_string($value) ? (['P' => 'positive', 'N' => 'negative', '' => 'none'][$value] ?? null) : null,
                'allowsChangeDeadline', 'allowsTimeTracking' => match ($value) {
                    'Y' => true, 'N' => false, default => null
                },
                'id', 'creatorId', 'responsibleId', 'groupId', 'estimatedTime' => is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null,
                'deadline', 'created', 'started', 'changed', 'closed', 'activity', 'startPlan', 'endPlan' => $value === '' ? null : $value,
                default => $value,
            };
        }

        return $result;
    }
}
