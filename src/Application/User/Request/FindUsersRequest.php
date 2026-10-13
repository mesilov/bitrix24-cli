<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\User\Request;

use Bitrix24\CLI\Application\User\UserQuery;

final readonly class FindUsersRequest
{
    public function __construct(public UserQuery $query, public ?string $name, public ?string $email)
    {
    }
}
