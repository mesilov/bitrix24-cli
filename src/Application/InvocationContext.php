<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application;

final readonly class InvocationContext
{
    public function __construct(public string $outputMode, public string $policy, public float $timeout, public bool $dryRun)
    {
    }
}
