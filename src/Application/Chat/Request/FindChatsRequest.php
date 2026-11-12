<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat\Request;

final readonly class FindChatsRequest
{
    public function __construct(public string $name, public int $maxScan, public ?int $limit)
    {
    }
}
