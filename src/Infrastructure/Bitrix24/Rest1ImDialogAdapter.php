<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Chat\Port\ChatDialogGateway;
use Bitrix24\CLI\Application\Failure;

final readonly class Rest1ImDialogAdapter implements ChatDialogGateway
{
    private const FIELDS = ['id', 'dialog_id', 'name', 'type', 'entity_type', 'entity_id', 'owner', 'role', 'message_count', 'last_message_id'];

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

    public function send(int $chatId, string $text): int
    {
        try {
            $result = $this->api->call('im.message.add', 1, ['DIALOG_ID' => 'chat' . $chatId, 'MESSAGE' => $text], 'write')->result;
        } catch (Failure $failure) {
            if ($failure->errorCode === 'api-error' && ($failure->details['apiErrorCode'] ?? null) === 'ACCESS_ERROR') {
                throw new Failure('chat-unavailable', 'The connection user cannot post to the chat.', 1, ['DIALOG_ID' => 'chat' . $chatId, 'apiErrorCode' => 'ACCESS_ERROR']);
            }

            throw $failure;
        }

        $id = $result[0] ?? $result['result'] ?? null;
        if ((!is_int($id) && (!is_string($id) || !ctype_digit($id))) || (int) $id < 1) {
            throw new Failure('api-error', 'im.message.add did not return a message ID; the message may have been sent.', 1, ['DIALOG_ID' => 'chat' . $chatId], true);
        }

        return (int) $id;
    }

    public function messages(int $chatId, int $lastId, int $limit): array
    {
        $result = $this->api->call('im.dialog.messages.get', 1, ['DIALOG_ID' => 'chat' . $chatId, 'LAST_ID' => $lastId, 'LIMIT' => $limit])->result;
        if ((int) ($result['chat_id'] ?? 0) !== $chatId || !is_array($result['messages'] ?? null) || !array_is_list($result['messages'])) {
            throw Failure::binding('The API did not confirm messages of the selected chat.', true);
        }

        $items = [];
        foreach ($result['messages'] as $row) {
            if (!is_array($row)) {
                throw new Failure('api-error', 'im.dialog.messages.get returned a malformed message row.');
            }

            $items[] = ResponseNormalizer::fields($row, ['id' => 'id', 'date' => 'createdDate', 'author_id' => 'authorId', 'text' => 'text', 'chat_id' => 'chatId']);
        }

        $users = [];
        foreach (is_array($result['users'] ?? null) ? $result['users'] : [] as $user) {
            if (is_array($user) && is_numeric($user['id'] ?? null) && is_string($user['name'] ?? null)) {
                $users[(int) $user['id']] = $user['name'];
            }
        }

        return ['items' => $items, 'users' => $users];
    }
}
