<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

interface CurrentUserGateway
{
    public function id(): int;
}
