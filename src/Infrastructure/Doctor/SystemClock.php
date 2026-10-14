<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Doctor;

use Bitrix24\CLI\Application\Clock;

final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
