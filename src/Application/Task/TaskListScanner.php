<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Application\Task\Port\CurrentUserGateway;
use Bitrix24\CLI\Application\Task\Port\DeadlineTaskGateway;
use Bitrix24\CLI\Application\Task\Port\ParticipantGateway;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;
use Bitrix24\CLI\Application\Task\Port\UnreadCounterGateway;

final readonly class TaskListScanner
{
    private const PROGRESS_STEP = 1000;

    public function __construct(private TaskGateway $tasks, private CurrentUserGateway $currentUser, private ParticipantGateway $participants, private RuntimeState $state, private DeadlineTaskGateway $deadlineTasks, private ?UnreadCounterGateway $unreadCounters = null)
    {
    }

    public function scan(TaskQuery $query, ?string $title = null): OperationResult
    {
        $filter = $query->ids === [] ? [] : [['id', 'in', $query->ids]];
        $deadlineWindow = $query->deadlineWindow;
        $dueBefore = $query->dueBefore === null ? null : new \DateTimeImmutable($query->dueBefore);
        $route = TaskListRoute::plan($query);
        $rest1 = $route?->server() === true;
        $scalarRole = $query->role?->scalarField();
        $participantRole = $query->role?->participantField();
        $fields = array_values(array_unique([...($title === null ? FieldSchema::LIST_FIELDS : ['id', 'title']), ...$query->select, ...array_keys($query->where), ...($query->dueBefore === null && !$deadlineWindow instanceof \Bitrix24\CLI\Application\Task\DeadlineWindow ? [] : ['deadline']), ...($deadlineWindow?->overdue || $query->openOnly ? ['status'] : [])]));
        // Fields needed only for local checks are read but not returned.
        $hiddenFields = [];
        foreach ([$scalarRole, ...($query->unread ? ['chatId'] : []), ...array_map(static fn (DateWindow $window): string => $window->field, $query->dateWindows)] as $field) {
            if ($field !== null && !in_array($field, $fields, true)) {
                $fields[] = $field;
                $hiddenFields[] = $field;
            }
        }

        if ($query->temporal()) {
            $this->deadlineTasks->validate($fields, $query->order);
        }

        $userId = $query->role instanceof \Bitrix24\CLI\Application\Task\TaskRole && !$deadlineWindow?->empty() ? $this->currentUser->id() : null;
        if ($route instanceof \Bitrix24\CLI\Application\Task\TaskListRoute && $rest1) {
            $filter = $route->filter($query, $userId);
        }

        $localScan = $route instanceof \Bitrix24\CLI\Application\Task\TaskListRoute && !$rest1 && $query->ids === [];
        if ($localScan) {
            $this->state->notice($this->localScanNotice($route, $query->maxScan));
        }

        // One snapshot per run, read before the task pages so every row is joined with the same observation.
        $snapshot = null;
        $unreadReasons = [];
        if ($query->unread) {
            if (!$this->unreadCounters instanceof UnreadCounterGateway) {
                throw new Failure('configuration-error', 'The unread counter source is not configured.');
            }

            $this->state->checkCancellation();
            $snapshot = $this->unreadCounters->snapshot();
            $unreadReasons = $snapshot->reasons;
        }

        $offset = 0;
        $scanned = 0;
        $matches = [];
        $seen = [];
        $reason = null;
        $complete = true;
        do {
            if ($deadlineWindow?->empty()) {
                break;
            }

            $this->state->checkCancellation();
            try {
                $page = $rest1
                    ? $this->deadlineTasks->page($filter, $fields, $query->order, $offset)
                    : $this->tasks->page($filter, $fields, $query->order, $offset, min(50, $query->maxScan - $scanned));
            } catch (Failure $failure) {
                if ($scanned === 0 || $failure->exitStatus === 130) {
                    throw $failure;
                }

                $reason = 'page-error';
                $complete = false;
                break;
            }

            $progress = 0;
            foreach ($page->items as $row) {
                $this->state->checkCancellation();
                if ($scanned >= $query->maxScan) {
                    $reason = 'scan-budget-exhausted';
                    $complete = false;
                    break;
                }

                $scanned++;
                if ($localScan && $scanned % self::PROGRESS_STEP === 0) {
                    $this->state->notice(sprintf('Локальный обход: просмотрено %d из %d задач, совпадений %d.', $scanned, $query->maxScan, count($matches)));
                }

                $id = $row['id'] ?? null;
                if (!is_int($id) || $id < 1) {
                    $reason = 'missing-id';
                    $complete = false;
                    continue;
                }

                if (isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;
                $progress++;
                $required = ['title', ...($rest1 ? array_keys($query->where) : array_diff(array_keys($query->where), ['groupId'])), ...($query->dueBefore === null && !$deadlineWindow instanceof \Bitrix24\CLI\Application\Task\DeadlineWindow ? [] : ['deadline']), ...($deadlineWindow?->overdue || $query->openOnly ? ['status'] : []), ...array_map(static fn (DateWindow $window): string => $window->field, $query->dateWindows)];
                if (array_diff($required, array_keys($row)) !== [] || !is_string($row['title'] ?? null)) {
                    $reason = 'missing-predicate-field';
                    $complete = false;
                    continue;
                }

                // An undated task has deadline=null on REST1; that is a value for local equality, not missing data.
                if ($rest1 && array_any(array_diff(array_keys($query->where), ['deadline']), static fn (string $field): bool => $row[$field] === null)) {
                    $reason = 'missing-predicate-field';
                    $complete = false;
                    continue;
                }

                if (isset($query->where['responsibleId']) && ((!is_int($row['responsibleId']) && (!is_string($row['responsibleId']) || !ctype_digit($row['responsibleId']))) || filter_var($row['responsibleId'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false)) {
                    $reason = 'missing-predicate-field';
                    $complete = false;
                    continue;
                }

                $matched = $title === null || mb_stripos($row['title'], $title) !== false;
                foreach ($query->where as $field => $value) {
                    if ($field !== 'groupId' || isset($row['groupId']) || $rest1) {
                        $matched = $matched && (string) $row[$field] === (string) $value;
                    }
                }

                if ($query->openOnly) {
                    if (!in_array($row['status'], [...DeadlineWindow::OPEN_STATUSES, 'completed', 'declined'], true)) {
                        $reason = 'missing-predicate-field';
                        $complete = false;
                        continue;
                    }

                    $matched = $matched && in_array($row['status'], DeadlineWindow::OPEN_STATUSES, true);
                }

                if ($dueBefore instanceof \DateTimeImmutable) {
                    try {
                        $deadline = $row['deadline'] === null ? null : new \DateTimeImmutable(FieldSchema::date($row['deadline']));
                    } catch (\Throwable) {
                        $reason = 'missing-predicate-field';
                        $complete = false;
                        continue;
                    }

                    $matched = $matched && $deadline instanceof \DateTimeImmutable && $deadline < $dueBefore;
                }

                if ($deadlineWindow instanceof \Bitrix24\CLI\Application\Task\DeadlineWindow) {
                    $deadlineMatch = $deadlineWindow->matches($row['deadline']);
                    if ($deadlineMatch === null || ($deadlineWindow->overdue && !is_string($row['status']))) {
                        $reason = 'missing-predicate-field';
                        $complete = false;
                        continue;
                    }

                    $matched = $matched && $deadlineMatch && (!$deadlineWindow->overdue || in_array($row['status'], DeadlineWindow::OPEN_STATUSES, true));
                    if ($query->ids !== []) {
                        $matched = $matched && in_array($id, $query->ids, true);
                    }
                }

                foreach ($query->dateWindows as $dateWindow) {
                    $dateMatch = $dateWindow->matches($row[$dateWindow->field]);
                    if ($dateMatch === null) {
                        $reason = 'missing-predicate-field';
                        $complete = false;
                        continue 2;
                    }

                    $matched = $matched && $dateMatch;
                }

                if ($query->dateWindows !== [] && $query->ids !== []) {
                    $matched = $matched && in_array($id, $query->ids, true);
                }

                if ($scalarRole !== null) {
                    $value = $row[$scalarRole] ?? null;
                    if ((!is_int($value) && (!is_string($value) || !ctype_digit($value))) || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                        $reason = 'missing-predicate-field';
                        $complete = false;
                        continue;
                    }

                    $matched = $matched && (int) $value === $userId;
                }

                if ($matched && $participantRole !== null) {
                    try {
                        $this->state->checkCancellation();
                        $members = $this->participants->ids($id, [$participantRole]);
                        $matched = in_array($userId, $members[$participantRole], true);
                    } catch (Failure $failure) {
                        if ($failure->exitStatus === 130) {
                            throw $failure;
                        }

                        $reason = 'role-read-error';
                        $complete = false;
                        break 2;
                    }
                }

                if ($matched && !$rest1 && array_key_exists('groupId', $query->where)) {
                    if (!isset($row['groupId'])) {
                        try {
                            $card = $this->tasks->get($id, ['group.id']);
                            $group = $card['group'] ?? null;
                            if (!array_key_exists('group', $card) || ($group !== null && (!is_array($group) || !is_int($group['id'] ?? null) || $group['id'] < 1))) {
                                throw new Failure('api-error', 'Task group membership was not returned.');
                            }

                            $row['groupId'] = $group['id'] ?? null;
                        } catch (Failure $failure) {
                            if ($failure->exitStatus === 130) {
                                throw $failure;
                            }

                            $reason = 'group-membership-unverified';
                            $complete = false;
                            continue;
                        }
                    }

                    $matched = (string) $row['groupId'] === (string) $query->where['groupId'];
                }

                $chatState = null;
                if ($matched && $snapshot instanceof UnreadCounterSnapshot) {
                    $chatId = $this->chatId($id, $row['chatId'] ?? null);
                    if ($chatId === null) {
                        $unreadReasons[] = 'task-chat-association-unavailable';
                    } elseif ($snapshot->conflict($chatId)) {
                        $unreadReasons[] = 'unread-counter-conflict';
                    }

                    // An unknown counter or binding is not a zero: the row is dropped and the run is partial.
                    $counter = is_int($chatId) && $chatId > 0 ? $snapshot->counter($chatId) : null;
                    $matched = is_int($counter) && $counter > 0;
                    if ($matched) {
                        $chatState = ['chatId' => $chatId, 'counter' => $counter, 'manuallyMarkedUnread' => $snapshot->manuallyMarkedUnread($chatId)];
                    }
                }

                if ($matched) {
                    foreach ($hiddenFields as $hiddenField) {
                        unset($row[$hiddenField]);
                    }

                    if ($chatState !== null) {
                        $row['chatState'] = $chatState;
                    }

                    $matches[] = $title === null ? $row : ['id' => $id, 'title' => $row['title']];
                }
            }

            $next = $page->next;
            if ($rest1 && !$page->complete) {
                $reason = 'pagination-no-progress';
                $complete = false;
                break;
            }

            if ($next !== null && ($progress === 0 || !is_int($next) || $next <= $offset)) {
                $reason = 'pagination-no-progress';
                $complete = false;
                break;
            }

            $offset = $next ?? 0;
            if ($next !== null && $scanned >= $query->maxScan) {
                if ($query->verifyExhaustionAtBudget && !$rest1) {
                    $this->state->checkCancellation();
                    try {
                        $probe = $this->tasks->page($filter, $fields, $query->order, $next, 1);
                        if ($probe->items === [] && $probe->next === null) {
                            $next = null;
                            continue;
                        }
                    } catch (Failure $failure) {
                        if ($failure->exitStatus === 130) {
                            throw $failure;
                        }

                        $reason = 'page-error';
                        $complete = false;
                        break;
                    }
                }

                $reason = 'scan-budget-exhausted';
                $complete = false;
                break;
            }
        } while ($next !== null);

        if ($snapshot instanceof UnreadCounterSnapshot) {
            $unreadReasons = array_values(array_unique($unreadReasons));
            if ($unreadReasons !== []) {
                $complete = false;
                $reason ??= $unreadReasons[0];
            }
        }

        $matchedCount = count($matches);
        $items = $query->limit === null ? $matches : array_slice($matches, 0, $query->limit);
        return new OperationResult($title === null ? 'task-list' : 'title-matches', ['items' => $items], [
            'scope' => $query->ids === [] ? 'tasks-visible-to-connection' : 'explicit-task-ids',
            'complete' => $complete, 'scanned' => $scanned, 'matched' => $matchedCount, 'returned' => count($items),
            'limitApplied' => count($items) < $matchedCount, 'reason' => $reason,
            ...($query->role instanceof \Bitrix24\CLI\Application\Task\TaskRole ? ['roleFilter' => ['role' => $query->role->value, 'userId' => $userId]] : []),
            'order' => $query->order,
            ...($route instanceof \Bitrix24\CLI\Application\Task\TaskListRoute ? ['listRoute' => $route->metadata()] : []),
            ...($deadlineWindow instanceof \Bitrix24\CLI\Application\Task\DeadlineWindow ? ['deadlineFilter' => [...$deadlineWindow->metadata(), 'groupApplied' => isset($query->where['groupId']) ? 'server' : null]] : []),
            ...array_merge([], ...array_map(static fn (DateWindow $window): array => [$window->field . 'Filter' => $window->metadata()], $query->dateWindows)),
            ...($snapshot instanceof UnreadCounterSnapshot ? ['unreadState' => $snapshot->metadata($unreadReasons)] : []),
            ...($reason === 'scan-budget-exhausted' ? ['hint' => 'Increase --max-scan to inspect more tasks; --all only removes the output limit. ' . ($title === null ? 'An incomplete scan does not prove that no matching task exists.' : 'An incomplete search does not prove that no duplicate exists.')] : []),
        ], $complete ? 0 : 3);
    }

    /**
     * Chat of a matched task: a positive chatId of the list row confirms it. Otherwise one task get reads the
     * chat binding; 0 means a confirmed absent chat (chat=null), null an unverified binding.
     */
    private function chatId(int $taskId, mixed $value): ?int
    {
        if (is_string($value) && preg_match('/^[1-9]\d{0,17}$/', $value) === 1) {
            $value = (int) $value;
        }

        if (is_int($value) && $value > 0) {
            return $value;
        }

        try {
            $this->state->checkCancellation();
            $task = $this->tasks->get($taskId, ['chat.id', 'chat.entityId', 'chat.entityType']);
        } catch (Failure $failure) {
            if ($failure->exitStatus === 130) {
                throw $failure;
            }

            return null;
        }

        if (array_key_exists('chat', $task) && $task['chat'] === null) {
            return 0;
        }

        $chat = $task['chat'] ?? null;
        if (!is_array($chat) || !is_int($chat['id'] ?? null) || $chat['id'] < 1 || (string) ($chat['entityId'] ?? '') !== (string) $taskId || ($chat['entityType'] ?? null) !== 'TASKS_TASK') {
            return null;
        }

        return $chat['id'];
    }

    private function localScanNotice(TaskListRoute $route, int $maxScan): string
    {
        $reason = (string) $route->reason;
        $advice = match (true) {
            $reason === 'strict-rest3' => 'серверные фильтры недоступны при --api-policy strict-rest3.',
            str_starts_with($reason, 'unsupported-field:') => 'уберите поле ' . substr($reason, strlen('unsupported-field:')) . ' из --select/--order/--where, чтобы фильтровать на сервере.',
            default => 'для серверной выборки добавьте --group-id, --responsible, --role responsible|creator, --status или временное окно (--deadline-window, --created-window, --changed-window).',
        };

        return sprintf('Локальный обход REST 3.0 (причина: %s): просмотр до %d задач (--max-scan), условия проверяются на клиенте; %s', $reason, $maxScan, $advice);
    }
}
