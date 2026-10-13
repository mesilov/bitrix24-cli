<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\User;

use Bitrix24\CLI\Application\Failure;

final class UserRecord
{
    public const FIELDS = ['ID', 'NAME', 'SECOND_NAME', 'LAST_NAME', 'EMAIL', 'UF_DEPARTMENT'];

    public static function id(mixed $value): int
    {
        if ((!is_int($value) && (!is_string($value) || !ctype_digit($value))) || filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1) {
            throw new Failure('api-error', 'The directory returned an invalid ID.');
        }

        return (int) $value;
    }

    public static function normalize(array $row): array
    {
        $user = ['ID' => (string) self::id($row['ID'] ?? null)];
        foreach (array_slice(self::FIELDS, 1) as $field) {
            if (!array_key_exists($field, $row)) {
                continue;
            }

            $value = $row[$field];
            if ($field === 'UF_DEPARTMENT') {
                if (!is_array($value) || !array_is_list($value)) {
                    throw new Failure('api-error', 'The directory returned invalid UF_DEPARTMENT data.');
                }

                $value = array_values(array_unique(array_map(self::id(...), $value)));
            } elseif (!is_string($value) && $value !== null) {
                throw new Failure('api-error', 'The directory returned an invalid user field.');
            }

            $user[$field] = $value;
        }

        return $user;
    }
}
