<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

use Bitrix24\CLI\Application\Task\LifecycleAction;

interface TaskLifecycleGateway
{
    public function transition(int $taskId, LifecycleAction $action): void;
    public function hasNativeResult(int $taskId): bool;
}
