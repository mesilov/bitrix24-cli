<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\User\Port\DepartmentGateway;
use Bitrix24\CLI\Application\User\UserRecord;

final readonly class Rest1DepartmentAdapter implements DepartmentGateway
{
    public function __construct(private ApiTransport $api)
    {
    }

    public function get(int $id): array
    {
        $response = $this->api->call('department.get', 1, ['ID' => $id]);
        if ($response->result === []) {
            throw new Failure('organization-unavailable', 'The selected department is unavailable; check department scope and membership.');
        }

        if (!array_is_list($response->result) || count($response->result) !== 1 || !is_array($row = $response->result[0]) || UserRecord::id($row['ID'] ?? null) !== $id || $response->next !== null) {
            throw new Failure('api-error', 'The organization returned an unexpected department.');
        }

        $department = ['ID' => $id];
        foreach (['PARENT', 'UF_HEAD'] as $field) {
            if (array_key_exists($field, $row)) {
                $value = $row[$field];
                $department[$field] = in_array($value, [0, '0', null, ''], true) ? 0 : UserRecord::id($value);
            }
        }

        return $department;
    }
}
