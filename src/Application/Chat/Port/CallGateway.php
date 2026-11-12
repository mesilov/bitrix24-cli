<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat\Port;

interface CallGateway
{
    /**
     * Whitelisted call data; connection secrets never leave the adapter.
     *
     * @return array{status: 'ok', call: array, users: list<int>, userData: array<int, array{name: ?string, role: ?string}>, ai: array}|array{status: 'unavailable', reason: string}
     */
    public function details(int $callId): array;

    /**
     * Participants of the AI Follow-up when it exists.
     *
     * @return array{status: 'ok', participants: list<array{userId: int, name: ?string, talkedSeconds: ?int}>}|array{status: 'unavailable', reason: string}
     */
    public function followup(int $callId): array;
}
