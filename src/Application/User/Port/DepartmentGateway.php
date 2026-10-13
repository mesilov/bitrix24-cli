<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\User\Port;

interface DepartmentGateway
{
    public function get(int $id): array;
}
