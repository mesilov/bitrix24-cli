<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;

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
            if (in_array($name, ['tags', 'TAGS'], true)) {
                throw Failure::usage('Tags are read-only in REST 3.0 fields; use --tag or --clear-tags.');
            }

            if ($name === 'userFields' || str_starts_with((string) $name, 'UF_')) {
                throw Failure::usage('Use --user-fields/--user-fields-file for custom task values; REST3 userFields is read-only.');
            }

            if (!in_array($name, self::WRITABLE, true)) {
                throw Failure::usage('Unsupported or read-only task field; use lifecycle commands for status changes.');
            }

            if (in_array($name, ['creatorId', 'responsibleId', 'groupId', 'parentId', 'estimatedTime'], true) && (!is_int($value) || $value < 1)) {
                throw Failure::usage('ID and time estimate fields must be positive integers.');
            }

            if (in_array($name, ['allowsTimeTracking', 'needsControl', 'allowsChangeDeadline', 'addInReport', 'requireResult'], true) && !is_bool($value)) {
                throw Failure::usage($name . ' must be a JSON boolean.');
            }

            if ($name === 'title' && (!is_string($value) || trim($value) === '')) {
                throw Failure::usage('Provide a nonempty title.');
            }

            if ($name === 'description' && !is_string($value)) {
                throw Failure::usage('Description must be a string; an empty string clears it.');
            }

            if ($name === 'priority' && !in_array($value, ['high', 'average', 'low'], true)) {
                throw Failure::usage('priority must be high, average or low.');
            }

            if ($name === 'mark' && !in_array($value, ['positive', 'negative', 'none'], true)) {
                throw Failure::usage('mark must be positive, negative or none.');
            }

            if (in_array($name, ['crmItemIds', 'fileIds'], true)) {
                if (!is_array($value) || !array_is_list($value) || array_any($value, static fn ($item): bool => $name === 'fileIds' ? !is_int($item) || $item < 1 : !is_string($item) || preg_match('/^(?:L|D|C|CO|SI|T[0-9]+)_[0-9]+$/', $item) !== 1)) {
                    throw Failure::usage($name . ' must be a JSON list of documented identifiers.');
                }
            }

            if (in_array($name, ['deadline', 'startPlan', 'endPlan'], true) && $value !== null) {
                self::date($value);
            }
        }

        if ($create && array_diff(['title', 'creatorId', 'responsibleId'], array_keys($fields)) !== []) {
            throw Failure::usage('Missing required creation fields: ' . implode(', ', array_diff(['title', 'creatorId', 'responsibleId'], array_keys($fields))) . '. Supply --title, --creator and --responsible or matching profile defaults.');
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

}
