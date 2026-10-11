<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\Port\UserGateway;

final readonly class Rest1UserAdapter implements UserGateway
{
    private const FIELDS = ['name' => ['NAME', 'SECOND_NAME', 'LAST_NAME'], 'email' => ['EMAIL'], 'gender' => ['PERSONAL_GENDER'], 'externalAuthId' => ['EXTERNAL_AUTH_ID']];

    public function __construct(private ApiTransport $api)
    {
    }

    public function byIds(array $ids, array $fields): array
    {
        $select = ['ID'];
        foreach ($fields as $field) {
            array_push($select, ...self::FIELDS[$field]);
        }

        $result = [];
        foreach (array_chunk(array_slice(array_values(array_unique($ids)), 0, 10000), 50) as $chunk) {
            $rows = $this->api->call('user.get', 1, ['filter' => ['@ID' => $chunk], 'select' => array_values(array_unique($select))])->result;
            if (!array_is_list($rows) || count($rows) > 50) {
                throw new Failure('api-error', 'The user lookup response has an unsupported shape.');
            }

            foreach ($rows as $row) {
                $rawId = is_array($row) ? ($row['ID'] ?? null) : null;
                if ((!is_int($rawId) && (!is_string($rawId) || !ctype_digit($rawId))) || filter_var($rawId, FILTER_VALIDATE_INT) === false || !in_array((int) $rawId, $chunk, true) || isset($result[(int) $rawId])) {
                    throw new Failure('api-error', 'The user lookup returned an invalid, unexpected or duplicate ID.');
                }

                $id = (int) $rawId;
                $user = ['id' => $id];
                foreach ($fields as $field) {
                    $sourceFields = self::FIELDS[$field];
                    if (array_diff($sourceFields, array_keys($row)) !== []) {
                        continue;
                    }

                    $values = array_map(static fn ($key) => $row[$key], $sourceFields);
                    if (array_any($values, static fn ($value): bool => !is_string($value) && $value !== null)) {
                        throw new Failure('api-error', 'The user lookup returned an unsupported field value.');
                    }

                    $user[$field] = $field === 'name' ? trim(implode(' ', array_filter(array_map(static fn ($value): string => trim($value ?? ''), $values), static fn ($value): bool => $value !== ''))) : $values[0];
                }

                $result[$id] = $user;
            }
        }

        return $result;
    }
}
