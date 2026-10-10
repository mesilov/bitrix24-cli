<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\Page;
use Bitrix24\CLI\Application\Task\Port\TaskChatGateway;

final readonly class TaskChatAdapter implements TaskChatGateway
{
    public function __construct(private ApiTransport $api)
    {
    }

    public function messages(int $chatId, ?int $before, ?int $after, int $limit): Page
    {
        $params = ['DIALOG_ID' => 'chat' . $chatId, 'LIMIT' => $limit];
        if ($before !== null) {
            $params['LAST_ID'] = $before;
        }

        if ($after !== null) {
            $params['FIRST_ID'] = $after;
        }

        $result = $this->api->call('im.dialog.messages.get', 1, $params)->result;
        $payload = array_is_list($result) && is_array($result[0] ?? null) ? $result[0] : $result;
        if ((int) ($payload['chat_id'] ?? 0) !== $chatId || !is_array($payload['messages'] ?? null)) {
            throw Failure::binding('The API did not confirm the selected task chat.', true);
        }

        $items = array_map(static fn (array $row): array => ResponseNormalizer::fields($row, [
            'id' => 'id', 'date' => 'createdDate', 'author_id' => 'authorId', 'authorId' => 'authorId',
            'text' => 'text', 'chat_id' => 'chatId', 'chatId' => 'chatId',
        ]), $payload['messages']);
        $ids = array_column($items, 'id');
        return new Page($items, count($items) >= $limit && $ids !== [] ? ($after === null ? min($ids) : max($ids)) : null);
    }

    public function send(int $taskId, string $text): void
    {
        ResponseNormalizer::ack($this->api->call('tasks.task.chat.message.send', 3, ['fields' => ['taskId' => $taskId, 'text' => $text]], 'write')->result);
    }

    public function update(int $messageId, string $text): void
    {
        ResponseNormalizer::ack($this->api->call('im.message.update', 1, ['MESSAGE_ID' => $messageId, 'MESSAGE' => $text], 'write')->result);
    }

    public function delete(int $messageId): void
    {
        ResponseNormalizer::ack($this->api->call('im.message.delete', 1, ['MESSAGE_ID' => $messageId], 'delete')->result);
    }
}
