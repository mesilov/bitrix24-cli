<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\User;

final readonly class UserPage
{
    public function __construct(public array $items, public ?int $next, public ?int $total = null)
    {
    }
}
