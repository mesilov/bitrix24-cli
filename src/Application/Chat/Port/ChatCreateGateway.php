<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat\Port;

interface ChatCreateGateway
{
    /**
     * Creates one group chat through im.chat.add and returns its chat ID.
     *
     * $fields uses the documented im.chat.add names (TITLE, TYPE, DESCRIPTION, USERS).
     * A lost or malformed response is reported as a Failure with outcomeUnknown; the call is never repeated.
     *
     * @param array<string, mixed> $fields
     */
    public function create(array $fields): int;
}
