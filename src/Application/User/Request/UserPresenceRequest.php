<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\User\Request;

use Bitrix24\CLI\Application\Chat\ChatTarget;

final readonly class UserPresenceRequest
{
    /**
     * @param list<int> $userIds explicit IDs; empty when members come from the chat TARGET
     */
    public function __construct(public array $userIds, public ?ChatTarget $target = null, public int $maxMembers = 1000)
    {
    }
}
