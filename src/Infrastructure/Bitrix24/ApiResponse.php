<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

final readonly class ApiResponse
{
    public function __construct(public array $result, public ?int $next = null, public ?int $total = null)
    {
    }
}
