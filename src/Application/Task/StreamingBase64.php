<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

final readonly class StreamingBase64
{
    public function __construct(public int $encodedLength, private \Closure $read)
    {
    }

    public function chunks(): \Generator
    {
        return ($this->read)();
    }
}
