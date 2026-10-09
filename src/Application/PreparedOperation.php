<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application;

final readonly class PreparedOperation
{
    public function __construct(public array $plan, public \Closure $execute, public string $target = '')
    {
    }
}
