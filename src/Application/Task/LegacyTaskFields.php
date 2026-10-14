<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;

final class LegacyTaskFields
{
    public const BASIC = ['title' => 'TITLE', 'description' => 'DESCRIPTION', 'creatorId' => 'CREATED_BY', 'responsibleId' => 'RESPONSIBLE_ID', 'groupId' => 'GROUP_ID', 'deadline' => 'DEADLINE', 'allowsTimeTracking' => 'ALLOW_TIME_TRACKING'];

    /** Explicit mapping of basic CLI flags and stored profile defaults, never advanced JSON. */
    public static function basic(array $fields): array
    {
        $result = [];
        foreach ($fields as $name => $value) {
            if (!isset(self::BASIC[$name])) {
                throw Failure::usage('Unsupported ordinary field for custom task creation.');
            }

            $result[self::BASIC[$name]] = $name === 'allowsTimeTracking' ? ($value ? 'Y' : 'N') : $value;
        }

        return $result;
    }

    public static function validate(array $fields, bool $required = true): void
    {
        foreach ($fields as $name => $value) {
            $valid = match ($name) {
                'TITLE' => is_string($value) && trim($value) !== '',
                'DESCRIPTION' => is_string($value),
                'CREATED_BY', 'RESPONSIBLE_ID', 'GROUP_ID' => is_int($value) && $value > 0,
                'DEADLINE' => is_string($value),
                'ALLOW_TIME_TRACKING' => in_array($value, ['Y', 'N'], true),
                default => false,
            };
            if (!$valid) {
                throw Failure::usage('Custom creation --fields requires native REST1 TITLE/DESCRIPTION/CREATED_BY/RESPONSIBLE_ID/GROUP_ID/DEADLINE/ALLOW_TIME_TRACKING and valid types.');
            }

            if ($name === 'DEADLINE') {
                FieldSchema::date($value);
            }
        }

        if ($required && array_diff(['TITLE', 'CREATED_BY', 'RESPONSIBLE_ID'], array_keys($fields)) !== []) {
            throw Failure::usage('Provide TITLE, CREATED_BY and RESPONSIBLE_ID or matching basic flags/profile defaults.');
        }
    }
}
