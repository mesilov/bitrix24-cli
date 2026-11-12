<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

use Bitrix24\CLI\Application\Task\Page;

interface TaskChatGateway
{
    public function messages(int $chatId, ?int $before, ?int $after, int $limit): Page;

    /** Latest message ID of the chat from im.dialog.get; 0 for an empty chat. Does not change read state. */
    public function lastMessageId(int $chatId): int;

    public function send(int $taskId, string $text): void;
    public function update(int $messageId, string $text): void;
    public function delete(int $messageId): void;
}
