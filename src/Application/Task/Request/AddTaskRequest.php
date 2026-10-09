<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Request;

final readonly class AddTaskRequest
{
    public function __construct(public array $fields, public bool $advanced)
    {
    }
}
