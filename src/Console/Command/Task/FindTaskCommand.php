<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\FindTasksHandler;
use Bitrix24\CLI\Application\Task\Request\FindTasksRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:find', description: 'Найти задачи по буквальной подстроке title')]
final class FindTaskCommand extends Command
{
    public function __construct(private readonly FindTasksHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('title', null, InputOption::VALUE_REQUIRED, 'Название.');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Лимит вывода; не заменяет бюджет обхода.');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Снять только лимит вывода; бюджет остаётся конечным.');
        $this->addOption('max-scan', null, InputOption::VALUE_REQUIRED, 'Максимум просмотренных задач (default 10000).');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV columns: id, title. REST 3.0. Поиск title без регистра Unicode; scan budget независим от лимита вывода.  Пример: b24cli task:find --title "договор" --json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $input,
            $output,
            'task:find',
            'read',
            fn (): FindTasksRequest => $this->mapper->findTasks($input),
            fn (FindTasksRequest $request): array => $this->handler->routes(),
            fn (FindTasksRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
