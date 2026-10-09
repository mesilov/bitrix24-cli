<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\ListTaskHistoryHandler;
use Bitrix24\CLI\Application\Task\Request\ListTaskHistoryRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:history:list', description: 'Показать историю изменений задачи')]
final class ListTaskHistoryCommand extends Command
{
    public function __construct(private readonly ListTaskHistoryHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('params', null, InputOption::VALUE_REQUIRED, 'Ограниченный query JSON object.');
        $this->addOption('params-file', null, InputOption::VALUE_REQUIRED, 'Ограниченный query JSON из PATH или -.');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV columns: id, createdDate, userId, field, from, to. REST 1.0 companion route; strict-rest3 отклоняет вызов до подключения.  History не является перепиской. При отсутствии next/total непустой результат имеет complete=false и exit3. Пример: b24cli task:history:list 123 --json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $input,
            $output,
            'task:history:list',
            'read',
            fn (): ListTaskHistoryRequest => $this->mapper->listTaskHistory($input),
            fn (ListTaskHistoryRequest $request): array => $this->handler->routes(),
            fn (ListTaskHistoryRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
