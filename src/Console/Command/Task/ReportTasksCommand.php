<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\ReportTasksHandler;
use Bitrix24\CLI\Application\Task\ReportWeekResolver;
use Bitrix24\CLI\Application\Task\Request\ReportTasksRequest;
use Bitrix24\CLI\Console\{CommandRunner, TaskInputMapper};
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:report', description: 'Показать недельный отчёт по проекту')]
final class ReportTasksCommand extends Command
{
    public function __construct(private readonly ReportTasksHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner, private readonly ReportWeekResolver $weeks)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        foreach ([
            'group-id' => 'Обязательный ID проекта. Без --responsible отчёт по всему проекту.',
            'responsible' => 'Сузить все показатели до текущего ответственного внутри проекта.',
            'week' => 'this-week, last-week или дата YYYY-MM-DD внутри недели; по умолчанию last-week.',
            'timezone' => 'Часовой пояс IANA; по умолчанию TZ или часовой пояс PHP, показан в отчёте.',
            'max-scan' => 'Общий бюджет просмотренных строк четырёх секций; по умолчанию 10000.',
        ] as $name => $description) {
            $this->addOption($name, null, InputOption::VALUE_REQUIRED, $description . ' Задайте один раз.');
        }

        $this->setHelp('Четыре показателя: закрытые за неделю, текущие задачи на контроле, новые за неделю и текущее количество in_progress. Неделя с понедельника, [from,to), текущая ограничена временем начала отчёта. Контроль/работа отражают текущее чтение даже за прошлую неделю; исторический и атомарный снимок не обещаются. Повторно открытые не входят в закрытые. Область — доступные подключению задачи проекта. REST1 tasks.task.list, фильтры REAL_STATUS и даты создания/закрытия; strict-rest3 запрещает до API. При partial count=null, observedCount сохраняется, exit 3. JSON: data/meta/error; plain TSV: section, recordType, id, title, responsibleId, groupId, status, createdDate, closedDate, count, observedCount, complete, reason; summary для каждой секции. Пример: b24cli task:report --group-id 234 --responsible 14 --week last-week --timezone Asia/Bishkek --json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): ReportTasksRequest => $this->mapper->reportTasks($input, $this->weeks),
            fn (): array => $this->handler->routes(),
            fn (ReportTasksRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
