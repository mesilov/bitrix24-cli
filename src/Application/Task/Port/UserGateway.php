<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

interface UserGateway
{
    /** Return selected user fields keyed by ID; omitted users/fields stay absent. */
    public function byIds(array $ids, array $fields): array;
}
