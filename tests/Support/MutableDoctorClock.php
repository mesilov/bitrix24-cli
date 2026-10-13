<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

use Bitrix24\CLI\Application\Clock;

final class MutableDoctorClock implements Clock
{
    public \DateTimeImmutable $time;

    public function __construct()
    {
        $this->time = new \DateTimeImmutable('2026-10-13T00:00:00Z');
    }

    public function now(): \DateTimeImmutable
    {
        return $this->time;
    }
}
