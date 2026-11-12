<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Chat\CallMessage;
use Bitrix24\CLI\Application\Chat\Port\ChatReadGateway;
use Bitrix24\CLI\Application\Failure;

final readonly class Rest1ChatReadAdapter implements ChatReadGateway
{
    public function __construct(private ApiTransport $api)
    {
    }

    public function readState(int $chatId): array
    {
        $result = $this->api->call('im.dialog.get', 1, ['DIALOG_ID' => 'chat' . $chatId])->result;
        $last = CallMessage::positive($result['last_message_id'] ?? null);
        if ((int) ($result['id'] ?? 0) !== $chatId || $last === null || !is_array($result['readed_list'] ?? null)) {
            throw Failure::binding('The API did not confirm the read state of the selected chat.', true);
        }

        $readers = [];
        foreach ($result['readed_list'] as $row) {
            $userId = is_array($row) ? CallMessage::positive($row['user_id'] ?? null) : null;
            $messageId = $row['message_id'] ?? null;
            if ($userId !== null && (is_int($messageId) || (is_string($messageId) && ctype_digit($messageId)))) {
                $readers[$userId] = ['messageId' => (int) $messageId, 'date' => is_string($row['date'] ?? null) && $row['date'] !== '' ? $row['date'] : null];
            }
        }

        ksort($readers);
        return ['lastMessageId' => $last, 'readers' => $readers];
    }
}
