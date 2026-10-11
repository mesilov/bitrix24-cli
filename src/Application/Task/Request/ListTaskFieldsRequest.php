<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Request;

final readonly class ListTaskFieldsRequest
{
    public function __construct(public ?string $name)
    {
    }
}
