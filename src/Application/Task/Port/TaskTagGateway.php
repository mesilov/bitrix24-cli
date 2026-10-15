<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

interface TaskTagGateway
{
    public function set(int $taskId, array $TAGS): void;
}
