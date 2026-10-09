<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

interface ChecklistGateway
{
    public function nodes(int $taskId): array;
    public function add(int $taskId, int $parentId, string $title): int;
    public function update(int $taskId, int $itemId, string $title): void;
    public function complete(int $taskId, int $itemId): void;
    public function renew(int $taskId, int $itemId): void;
    public function delete(int $taskId, int $itemId): void;
}
