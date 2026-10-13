<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

use Bitrix24\CLI\Application\Task\Page;

interface DeadlineTaskGateway
{
    public function validate(array $select, array $order): void;
    public function page(array $filter, array $select, array $order, int $offset): Page;
}
