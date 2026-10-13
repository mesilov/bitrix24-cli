<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Request;

use Bitrix24\CLI\Application\Task\LifecycleAction;

final readonly class TaskLifecycleRequest
{
    public function __construct(public int $taskId, public LifecycleAction $action)
    {
    }
}
