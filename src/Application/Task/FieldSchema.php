<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\Port\TaskGateway;

final class FieldSchema
{
    public const CARD = ['id', 'title', 'description', 'creator.id', 'responsible.id', 'group.id', 'status', 'deadline'];
    public const LIST_FIELDS = ['id', 'title', 'responsibleId', 'groupId', 'status', 'deadline'];
    public const WRITABLE = [
        'title', 'description', 'creatorId', 'responsibleId', 'groupId', 'deadline', 'parentId', 'priority',
        'startPlan', 'endPlan', 'estimatedTime', 'allowsTimeTracking', 'needsControl', 'mark',
        'allowsChangeDeadline', 'addInReport', 'requireResult', 'crmItemIds', 'fileIds',
    ];
    public const SORTABLE = ['id', 'title', 'creatorId', 'created', 'responsibleId', 'deadline', 'startPlan', 'endPlan', 'groupId', 'priority', 'status', 'started', 'estimatedTime', 'changed', 'closed', 'activity', 'mark', 'allowsChangeDeadline', 'allowsTimeTracking'];
    public const READABLE = [
        'Name', 'id', 'title', 'description', 'creatorId', 'creator', 'created', 'responsibleId', 'responsible', 'deadline', 'needsControl', 'startPlan', 'endPlan', 'checklist', 'fileIds', 'groupId', 'group', 'stageId', 'stage', 'epicId', 'storyPoints', 'flowId', 'flow', 'priority', 'status', 'statusChanged', 'accomplices', 'auditors', 'parentId', 'parent', 'containsChecklist', 'containsSubTasks', 'containsRelatedTasks', 'containsGanttLinks', 'containsPlacements', 'containsResults', 'numberOfReminders', 'chatId', 'chat', 'plannedDuration', 'actualDuration', 'durationType', 'started', 'estimatedTime', 'replicate', 'changed', 'changedById', 'changedBy', 'statusChangedById', 'statusChangedBy', 'closedById', 'closedBy', 'closed', 'activity', 'guid', 'xmlId', 'exchangeId', 'exchangeModified', 'outlookVersion', 'mark', 'allowsChangeDeadline', 'allowsTimeTracking', 'matchesWorkTime', 'addInReport', 'isMultitask', 'siteId', 'deadlineCount', 'declineReason', 'forumTopicId', 'forkedByTemplateId', 'forkedByTemplate', 'maxDeadlineChangeDate', 'maxDeadlineChanges', 'requireDeadlineChangeReason', 'tags', 'link', 'userFields', 'rights', 'archiveLink', 'crmItemIds', 'emailId', 'email', 'elapsedTime', 'requireResult', 'matchesSubTasksTime', 'autocompleteSubTasks', 'allowsChangeDatePlan', 'inFavorite', 'inPin', 'inGroupPin', 'inMute', 'source', 'dependsOn', 'scenarios'
    ];

    public static function select(array $fields): array
    {
        foreach ($fields as $field) {
            if (!is_string($field) || !in_array(explode('.', $field)[0], self::READABLE, true) || !preg_match('/^[a-zA-Z][a-zA-Z0-9]*(?:\.[a-zA-Z][a-zA-Z0-9]*)?$/', $field)) {
                throw Failure::usage('Unsupported --select field; use task:fields:list to inspect the schema.');
            }
        }

        $select = [];
        foreach ($fields as $field) {
            array_push($select, ...(in_array($field, ['accomplices', 'auditors'], true) ? [$field . '.id', $field . '.name'] : [$field]));
        }

        return array_values(array_unique($select));
    }

    public static function localPatch(array $fields, bool $create): void
    {
        if ($fields === []) {
            throw Failure::usage('Provide at least one field to change.');
        }

        foreach ($fields as $name => $value) {
            if (!in_array($name, self::WRITABLE, true)) {
                throw Failure::usage('Unsupported or read-only task field; status changes are outside MVP.');
            }

            if (in_array($name, ['creatorId', 'responsibleId', 'groupId', 'parentId', 'estimatedTime'], true) && (!is_int($value) || $value < 1)) {
                throw Failure::usage('ID and time estimate fields must be positive integers.');
            }

            if ($name === 'title' && (!is_string($value) || trim($value) === '')) {
                throw Failure::usage('Provide a nonempty title.');
            }

            if ($name === 'description' && !is_string($value)) {
                throw Failure::usage('Description must be a string; an empty string clears it.');
            }

            if (in_array($name, ['deadline', 'startPlan', 'endPlan'], true)) {
                self::date($value);
            }
        }

        if ($create && array_diff(['title', 'creatorId', 'responsibleId'], array_keys($fields)) !== []) {
            throw Failure::usage('Creation requires --title, --creator and --responsible (or their advanced fields).');
        }
    }

    public static function date(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
            throw Failure::usage('Use an ISO8601 date and time with an explicit timezone offset.');
        }

        try {
            new \DateTimeImmutable($value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                throw new \InvalidArgumentException();
            }
        } catch (\Throwable) {
            throw Failure::usage('The supplied date and time is invalid.');
        }

        return $value;
    }

    public static function validateWritable(TaskGateway $gateway, array $fields): void
    {
        $schema = [];
        foreach ($gateway->fields() as $field) {
            $schema[$field['name'] ?? ''] = $field;
        }

        foreach ($fields as $name => $value) {
            $field = $schema[$name] ?? null;
            if (!is_array($field) || ($field['editable'] ?? null) !== true) {
                throw Failure::usage('An advanced field is not confirmed editable by the portal metadata.');
            }

            $type = $field['type'] ?? null;
            $valid = match ($type) {
                'integer', 'int' => is_int($value), 'boolean', 'bool' => is_bool($value),
                'string', 'datetime', 'date' => is_string($value), 'array' => is_array($value),
                'object' => is_array($value) || is_object($value), default => false,
            };
            if (($field['multiple'] ?? false) === true) {
                $valid = is_array($value);
            }

            if (!$valid) {
                throw Failure::usage('An advanced field has an unsupported or mismatched metadata type.');
            }
        }
    }
}
