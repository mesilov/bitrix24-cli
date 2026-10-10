<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

use Bitrix24\CLI\Application\Task\Page;

interface TaskHistoryGateway
{
    public function page(int $taskId, array $query, int $offset): Page;
}
