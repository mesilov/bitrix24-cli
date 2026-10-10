<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Failure;

final class ResponseNormalizer
{
    public static function id(array $result): int
    {
        $id = $result['item']['id'] ?? $result['id'] ?? $result[0] ?? null;
        if ((!is_int($id) && (!is_string($id) || !ctype_digit($id))) || (int) $id < 1) {
            throw new Failure('api-error', 'The API response did not contain a valid resource ID.', 1, [], true);
        }

        return (int) $id;
    }

    public static function ack(array $result): void
    {
        $ack = $result['result'] ?? $result[0] ?? null;
        if (!in_array($ack, [true, 1, '1'], true)) {
            throw new Failure('api-error', 'Bitrix24 did not confirm the operation.');
        }
    }

    public static function task(array $task): array
    {
        if (isset($task['id']) && (is_int($task['id']) || (is_string($task['id']) && ctype_digit($task['id'])))) {
            $task['id'] = (int) $task['id'];
        }

        foreach (['responsible', 'creator', 'group', 'chat'] as $relation) {
            if (array_key_exists($relation, $task)) {
                $task[$relation . 'Id'] = isset($task[$relation]['id']) ? (int) $task[$relation]['id'] : null;
            }
        }

        return $task;
    }

    public static function voidAck(array $result): void
    {
        if ($result !== [null]) {
            throw new Failure('api-error', 'Bitrix24 did not confirm the operation.');
        }
    }

    public static function fields(array $row, array $mapping): array
    {
        $out = [];
        foreach ($mapping as $source => $target) {
            if (array_key_exists($source, $row)) {
                $out[$target] = $row[$source];
            }
        }

        foreach (['id', 'parentId', 'taskId', 'userId', 'authorId', 'seconds', 'sortIndex'] as $key) {
            if (isset($out[$key]) && (is_int($out[$key]) || (is_string($out[$key]) && ctype_digit($out[$key])))) {
                $out[$key] = (int) $out[$key];
            }
        }

        return $out;
    }
}
