<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Request;

final readonly class ShowTaskAccessRequest
{
    public function __construct(public int $taskId)
    {
    }
}
