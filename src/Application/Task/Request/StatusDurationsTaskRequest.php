<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Request;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\CalendarInstant;

final readonly class StatusDurationsTaskRequest
{
    public function __construct(public int $taskId, public \DateTimeImmutable $asOf)
    {
    }

    public static function at(int $taskId, mixed $value, \DateTimeImmutable $now): self
    {
        $date = $value === null ? $now : CalendarInstant::parse($value);
        if (!$date instanceof \DateTimeImmutable || $date > $now) {
            throw Failure::usage('--as-of requires ISO 8601 with timezone, not in the future.');
        }

        return new self($taskId, $date);
    }
}
