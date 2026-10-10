<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application;

final class RuntimeState
{
    public float $timeout = 30.0;
    public bool $cancelled = false;
    public array $calls = [];

    public function begin(float $timeout): void
    {
        $this->timeout = $timeout;
        $this->cancelled = false;
        $this->calls = [];
    }

    public function checkCancellation(): void
    {
        if ($this->cancelled) {
            throw new Failure('interrupted', 'Operation interrupted; an already sent request may have completed.', 130);
        }
    }
}
