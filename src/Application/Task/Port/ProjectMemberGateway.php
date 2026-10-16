<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

interface ProjectMemberGateway
{
    public function ids(int $groupId): array;
}
