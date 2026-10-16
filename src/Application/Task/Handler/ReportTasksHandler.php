<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Handler;

use Bitrix24\CLI\Application\{PreparedOperation, Route};
use Bitrix24\CLI\Application\Task\WeeklyTaskReport;
use Bitrix24\CLI\Application\Task\Request\ReportTasksRequest;

final readonly class ReportTasksHandler
{
    public function __construct(private WeeklyTaskReport $report)
    {
    }

    public function routes(): array
    {
        return [new Route('tasks.task.list', 1, 'read')];
    }

    public function prepare(ReportTasksRequest $request): PreparedOperation
    {
        return new PreparedOperation([], fn () => $this->report->collect($request));
    }
}
