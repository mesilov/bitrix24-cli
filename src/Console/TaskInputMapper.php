<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\FieldSchema;
use Bitrix24\CLI\Application\Task\Request;
use Bitrix24\CLI\Application\Task\TaskQuery;
use Symfony\Component\Console\Input\InputInterface;

final readonly class TaskInputMapper
{
    public function __construct(private InputSourceReader $reader)
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

    private function patch(InputInterface $input, bool $create): array
    {
        $advanced = $this->json($input, 'fields');
        $fields = [];
        foreach (['title' => 'title', 'description' => 'description', 'creator' => 'creatorId', 'responsible' => 'responsibleId', 'project' => 'groupId', 'deadline' => 'deadline'] as $flag => $field) {
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

            $fields[$field] = in_array($flag, ['creator', 'responsible', 'project'], true) ? $this->id($value, '--' . $flag) : $value;
        }

        $fields = $advanced ?? $fields;
        FieldSchema::localPatch($fields, $create);
        return [$fields, $advanced !== null];
    }

    public function addTask(InputInterface $input): Request\AddTaskRequest
    {
        [$fields, $advanced] = $this->patch($input, true);
        return new Request\AddTaskRequest($fields, $advanced);
    }

    public function showTask(InputInterface $input): Request\ShowTaskRequest
    {
        return new Request\ShowTaskRequest($this->taskId($input), FieldSchema::select($input->getOption('select') ?: FieldSchema::CARD));
    }

    public function updateTask(InputInterface $input): Request\UpdateTaskRequest
    {
        [$fields, $advanced] = $this->patch($input, false);
        return new Request\UpdateTaskRequest($this->taskId($input), $fields, $advanced, 'task:update');
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
        $where = $find ? [] : $this->json($input, 'where');
        $predicates = [];
        if (!$find) {
            foreach (['responsible' => 'responsibleId', 'project' => 'groupId', 'status' => 'status'] as $flag => $field) {
                $value = $input->getOption($flag);
                if ($value !== null) {
                    $predicates[$field] = $flag === 'status' ? $value : $this->id($value, '--' . $flag);
                }
            }

            if ($where !== null && ($predicates !== [] || $input->getOption('due-before') !== null)) {
                throw Failure::usage('Choose --where or convenient filter flags.');
            }
        }

        foreach ($where ?? [] as $field => $value) {
            if (!in_array($field, ['id', 'title', 'creatorId', 'responsibleId', 'groupId', 'status', 'deadline'], true) || (!is_string($value) && !is_int($value))) {
                throw Failure::usage('Unsupported --where field or value.');
            }

            if (in_array($field, ['id', 'creatorId', 'responsibleId', 'groupId'], true)) {
                $this->id($value, '--where ID field');
            }
        }

        $order = ['id' => 'ASC'];
        if (!$find && $input->getOption('order') !== []) {
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
            $find ? [] : array_map(fn ($id): int => $this->id($id, '--id'), $input->getOption('id')),
            $where ?? $predicates,
            !$find && $input->getOption('due-before') !== null ? FieldSchema::date($input->getOption('due-before')) : null,
            $find ? ['id', 'title'] : FieldSchema::select($input->getOption('select')),
            $order,
            $this->limit($input, 50),
            $input->getOption('max-scan') === null ? 10000 : $this->id($input->getOption('max-scan'), '--max-scan'),
        );
    }

    public function listTasks(InputInterface $input): Request\ListTasksRequest
    {
        return new Request\ListTasksRequest($this->query($input, false));
    }

    public function findTasks(InputInterface $input): Request\FindTasksRequest
    {
        return new Request\FindTasksRequest($this->query($input, true), trim($this->text($input, 'title')));
    }

    public function deleteTask(InputInterface $input): Request\DeleteTaskRequest
    {
        return new Request\DeleteTaskRequest($this->taskId($input));
    }

    public function listTaskFields(InputInterface $input): Request\ListTaskFieldsRequest
    {
        return new Request\ListTaskFieldsRequest($this->text($input, 'name', false));
    }

    public function showTaskAccess(InputInterface $input): Request\ShowTaskAccessRequest
    {
        return new Request\ShowTaskAccessRequest($this->taskId($input));
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

    public function attachTaskFiles(InputInterface $input): Request\AttachTaskFilesRequest
    {
        if ($input->getOption('file-id') === []) {
            throw Failure::usage('Provide at least one --file-id of an existing Disk file.');
        }

        return new Request\AttachTaskFilesRequest($this->taskId($input), array_values(array_unique(array_map(fn ($id): int => $this->id($id, '--file-id'), $input->getOption('file-id')))));
    }

    public function showTaskTime(InputInterface $input): Request\ShowTaskTimeRequest
    {
        return new Request\ShowTaskTimeRequest($this->taskId($input));
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

    public function listTaskChecklists(InputInterface $input): Request\ListTaskChecklistsRequest
    {
        return new Request\ListTaskChecklistsRequest($this->taskId($input));
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

    public function completeTaskChecklistItem(InputInterface $input): Request\CompleteTaskChecklistItemRequest
    {
        return new Request\CompleteTaskChecklistItemRequest($this->taskId($input), $this->id($input->getOption('item'), '--item'));
    }

    public function renewTaskChecklistItem(InputInterface $input): Request\RenewTaskChecklistItemRequest
    {
        return new Request\RenewTaskChecklistItemRequest($this->taskId($input), $this->id($input->getOption('item'), '--item'));
    }

    public function deleteTaskChecklistItem(InputInterface $input): Request\DeleteTaskChecklistItemRequest
    {
        return new Request\DeleteTaskChecklistItemRequest($this->taskId($input), $this->id($input->getOption('item'), '--item'));
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
