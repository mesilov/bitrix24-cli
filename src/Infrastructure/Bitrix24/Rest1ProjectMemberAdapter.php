<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\Port\ProjectMemberGateway;

final readonly class Rest1ProjectMemberAdapter implements ProjectMemberGateway
{
    public function __construct(private ApiTransport $api)
    {
    }

    public function ids(int $groupId): array
    {
        $response = $this->api->call('sonet_group.user.get', 1, ['ID' => $groupId]);
        if (!array_is_list($response->result) || $response->next !== null || ($response->total !== null && $response->total !== count($response->result))) {
            throw new Failure('project-membership-unverified', 'The project membership response is incomplete or unsupported.', 4);
        }

        $ids = [];
        foreach ($response->result as $row) {
            $value = is_array($row) ? ($row['USER_ID'] ?? null) : null;
            $id = (is_int($value) || (is_string($value) && ctype_digit($value))) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
            if ($id === false || !in_array($row['ROLE'] ?? null, ['A', 'E', 'K'], true) || in_array($id, $ids, true)) {
                throw new Failure('project-membership-unverified', 'The project returned an invalid or ambiguous active member.', 4);
            }

            $ids[] = $id;
        }

        return $ids;
    }
}
