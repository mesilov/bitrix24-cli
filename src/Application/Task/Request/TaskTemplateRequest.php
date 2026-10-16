<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Request;

final readonly class TaskTemplateRequest
{
    public function __construct(public ?int $templateId = null, public array $fields = [])
    {
    }
}
