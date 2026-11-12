<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Chat\CallMessage;
use Bitrix24\CLI\Application\Chat\Port\ChatMembersGateway;
use Bitrix24\CLI\Application\Failure;

/**
 * im.dialog.users.list (REST 1.0, scope im), pages by OFFSET/next.
 */
final readonly class Rest1ChatMembersAdapter implements ChatMembersGateway
{
    public const PAGE = 200;

    public function __construct(private ApiTransport $api)
    {
    }

    public function members(int $chatId, int $max): array
    {
        $ids = [];
        $offset = 0;
        $total = null;
        while (true) {
            $response = $this->api->call('im.dialog.users.list', 1, ['DIALOG_ID' => 'chat' . $chatId, 'SKIP_EXTERNAL' => 'Y', 'LIMIT' => self::PAGE, 'OFFSET' => $offset]);
            if (!array_is_list($response->result)) {
                throw new Failure('api-error', 'im.dialog.users.list returned an unsupported page.');
            }

            $total = $response->total ?? $total;
            foreach ($response->result as $row) {
                $id = is_array($row) ? CallMessage::positive($row['id'] ?? null) : null;
                if ($id === null) {
                    throw new Failure('api-error', 'im.dialog.users.list returned a malformed member row.');
                }

                if (($row['bot'] ?? false) !== true) {
                    $ids[$id] = $id;
                }
            }

            if ($response->next === null || $response->result === []) {
                break;
            }

            if ($response->next <= $offset) {
                throw new Failure('api-error', 'im.dialog.users.list returned a pagination cursor without progress.');
            }

            if (count($ids) >= $max) {
                return ['ids' => array_slice(array_values($ids), 0, $max), 'complete' => false, 'total' => $total];
            }

            $offset = $response->next;
        }

        ksort($ids);
        return ['ids' => array_values($ids), 'complete' => true, 'total' => $total];
    }
}
