<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

final class Plan
{
    public static function step(?int $taskId, string $method, int $version, string $effect, array $fields = [], int $step = 1): array
    {
        return ['taskId' => $taskId, 'step' => $step, 'method' => $method, 'apiVersion' => $version . '.0', 'effect' => $effect, 'fields' => $fields];
    }
}
