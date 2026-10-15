<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;

final class CustomTaskFields
{
    public static function input(array $fields): void
    {
        if ($fields === []) {
            throw Failure::usage('Provide at least one custom UF_* value.');
        }

        foreach ($fields as $code => $value) {
            if (!is_string($code) || preg_match('/^UF_[A-Z0-9_]+$/', $code) !== 1 || in_array($code, ['UF_CRM_TASK', 'UF_TASK_WEBDAV_FILES', 'UF_MAIL_MESSAGE'], true)) {
                throw Failure::usage('Use exact custom UF_* codes; system custom fields are not supported.');
            }

            $values = is_array($value) ? $value : [$value];
            if (!array_is_list($values) || array_any($values, static fn ($item): bool => !is_scalar($item) || (is_float($item) && !is_finite($item)))) {
                throw Failure::usage('Custom values must be JSON scalars or flat scalar lists; null and objects are unsupported.');
            }
        }
    }

    public static function validate(array $fields, array $schema): void
    {
        self::input($fields);
        foreach ($fields as $code => $value) {
            $type = $schema[$code]['type'] ?? null;
            if (!in_array($type, ['string', 'integer', 'int', 'double', 'boolean', 'date', 'datetime'], true)) {
                throw Failure::usage('Unknown or unsupported custom field: ' . $code . '.');
            }

            foreach (is_array($value) ? $value : [$value] as $item) {
                if ($item === '') {
                    continue;
                }

                $valid = match ($type) {
                    'string', 'date', 'datetime' => is_string($item),
                    'integer', 'int' => is_int($item) || (is_string($item) && preg_match('/^-?[0-9]+$/', $item) === 1),
                    'double' => (is_int($item) || is_float($item) || is_string($item)) && is_numeric($item) && is_finite((float) $item),
                    default => is_bool($item) || in_array($item, [0, 1, '0', '1'], true),
                };
                if (!$valid) {
                    throw Failure::usage('Value type does not match custom field ' . $code . '.');
                }
            }
        }
    }
}
