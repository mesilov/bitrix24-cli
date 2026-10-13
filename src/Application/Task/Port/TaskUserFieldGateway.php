<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

interface TaskUserFieldGateway
{
    public function fields(): array;
    public function add(array $ordinary, array $custom): int;
    public function update(int $taskId, array $custom): void;
}
