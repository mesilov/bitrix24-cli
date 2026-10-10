<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Request;

final readonly class UpdateTaskRequest
{
    public function __construct(public int $taskId, public array $fields, public bool $advanced, public string $operation)
    {
    }
}
