<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

use Bitrix24\CLI\Application\Task\Page;

interface TaskResultGateway
{
    public function add(int $taskId, string $text): int;
    public function page(int $taskId, int $offset, int $limit, ?int $id = null): Page;
    public function update(int $taskId, int $id, string $text): void;
    public function delete(int $id): void;
}
