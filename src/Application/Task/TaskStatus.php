<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;

enum TaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case SupposedlyCompleted = 'supposedly_completed';
    case Completed = 'completed';
    case Deferred = 'deferred';
    case Declined = 'declined';

    public static function validateFilter(mixed $value): string
    {
        if (!is_string($value) || self::tryFrom($value) === null) {
            throw Failure::usage('Use a logical task status: ' . implode(', ', array_column(self::cases(), 'value')) . '.');
        }

        return $value;
    }
}
