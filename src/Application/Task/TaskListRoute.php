<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Route;

/**
 * Selects the task list route before any request: server predicates go to REST 1.0
 * tasks.task.list when the default policy and the chosen fields allow it, otherwise
 * the REST 3.0 route keeps local filtering.
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

    private const SERVER_FIELDS = ['groupId' => 'GROUP_ID', 'responsibleId' => 'RESPONSIBLE_ID', 'creatorId' => 'CREATED_BY'];

    /**
     * @param list<string> $serverFilter
     */
    private function __construct(public string $apiVersion, public array $serverFilter, public ?string $reason)
    {
    }

    /**
     * Returns null for callers that do not opt in (no API policy on the query) and for deadline windows,
     * which keep their own REST 1.0 route and metadata.
     */
    public static function plan(TaskQuery $query): ?self
    {
        if ($query->apiPolicy === null || $query->deadlineWindow instanceof DeadlineWindow) {
            return null;
        }

        $idFilter = $query->ids === [] ? [] : ['id'];
        $candidates = self::candidates($query);
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
        if ($query->deadlineWindow instanceof DeadlineWindow) {
            return [new Route('tasks.task.list', 1, 'read'), ...$participants];
        }

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
     */
    public function filter(TaskQuery $query, ?int $userId): array
    {
        $filter = $query->ids === [] ? [] : ['ID' => $query->ids];
        $role = $query->role?->scalarField();
        foreach (self::SERVER_FIELDS as $field => $native) {
            if (!in_array($field, $this->serverFilter, true)) {
                continue;
            }

            $value = $query->where[$field] ?? ($role === $field ? $userId : null);
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
            static fn (string $field): bool => isset($query->where[$field]) || $role === $field,
        ));
    }
}
