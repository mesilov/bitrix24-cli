<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat\Port;

interface ChatReadGateway
{
    /**
     * @return array{lastMessageId: int, readers: array<int, array{messageId: int, date: ?string}>}
     */
    public function readState(int $chatId): array;
}
