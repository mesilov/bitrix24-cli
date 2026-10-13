<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\User;

final readonly class UserQuery
{
    public function __construct(public ?int $limit = 50, public int $maxScan = 1000)
    {
    }
}
