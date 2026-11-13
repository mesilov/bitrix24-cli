<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Chat\Port\ChatCreateGateway;
use Bitrix24\CLI\Application\Failure;

/**
 * im.chat.add (REST 1.0, scope im): https://apidocs.bitrix24.ru/api-reference/chats/im-chat-add.html
 */
final readonly class Rest1ChatCreateAdapter implements ChatCreateGateway
{
    public function __construct(private ApiTransport $api)
    {
    }

    public function create(array $fields): int
    {
        try {
            $result = $this->api->call('im.chat.add', 1, $fields, 'write')->result;
        } catch (Failure $failure) {
            if ($failure->errorCode === 'api-error' && in_array($failure->details['apiErrorCode'] ?? null, ['ACCESS_ERROR', 'ACCESS_DENIED'], true)) {
                throw new Failure('chat-create-denied', 'The connection user cannot create chats.', 1, ['apiErrorCode' => $failure->details['apiErrorCode']]);
            }

            throw $failure;
        }

        $id = $result[0] ?? $result['result'] ?? null;
        if ((!is_int($id) && (!is_string($id) || !ctype_digit($id))) || (int) $id < 1) {
            throw new Failure('api-error', 'im.chat.add did not return a chat ID; the chat may have been created.', 1, ['TITLE' => $fields['TITLE'] ?? null], true);
        }

        return (int) $id;
    }
}
