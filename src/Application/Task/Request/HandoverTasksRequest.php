<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Request;

final readonly class HandoverTasksRequest
{
    public function __construct(public int $groupId, public int $responsibleId, public ?int $fromUserId = null, public int $maxScan = 10000, public ?string $sourceStatus = null, public array $excludeIds = [], public ?string $targetStatus = null)
    {
    }
}
