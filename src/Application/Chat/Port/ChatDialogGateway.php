<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat\Port;

interface ChatDialogGateway
{
    /**
     * Reads chat metadata through im.dialog.get without changing read state.
     *
     * @return array<string, mixed> native im.dialog.get fields
     */
    public function dialog(string $dialogId): array;

    /**
     * Reads one page of messages older than $lastId through im.dialog.messages.get.
     *
     * Rows are normalized to id, createdDate, authorId, text, chatId but not validated.
     *
     * @return array{items: list<array<string, mixed>>, users: array<int, string>}
     */
    public function messages(int $chatId, int $lastId, int $limit): array;
}
