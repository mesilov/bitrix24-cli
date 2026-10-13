<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\User\Port;

use Bitrix24\CLI\Application\User\UserPage;

interface UserDirectoryGateway
{
    public function current(): array;
    public function page(array $filter, int $start): UserPage;
    public function byId(int $id): ?array;
}
