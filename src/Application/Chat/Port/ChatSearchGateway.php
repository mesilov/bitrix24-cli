<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat\Port;

interface ChatSearchGateway
{
    /**
     * Searches chats by title and participant names through im.search.chat.list.
     *
     * @return array{items: list<array{id: int, name: string, type: string}>, next: ?int}
     */
    public function chats(string $find, int $offset, int $limit): array;

    /**
     * Searches workgroups and projects by a name fragment through sonet_group.get.
     *
     * @return array{items: list<array{id: int, name: string, project: bool}>, next: ?int}
     */
    public function groups(string $find, int $start): array;
}
