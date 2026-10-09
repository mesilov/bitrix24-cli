<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

use Bitrix24\CLI\Application\Task\Page;

interface TaskChatGateway
{
    public function messages(int $chatId, ?int $before, ?int $after, int $limit): Page;
    public function send(int $taskId, string $text): void;
    public function update(int $messageId, string $text): void;
    public function delete(int $messageId): void;
}
