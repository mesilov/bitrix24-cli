<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;

final class HandoverStatusPlan
{
    public const TARGETS = ['pending', 'in_progress', 'supposedly_completed', 'completed', 'deferred'];

    public static function actions(string $target): array
    {
        return match ($target) {
            'pending' => [LifecycleAction::Disapprove, LifecycleAction::Start, LifecycleAction::Pause],
            'in_progress' => [LifecycleAction::Disapprove, LifecycleAction::Start],
            'completed' => [LifecycleAction::Complete, LifecycleAction::Approve],
            'supposedly_completed' => [LifecycleAction::Complete],
            'deferred' => [LifecycleAction::Disapprove, LifecycleAction::Defer],
            default => throw Failure::usage('Unsupported handover target status.'),
        };
    }

    public static function next(string $current, string $target): LifecycleAction
    {
        if (!in_array($current, DeadlineWindow::OPEN_STATUSES, true)) {
            throw new Failure('target-status-unavailable', 'The actual task status cannot reach the requested target through this plan.', 4);
        }

        return match ($target) {
            'pending' => match ($current) {
                'in_progress' => LifecycleAction::Pause,
                'supposedly_completed' => LifecycleAction::Disapprove,
                default => LifecycleAction::Start,
            },
            'in_progress' => $current === 'supposedly_completed' ? LifecycleAction::Disapprove : LifecycleAction::Start,
            'completed' => $current === 'supposedly_completed' ? LifecycleAction::Approve : LifecycleAction::Complete,
            'supposedly_completed' => LifecycleAction::Complete,
            'deferred' => $current === 'supposedly_completed' ? LifecycleAction::Disapprove : LifecycleAction::Defer,
            default => throw Failure::usage('Unsupported handover target status.'),
        };
    }
}
