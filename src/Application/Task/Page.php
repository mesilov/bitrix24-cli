<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

final readonly class Page
{
    public function __construct(public array $items, public int|string|null $next = null, public bool $complete = true)
    {
    }
}
