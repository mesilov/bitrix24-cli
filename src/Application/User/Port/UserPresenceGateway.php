<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\User\Port;

interface UserPresenceGateway
{
    /**
     * Messenger users by ID; unknown IDs are omitted. Online flags are not returned.
     *
     * @param list<int> $ids
     * @return array<int, array{id: int, name: ?string, lastActivityDate: ?string, absent: mixed}>
     */
    public function presence(array $ids): array;
}
