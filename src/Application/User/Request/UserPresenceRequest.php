<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\User\Request;

final readonly class UserPresenceRequest
{
    /**
     * @param list<int> $userIds
     */
    public function __construct(public array $userIds)
    {
    }
}
