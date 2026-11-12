<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Route;

/**
 * Selects the task list route before any request: server predicates go to REST 1.0
 * tasks.task.list when the default policy and the chosen fields allow it, otherwise
 * the REST 3.0 route keeps local filtering. Temporal flags always use REST 1.0 and
 * carry the same server predicates next to their date bounds.
 */
final readonly class TaskListRoute
{
    /** Fields readable through REST 1.0 tasks.task.list, kept equal to Rest1TaskListAdapter::FIELDS keys. */
    public const REST1_FIELDS = [
        'id', 'title', 'description', 'creatorId', 'responsibleId', 'groupId', 'deadline', 'status', 'created', 'started',
        'changed', 'closed', 'activity', 'startPlan', 'endPlan', 'estimatedTime', 'priority', 'mark',
        'allowsChangeDeadline', 'allowsTimeTracking',
    ];

    /** Fields sortable through REST 1.0 tasks.task.list, kept equal to Rest1TaskListAdapter::SORT_FIELDS. */
    public const REST1_SORT_FIELDS = [
        'id', 'title', 'created', 'deadline', 'startPlan', 'endPlan', 'groupId', 'priority', 'status', 'started',
        'estimatedTime', 'changed', 'closed', 'activity', 'mark', 'allowsChangeDeadline', 'allowsTimeTracking',
    ];

    private const SERVER_FIELDS = ['groupId' => 'GROUP_ID', 'responsibleId' => 'RESPONSIBLE_ID', 'creatorId' => 'CREATED_BY', 'status' => 'REAL_STATUS'];

    /**
     * Statuses documented for the REST 1.0 REAL_STATUS filter. "declined" (7) is not documented there and stays a local check.
     */
    public const REAL_STATUSES = ['pending' => 2, 'in_progress' => 3, 'supposedly_completed' => 4, 'completed' => 5, 'deferred' => 6];

    /**
     * @param list<string> $serverFilter
     */
    private function __construct(public string $apiVersion, public array $serverFilter, public ?string $reason)
    {
    }

    /**
     * Returns null for callers that do not opt in (no API policy on the query) and use no temporal flags.
     * Temporal flags select REST 1.0 regardless of the fields; the adapter rejects unsupported fields as usage errors.
     */
    public static function plan(TaskQuery $query): ?self
    {
        $idFilter = $query->ids === [] ? [] : ['id'];
        $candidates = self::candidates($query);
        if ($query->temporal()) {
            $windows = [
                ...($query->deadlineWindow instanceof DeadlineWindow ? ['deadline'] : []),
                ...array_map(static fn (DateWindow $window): string => $window->field, $query->dateWindows),
            ];
            return new self('1.0', [...$idFilter, ...$candidates, ...$windows], null);
        }

        if ($query->apiPolicy === null) {
            return null;
        }

        if ($candidates === []) {
            return new self('3.0', $idFilter, 'no-server-predicate');
        }

        if ($query->apiPolicy !== 'task-v3') {
            return new self('3.0', $idFilter, $query->apiPolicy);
        }

        foreach ([...$query->select, ...array_keys($query->where)] as $field) {
            if (!in_array($field, self::REST1_FIELDS, true)) {
                return new self('3.0', $idFilter, 'unsupported-field:' . $field);
            }
        }

        foreach (array_keys($query->order) as $field) {
            if (!in_array($field, self::REST1_SORT_FIELDS, true)) {
                return new self('3.0', $idFilter, 'unsupported-field:' . $field);
            }
        }

        return new self('1.0', [...$idFilter, ...$candidates], null);
    }

    /**
     * Full route plan of task:list/task:find for the API policy guard.
     *
     * @return list<Route>
     */
    public static function routes(TaskQuery $query): array
    {
        $participants = $query->role?->participantField() === null ? [] : [new Route('tasks.task.get', 1, 'read')];
        if (self::plan($query)?->server() === true) {
            return [new Route('tasks.task.list', 1, 'read'), ...$participants];
        }

        return [
            new Route('tasks.task.list', 3, 'read'),
            ...(array_key_exists('groupId', $query->where) ? [new Route('tasks.task.get', 3, 'read')] : []),
            ...$participants,
        ];
    }

    public function server(): bool
    {
        return $this->apiVersion === '1.0';
    }

    /**
     * REST 1.0 filter for the server route. An explicit field value wins over the connection user of a
     * scalar role; the role itself stays a local AND check, so contradictory criteria end in a complete empty result.
     * Temporal windows add their date bounds.
     */
    public function filter(TaskQuery $query, ?int $userId): array
    {
        $filter = [
            ...($query->deadlineWindow?->filter() ?? []),
            ...array_merge([], ...array_map(static fn (DateWindow $window): array => $window->filter(), $query->dateWindows)),
            ...($query->ids === [] ? [] : ['ID' => $query->ids]),
        ];
        $role = $query->role?->scalarField();
        foreach (self::SERVER_FIELDS as $field => $native) {
            if (!in_array($field, $this->serverFilter, true)) {
                continue;
            }

            $value = $query->where[$field] ?? ($role === $field ? $userId : null);
            if ($field === 'status') {
                $value = self::REAL_STATUSES[$value] ?? null;
            }

            if ($value !== null) {
                $filter[$native] = (int) $value;
            }
        }

        return $filter;
    }

    public function metadata(): array
    {
        return ['apiVersion' => $this->apiVersion, 'serverFilter' => $this->serverFilter, 'reason' => $this->reason];
    }

    /**
     * @return list<string>
     */
    private static function candidates(TaskQuery $query): array
    {
        $role = $query->role?->scalarField();
        return array_values(array_filter(
            array_keys(self::SERVER_FIELDS),
            static fn (string $field): bool => $field === 'status'
                ? is_string($query->where['status'] ?? null) && array_key_exists($query->where['status'], self::REAL_STATUSES)
                : isset($query->where[$field]) || $role === $field,
        ));
    }
}
