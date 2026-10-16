<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\FieldSchema;
use Bitrix24\CLI\Application\Task\Request;
use Bitrix24\CLI\Application\Task\TaskQuery;
use Bitrix24\CLI\Application\Task\TaskRole;
use Symfony\Component\Console\Input\InputInterface;

final readonly class TaskInputMapper
{
    public function __construct(private InputSourceReader $reader, private ?\Bitrix24\CLI\Infrastructure\Connection\ConnectionManager $connections = null, private ?\Bitrix24\CLI\Application\Task\Port\LocalFileGateway $files = null, private \Bitrix24\CLI\Application\Task\DeadlineWindowResolver $deadlines = new \Bitrix24\CLI\Application\Task\DeadlineWindowResolver())
    {
    }

    public function id(mixed $value, string $option = 'TASK_ID'): int
    {
        if ((!is_int($value) && (!is_string($value) || !ctype_digit($value))) || (int) $value < 1 || (string) (int) $value !== ltrim((string) $value, '0')) {
            throw Failure::usage('Provide a positive integer for ' . $option . '.');
        }

        return (int) $value;
    }

    public function taskId(InputInterface $input): int
    {
        return $this->id($input->getArgument('TASK_ID'));
    }

    public function handoverTasks(InputInterface $input): Request\HandoverTasksRequest
    {
        $groupId = $this->id($input->getOption('group-id'), '--group-id');
        $responsibleId = $this->id($input->getOption('responsible'), '--responsible');
        $from = $input->getOption('from') === null ? null : $this->id($input->getOption('from'), '--from');
        $maxScan = $input->getOption('max-scan') === null ? 10000 : $this->id($input->getOption('max-scan'), '--max-scan');
        if ($from === $responsibleId) {
            throw Failure::usage('Source and responsible user must be different.');
        }

        return new Request\HandoverTasksRequest($groupId, $responsibleId, $from, $maxScan);
    }

    public function text(InputInterface $input, string $name, bool $required = true, bool $allowEmpty = false): ?string
    {
        $inline = $input->getOption($name);
        $file = $input->hasOption($name . '-file') ? $input->getOption($name . '-file') : null;
        if ($inline !== null && $file !== null) {
            throw Failure::usage('Choose --' . $name . ' or --' . $name . '-file.');
        }

        $value = $file === null ? $inline : $this->reader->read($file);
        if ($value === null && $required) {
            throw Failure::usage('Provide --' . $name . '.');
        }

        if ($value !== null && (!is_string($value) || (!$allowEmpty && trim($value) === ''))) {
            throw Failure::usage('Provide nonempty --' . $name . '.');
        }

        return $value;
    }

    private function json(InputInterface $input, string $name): ?array
    {
        $value = $this->text($input, $name, false);
        return $value === null ? null : $this->reader->object($value);
    }

    private function patch(InputInterface $input, bool $create, ?array $TAGS = null, ?array $custom = null): array
    {
        $advanced = $this->json($input, 'fields');
        if ($advanced !== null && $TAGS !== null) {
            throw Failure::usage('Advanced --fields/--fields-file cannot be mixed with --tag/--clear-tags.');
        }

        $fields = [];
        foreach (['title' => 'title', 'description' => 'description', 'creator' => 'creatorId', 'responsible' => 'responsibleId', 'group-id' => 'groupId', 'deadline' => 'deadline'] as $flag => $field) {
            if (!$input->hasOption($flag)) {
                continue;
            }

            $value = in_array($flag, ['title', 'description'], true) ? $this->text($input, $flag, false, $flag === 'description') : $input->getOption($flag);
            if ($value === null) {
                continue;
            }

            if ($advanced !== null) {
                throw Failure::usage('Advanced --fields cannot be mixed with field flags.');
            }

            $fields[$field] = in_array($flag, ['creator', 'responsible', 'group-id'], true) ? $this->id($value, '--' . $flag) : $value;
        }

        $legacy = $create && $custom !== null;
        $fields = $advanced ?? ($legacy ? \Bitrix24\CLI\Application\Task\LegacyTaskFields::basic($fields) : $fields);
        if ($create) {
            if ($fields !== []) {
                $legacy ? \Bitrix24\CLI\Application\Task\LegacyTaskFields::validate($fields, false) : FieldSchema::localPatch($fields, false);
            }

            $sources = array_fill_keys(array_keys($fields), 'explicit');
            $defaults = $this->connections?->defaults($input->getOption('dry-run')) ?? [];
            foreach ($legacy ? \Bitrix24\CLI\Application\Task\LegacyTaskFields::basic($defaults) : $defaults as $field => $value) {
                if (!array_key_exists($field, $fields)) {
                    $fields[$field] = $value;
                    $sources[$field] = 'profile-default';
                }
            }

            $tracking = $legacy ? 'ALLOW_TIME_TRACKING' : 'allowsTimeTracking';
            if (!array_key_exists($tracking, $fields)) {
                $fields[$tracking] = $legacy ? 'N' : false;
                $sources[$tracking] = 'built-in-default';
            }

            if ($this->connections instanceof \Bitrix24\CLI\Infrastructure\Connection\ConnectionManager) {
                $this->connections->fieldSources = $sources;
            }
        }

        if ($fields !== [] || $create || ($TAGS === null && $custom === null)) {
            $legacy ? \Bitrix24\CLI\Application\Task\LegacyTaskFields::validate($fields) : FieldSchema::localPatch($fields, $create);
        }

        return [$fields, $advanced !== null];
    }

    private function TAGS(InputInterface $input): ?array
    {
        $values = $input->getOption('tag');
        $clear = $input->hasOption('clear-tags') && $input->getOption('clear-tags');
        if ($clear && $values !== []) {
            throw Failure::usage('Choose --tag or --clear-tags.');
        }

        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/(*UCP)\\S/u', $value) !== 1) {
                throw Failure::usage('Provide a nonempty UTF-8 --tag name.');
            }
        }

        return $clear ? [] : ($values === [] ? null : array_values(array_unique($values)));
    }

    private function customFields(InputInterface $input): ?array
    {
        $value = $this->text($input, 'user-fields', false);
        if ($value === null) {
            return null;
        }

        $fields = $this->reader->uniqueObject($value);
        \Bitrix24\CLI\Application\Task\CustomTaskFields::input($fields);
        return $fields;
    }

    public function addTask(InputInterface $input): Request\AddTaskRequest
    {
        $TAGS = $this->TAGS($input);
        $custom = $this->customFields($input);
        [$fields, $advanced] = $this->patch($input, true, $TAGS, $custom);
        $roles = $this->connections?->userDefaults($input->getOption('dry-run')) ?? [];
        $observer = ($roles['autoAddObserver'] ?? false) ? $roles['observerId'] : null;
        if ($observer !== null && $this->connections instanceof \Bitrix24\CLI\Infrastructure\Connection\ConnectionManager) {
            $this->connections->fieldSources['default.users.observerId'] = 'profile-default';
        }

        return new Request\AddTaskRequest($fields, $advanced, $observer, $TAGS, $custom);
    }

    public function showTask(InputInterface $input): Request\ShowTaskRequest
    {
        $select = FieldSchema::select($input->getOption('select') ?: FieldSchema::CARD);
        \Bitrix24\CLI\Application\Task\TaskParticipantEnricher::projections($select);
        return new Request\ShowTaskRequest($this->taskId($input), $select);
    }

    public function updateTask(InputInterface $input): Request\UpdateTaskRequest
    {
        $TAGS = $this->TAGS($input);
        $custom = $this->customFields($input);
        [$fields, $advanced] = $this->patch($input, false, $TAGS, $custom);
        return new Request\UpdateTaskRequest($this->taskId($input), $fields, $advanced, 'task:update', $TAGS, $custom);
    }

    public function assignTask(InputInterface $input): Request\UpdateTaskRequest
    {
        return new Request\UpdateTaskRequest($this->taskId($input), ['responsibleId' => $this->id($input->getOption('responsible'), '--responsible')], false, 'task:assign');
    }

    public function setTaskDeadline(InputInterface $input): Request\UpdateTaskRequest
    {
        return new Request\UpdateTaskRequest($this->taskId($input), ['deadline' => FieldSchema::date($input->getOption('at'))], false, 'task:deadline:set');
    }

    private function limit(InputInterface $input, int $default): ?int
    {
        if ($input->getOption('all') && $input->getOption('limit') !== null) {
            throw Failure::usage('Choose --limit or --all.');
        }

        return $input->getOption('all') ? null : ($input->getOption('limit') === null ? $default : $this->id($input->getOption('limit'), '--limit'));
    }

    private function query(InputInterface $input, bool $find): TaskQuery
    {
        $windows = $input->getOption('deadline-window');
        if (count($windows) > 1) {
            throw Failure::usage('Provide --deadline-window only once.');
        }

        $deadlineWindow = $this->deadlines->resolve($windows[0] ?? null, $input->getOption('overdue'), $input->getOption('timezone'));
        if ($deadlineWindow instanceof \Bitrix24\CLI\Application\Task\DeadlineWindow && ($input->getOption('due-before') !== null || $input->getOption('where') !== null || $input->getOption('where-file') !== null)) {
            throw Failure::usage('Do not mix --deadline-window/--overdue with --due-before or --where/--where-file.');
        }

        $value = $input->getOption('role');
        $role = $value === null ? null : (is_string($value) ? TaskRole::tryFrom($value) : null);
        if ($value !== null && $role === null) {
            throw Failure::usage('Use --role responsible, accomplice, creator or auditor.');
        }

        $where = $this->json($input, 'where');
        $predicates = [];
        foreach (['responsible' => 'responsibleId', 'group-id' => 'groupId', 'status' => 'status'] as $flag => $field) {
            $value = $input->getOption($flag);
            if ($value !== null) {
                $predicates[$field] = $flag === 'status' ? \Bitrix24\CLI\Application\Task\TaskStatus::validateFilter($value) : $this->id($value, '--' . $flag);
            }
        }

        if ($where !== null && ($predicates !== [] || $input->getOption('due-before') !== null)) {
            throw Failure::usage('Choose --where/--where-file or --responsible/--group-id/--status/--due-before; --title, --id and --role may be combined with either.');
        }

        foreach ($where ?? [] as $field => $value) {
            if (!in_array($field, ['id', 'title', 'creatorId', 'responsibleId', 'groupId', 'status', 'deadline'], true) || (!is_string($value) && !is_int($value))) {
                throw Failure::usage('Unsupported --where field or value.');
            }

            if ($field === 'status') {
                \Bitrix24\CLI\Application\Task\TaskStatus::validateFilter($value);
            }

            if (in_array($field, ['id', 'creatorId', 'responsibleId', 'groupId'], true)) {
                $this->id($value, '--where ID field');
            }
        }

        $order = ['id' => 'DESC'];
        if ($input->getOption('order') !== []) {
            $order = [];
            foreach ($input->getOption('order') as $value) {
                $parts = explode(':', $value);
                if (count($parts) !== 2 || !in_array($parts[0], FieldSchema::SORTABLE, true) || !in_array(strtoupper($parts[1]), ['ASC', 'DESC'], true)) {
                    throw Failure::usage('Use --order FIELD:asc or FIELD:desc with a documented sortable field.');
                }

                $order[$parts[0]] = strtoupper($parts[1]);
            }

            $order['id'] ??= 'ASC';
        }

        return new TaskQuery(
            array_map(fn ($id): int => $this->id($id, '--id'), $input->getOption('id')),
            $where ?? $predicates,
            $input->getOption('due-before') !== null ? FieldSchema::date($input->getOption('due-before')) : null,
            $find ? ['id', 'title'] : FieldSchema::select($input->getOption('select')),
            $order,
            $this->limit($input, 50),
            $input->getOption('max-scan') === null ? 10000 : $this->id($input->getOption('max-scan'), '--max-scan'),
            $role,
            $deadlineWindow,
        );
    }

    public function listTasks(InputInterface $input): Request\ListTasksRequest
    {
        return new Request\ListTasksRequest($this->query($input, false));
    }

    public function myTasks(InputInterface $input): Request\ListTasksRequest
    {
        return new Request\ListTasksRequest(new TaskQuery(
            where: ['responsibleId' => $this->id($input->getOption('user'), '--user')],
            dueBefore: $input->getOption('due-before') === null ? null : FieldSchema::date($input->getOption('due-before')),
            maxScan: $input->getOption('max-scan') === null ? 10000 : $this->id($input->getOption('max-scan'), '--max-scan'),
            openOnly: $input->getOption('open'),
        ));
    }

    public function findTasks(InputInterface $input): Request\FindTasksRequest
    {
        return new Request\FindTasksRequest($this->query($input, true), trim($this->text($input, 'title')));
    }

    public function lifecycleRequest(InputInterface $input, \Bitrix24\CLI\Application\Task\LifecycleAction $action): Request\TaskLifecycleRequest
    {
        return new Request\TaskLifecycleRequest($this->taskId($input), $action);
    }

    public function taskReference(InputInterface $input): Request\TaskReference
    {
        return new Request\TaskReference($this->taskId($input));
    }

    public function listTaskFields(InputInterface $input): Request\ListTaskFieldsRequest
    {
        return new Request\ListTaskFieldsRequest($this->text($input, 'name', false));
    }


    public function listTaskChat(InputInterface $input): Request\ListTaskChatRequest
    {
        $before = $input->getOption('before');
        $after = $input->getOption('after');
        if ($before !== null && $after !== null) {
            throw Failure::usage('Choose --before or --after.');
        }

        return new Request\ListTaskChatRequest($this->taskId($input), $before === null ? null : $this->id($before, '--before'), $after === null ? null : $this->id($after, '--after'), $this->limit($input, 20), $input->getOption('max-messages') === null ? 10000 : $this->id($input->getOption('max-messages'), '--max-messages'));
    }

    public function sendTaskChatMessage(InputInterface $input): Request\SendTaskChatMessageRequest
    {
        return new Request\SendTaskChatMessageRequest($this->taskId($input), $this->text($input, 'text'));
    }

    public function updateTaskChatMessage(InputInterface $input): Request\UpdateTaskChatMessageRequest
    {
        return new Request\UpdateTaskChatMessageRequest($this->taskId($input), $this->id($input->getOption('message'), '--message'), $this->text($input, 'text'));
    }

    public function deleteTaskChatMessage(InputInterface $input): Request\DeleteTaskChatMessageRequest
    {
        return new Request\DeleteTaskChatMessageRequest($this->taskId($input), $this->id($input->getOption('message'), '--message'));
    }

    public function attachTaskFiles(InputInterface $input, int $maxUploadBytes = \Bitrix24\CLI\Application\Task\LocalFile::DEFAULT_MAX_BYTES): Request\AttachTaskFilesRequest
    {
        $taskId = $this->taskId($input);
        $path = $input->getOption('file');
        $folder = $input->getOption('folder-id');
        $name = $input->getOption('name');
        $ids = $input->getOption('file-id');
        if ($path !== null) {
            if ($ids !== [] || $folder === null) {
                throw Failure::usage('Choose --file-id or --file PATH with --folder-id ID.');
            }

            if (!$this->files instanceof \Bitrix24\CLI\Application\Task\Port\LocalFileGateway) {
                throw new Failure('configuration-error', 'Local file source is not configured.');
            }

            $folderId = $this->id($folder, '--folder-id');
            return new Request\AttachTaskFilesRequest($taskId, [], $this->files->inspect($path, $name, $maxUploadBytes), $folderId);
        }

        if ($folder !== null || $name !== null || $ids === []) {
            throw Failure::usage('Provide --file-id, or --file PATH with --folder-id ID; --name belongs to the local file mode.');
        }

        return new Request\AttachTaskFilesRequest($taskId, array_values(array_unique(array_map(fn ($id): int => $this->id($id, '--file-id'), $ids))));
    }

    public function addTaskResult(InputInterface $input): Request\AddTaskResultRequest
    {
        return new Request\AddTaskResultRequest($this->taskId($input), $this->text($input, 'text'));
    }

    public function listTaskResults(InputInterface $input): Request\ListTaskResultsRequest
    {
        return new Request\ListTaskResultsRequest($this->taskId($input), $this->limit($input, 50), $input->getOption('max-scan') === null ? 10000 : $this->id($input->getOption('max-scan'), '--max-scan'));
    }

    public function updateTaskResult(InputInterface $input): Request\UpdateTaskResultRequest
    {
        return new Request\UpdateTaskResultRequest($this->taskId($input), $this->id($input->getOption('result'), '--result'), $this->text($input, 'text'));
    }

    public function deleteTaskResult(InputInterface $input): Request\DeleteTaskResultRequest
    {
        return new Request\DeleteTaskResultRequest($this->taskId($input), $this->id($input->getOption('result'), '--result'));
    }


    public function addTaskTimeEntry(InputInterface $input): Request\AddTaskTimeEntryRequest
    {
        return new Request\AddTaskTimeEntryRequest($this->taskId($input), $this->id($input->getOption('seconds'), '--seconds'), $this->text($input, 'text', false, true));
    }

    public function updateTaskTimeEntry(InputInterface $input): Request\UpdateTaskTimeEntryRequest
    {
        return new Request\UpdateTaskTimeEntryRequest($this->taskId($input), $this->id($input->getOption('entry'), '--entry'), $this->id($input->getOption('seconds'), '--seconds'), $this->text($input, 'text', false, true));
    }

    public function deleteTaskTimeEntry(InputInterface $input): Request\DeleteTaskTimeEntryRequest
    {
        return new Request\DeleteTaskTimeEntryRequest($this->taskId($input), $this->id($input->getOption('entry'), '--entry'));
    }

    public function listTaskTimeEntries(InputInterface $input): Request\ListTaskTimeEntriesRequest
    {
        $query = $this->json($input, 'params') ?? [];
        if (array_diff(array_keys($query), ['order', 'filter', 'select', 'params']) !== []) {
            throw Failure::usage('Unsupported time query parameter.');
        }

        $fields = ['ID', 'TASK_ID', 'USER_ID', 'SECONDS', 'MINUTES', 'SOURCE', 'COMMENT_TEXT', 'CREATED_DATE', 'DATE_START', 'DATE_STOP'];
        foreach (['order' => ['ID', 'TASK_ID', 'USER_ID', 'MINUTES', 'SECONDS', 'CREATED_DATE', 'DATE_START', 'DATE_STOP'], 'filter' => ['ID', 'TASK_ID', 'USER_ID', 'CREATED_DATE']] as $key => $allowed) {
            if (isset($query[$key]) && (!is_array($query[$key]) || array_diff(array_keys($query[$key]), $allowed) !== [])) {
                throw Failure::usage('Unsupported time query field.');
            }
        }

        foreach ($query['order'] ?? [] as $direction) {
            if (!is_string($direction) || !in_array(strtoupper($direction), ['ASC', 'DESC'], true)) {
                throw Failure::usage('Time query order must be ASC or DESC.');
            }
        }

        foreach ($query['filter'] ?? [] as $field => $value) {
            if (!is_int($value) && !is_string($value)) {
                throw Failure::usage('Time filter values must be scalar.');
            }

            if (in_array($field, ['ID', 'TASK_ID', 'USER_ID'], true)) {
                $this->id($value, 'time filter ID');
            }

            if ($field === 'TASK_ID' && (int) $value !== $this->taskId($input)) {
                throw Failure::usage('Time query cannot override the selected TASK_ID.');
            }
        }

        if (isset($query['select']) && (!is_array($query['select']) || !array_is_list($query['select']) || array_filter($query['select'], static fn ($value): bool => !is_string($value)) !== [] || array_diff($query['select'], $fields) !== [])) {
            throw Failure::usage('Unsupported time query select.');
        }

        $params = $query['params'] ?? [];
        if (!is_array($params) || array_diff(array_keys($params), ['NAV_PARAMS']) !== []) {
            throw Failure::usage('Time params support NAV_PARAMS only.');
        }

        $nav = $params['NAV_PARAMS'] ?? [];
        if (!is_array($nav) || array_diff(array_keys($nav), ['nPageSize', 'iNumPage']) !== []) {
            throw Failure::usage('Unsupported time pagination.');
        }

        foreach ($nav as $field => $value) {
            $n = $this->id($value, $field);
            if ($field === 'nPageSize' && $n > 50) {
                throw Failure::usage('Time page size must be between 1 and 50.');
            }
        }

        return new Request\ListTaskTimeEntriesRequest($this->taskId($input), $query, isset($nav['iNumPage']));
    }

    public function addTaskChecklist(InputInterface $input): Request\AddTaskChecklistRequest
    {
        return new Request\AddTaskChecklistRequest($this->taskId($input), $this->text($input, 'title'));
    }


    public function addTaskChecklistItem(InputInterface $input): Request\AddTaskChecklistItemRequest
    {
        return new Request\AddTaskChecklistItemRequest($this->taskId($input), $this->id($input->getOption('checklist'), '--checklist'), $this->text($input, 'title'), $input->getOption('parent') === null ? null : $this->id($input->getOption('parent'), '--parent'));
    }

    public function listTaskChecklistItems(InputInterface $input): Request\ListTaskChecklistItemsRequest
    {
        return new Request\ListTaskChecklistItemsRequest($this->taskId($input), $this->id($input->getOption('checklist'), '--checklist'));
    }

    public function updateTaskChecklistItem(InputInterface $input): Request\UpdateTaskChecklistItemRequest
    {
        return new Request\UpdateTaskChecklistItemRequest($this->taskId($input), $this->id($input->getOption('item'), '--item'), $this->text($input, 'title'));
    }

    public function checklistItemReference(InputInterface $input): Request\ChecklistItemReference
    {
        return new Request\ChecklistItemReference($this->taskId($input), $this->id($input->getOption('item'), '--item'));
    }



    public function setTaskParticipants(InputInterface $input): Request\SetTaskParticipantsRequest
    {
        $roles = [];
        foreach (['accomplice' => 'accomplices', 'auditor' => 'auditors'] as $flag => $role) {
            $ids = $input->getOption($flag);
            $clear = $input->getOption('clear-' . $role);
            if ($ids !== [] && $clear) {
                throw Failure::usage('Do not mix IDs and clearing for one role.');
            }

            $roles[$role] = $clear ? [] : ($ids === [] ? null : array_values(array_unique(array_map(fn ($id): int => $this->id($id, '--' . $flag), $ids))));
        }

        if ($roles['accomplices'] === null && $roles['auditors'] === null) {
            throw Failure::usage('Provide a participant role set or an explicit clear flag.');
        }

        return new Request\SetTaskParticipantsRequest($this->taskId($input), $roles['accomplices'], $roles['auditors']);
    }

    public function listTaskHistory(InputInterface $input): Request\ListTaskHistoryRequest
    {
        $query = $this->json($input, 'params') ?? [];
        if (array_diff(array_keys($query), ['order', 'filter']) !== [] || (isset($query['filter']) && (!is_array($query['filter']) || array_diff(array_keys($query['filter']), ['FIELD']) !== [] || !is_string($query['filter']['FIELD'] ?? null))) || (isset($query['order']) && (!is_array($query['order']) || array_diff(array_keys($query['order']), ['createdDate']) !== [] || !in_array(strtoupper((string) ($query['order']['createdDate'] ?? '')), ['ASC', 'DESC'], true)))) {
            throw Failure::usage('History params support filter.FIELD and order.createdDate only.');
        }

        return new Request\ListTaskHistoryRequest($this->taskId($input), $query);
    }
}
