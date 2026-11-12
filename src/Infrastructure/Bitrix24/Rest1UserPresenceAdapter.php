<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Chat\CallMessage;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\User\Port\UserPresenceGateway;

/**
 * im.user.list.get; status, idle and online flags are deliberately dropped.
 */
final readonly class Rest1UserPresenceAdapter implements UserPresenceGateway
{
    public const MAX_IDS = 50;

    public function __construct(private ApiTransport $api)
    {
    }

    public function presence(array $ids): array
    {
        $users = [];
        foreach (array_chunk(array_values(array_unique($ids)), self::MAX_IDS) as $chunk) {
            $result = $this->api->call('im.user.list.get', 1, ['ID' => $chunk])->result;
            foreach ($result as $key => $row) {
                if ($row === null || $row === false) {
                    continue;
                }

                $id = is_array($row) ? CallMessage::positive($row['id'] ?? $key) : null;
                if ($id === null || !in_array($id, $chunk, true)) {
                    throw new Failure('api-error', 'The messenger returned an unexpected user row.');
                }

                $activity = $row['last_activity_date'] ?? null;
                $absent = $row['absent'] ?? null;
                $users[$id] = [
                    'id' => $id,
                    'name' => is_string($row['name'] ?? null) ? $row['name'] : null,
                    'lastActivityDate' => is_string($activity) && $activity !== '' ? $activity : null,
                    'absent' => is_string($absent) && $absent !== '' ? $absent : false,
                ];
            }
        }

        ksort($users);
        return $users;
    }
}
