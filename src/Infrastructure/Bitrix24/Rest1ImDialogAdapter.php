<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Chat\Port\ChatDialogGateway;
use Bitrix24\CLI\Application\Failure;

final readonly class Rest1ImDialogAdapter implements ChatDialogGateway
{
    private const FIELDS = ['id', 'dialog_id', 'name', 'type', 'entity_type', 'entity_id', 'owner', 'role', 'message_count'];

    public function __construct(private ApiTransport $api)
    {
    }

    public function dialog(string $dialogId): array
    {
        try {
            $result = $this->api->call('im.dialog.get', 1, ['DIALOG_ID' => $dialogId])->result;
        } catch (Failure $failure) {
            if ($failure->errorCode === 'api-error' && ($failure->details['apiErrorCode'] ?? null) === 'ACCESS_ERROR') {
                throw new Failure('chat-unavailable', 'The chat is missing or not accessible to the connection user.', 1, ['DIALOG_ID' => $dialogId, 'apiErrorCode' => 'ACCESS_ERROR']);
            }

            throw $failure;
        }

        $id = $result['id'] ?? null;
        if ((!is_int($id) && (!is_string($id) || !ctype_digit($id))) || (int) $id < 1) {
            throw new Failure('api-error', 'im.dialog.get did not return chat metadata.', 1, ['DIALOG_ID' => $dialogId]);
        }

        $dialog = array_intersect_key($result, array_flip(self::FIELDS));
        $dialog['id'] = (int) $id;
        foreach (['dialog_id', 'name', 'type', 'entity_type', 'entity_id'] as $field) {
            $dialog[$field] = is_scalar($dialog[$field] ?? null) ? (string) $dialog[$field] : '';
        }

        return $dialog;
    }
}
