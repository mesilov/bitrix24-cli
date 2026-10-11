<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\TimeEntryService;
use Bitrix24\CLI\Application\Task\Request\ListTaskTimeEntriesRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:time:list', description: 'Показать записи времени')]
final class ListTaskTimeEntriesCommand extends Command
{
    public function __construct(private readonly TimeEntryService $service, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('params', null, InputOption::VALUE_REQUIRED, 'Ограниченный query JSON object.');
        $this->addOption('params-file', null, InputOption::VALUE_REQUIRED, 'Ограниченный query JSON из PATH или -.');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV columns: id, userId, seconds, text, createdDate. REST 1.0 companion route; strict-rest3 отклоняет вызов до подключения.  --params допускает order/filter/select/params.NAV_PARAMS. iNumPage ограничивает scope выбранной страницей. По умолчанию просматриваются страницы до конца или бюджета 10000. Пример: b24cli task:time:list 123');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): ListTaskTimeEntriesRequest => $this->mapper->listTaskTimeEntries($input),
            fn (ListTaskTimeEntriesRequest $request): array => $this->service->listRoutes(),
            fn (ListTaskTimeEntriesRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->service->list($request),
        );
    }
}
