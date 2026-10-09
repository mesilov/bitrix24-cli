<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Request;

final readonly class ListTaskChatRequest
{
    public function __construct(public int $taskId, public ?int $before, public ?int $after, public ?int $limit, public int $maxMessages)
    {
    }
}
