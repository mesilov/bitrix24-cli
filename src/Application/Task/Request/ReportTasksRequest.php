<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Request;

use Bitrix24\CLI\Application\Task\ReportWeek;

final readonly class ReportTasksRequest
{
    public function __construct(public int $groupId, public ?int $responsibleId, public ReportWeek $week, public int $maxScan = 10000)
    {
    }
}
