<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

use Bitrix24\CLI\Application\Task\Page;

interface TimeEntryGateway
{
    public function add(int $taskId, int $seconds, ?string $text): int;
    public function page(int $taskId, array $query, int $page): Page;
    public function update(int $taskId, int $entryId, int $seconds, ?string $text): void;
    public function delete(int $taskId, int $entryId): void;
}
