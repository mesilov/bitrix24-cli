<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application;

final class RuntimeState
{
    public int $generation = 0;
    public float $timeout = 30.0;
    public bool $cancelled = false;
    public array $calls = [];

    /** Command metadata computed from the input (for example a resolved relative deadline), added to the result and to failure details. */
    public array $meta = [];

    /**
     * Progress and warning sink of the running command (stderr in the console); null keeps notices silent.
     *
     * @var (\Closure(string): void)|null
     */
    public ?\Closure $notice = null;

    public function begin(float $timeout): void
    {
        $this->generation++;
        $this->timeout = $timeout;
        $this->cancelled = false;
        $this->calls = [];
        $this->meta = [];
    }

    public function notice(string $message): void
    {
        if ($this->notice instanceof \Closure) {
            ($this->notice)($message);
        }
    }

    public function checkCancellation(): void
    {
        if ($this->cancelled) {
            throw new Failure('interrupted', 'Operation interrupted; an already sent request may have completed.', 130);
        }
    }
}
