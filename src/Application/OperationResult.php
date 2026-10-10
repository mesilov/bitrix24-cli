<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application;

final readonly class OperationResult
{
    public function __construct(public string $profile, public array $data, public array $meta = [], public int $exitStatus = 0)
    {
    }

    public static function mutation(int $taskId, ?int $resourceId, string $action, array $fields = []): self
    {
        return new self('mutation', ['operation' => [
            'taskId' => $taskId, 'resourceId' => $resourceId, 'action' => $action,
            'changedFields' => array_keys($fields), 'confirmed' => true,
        ]]);
    }
}
