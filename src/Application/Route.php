<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application;

final readonly class Route
{
    public function __construct(public string $method, public int $version, public string $effect = 'read')
    {
    }
}
