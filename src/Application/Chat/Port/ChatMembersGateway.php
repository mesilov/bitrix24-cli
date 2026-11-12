<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat\Port;

interface ChatMembersGateway
{
    /**
     * Human members of a chat (bots and system users excluded), at most $max.
     *
     * @return array{ids: list<int>, complete: bool, total: ?int}
     */
    public function members(int $chatId, int $max): array;
}
