<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

final readonly class ConsoleRun
{
    public function __construct(public int $status, public string $stdout, public string $stderr)
    {
    }

    public function json(): array
    {
        return json_decode($this->stdout, true, 512, JSON_THROW_ON_ERROR);
    }
}
