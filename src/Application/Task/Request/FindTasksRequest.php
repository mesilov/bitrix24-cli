<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Request;

final readonly class FindTasksRequest
{
    public function __construct(public \Bitrix24\CLI\Application\Task\TaskQuery $query, public string $title)
    {
    }
}
