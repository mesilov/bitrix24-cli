<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

use Bitrix24\CLI\Application\Task\Page;

interface WeeklyReportTaskGateway
{
    public function reportPage(array $filter, int $offset): Page;
}
