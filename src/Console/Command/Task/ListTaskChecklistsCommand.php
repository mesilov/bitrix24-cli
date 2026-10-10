<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\ListTaskChecklistsHandler;
use Bitrix24\CLI\Application\Task\Request\ListTaskChecklistsRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:checklist:list', description: 'Показать чек-листы')]
final class ListTaskChecklistsCommand extends Command
{
    public function __construct(private readonly ListTaskChecklistsHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV columns: id, title. REST 1.0 companion route; strict-rest3 отклоняет вызов до подключения.   Пример: b24cli task:checklist:list 123');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $input,
            $output,
            'task:checklist:list',
            'read',
            fn (): ListTaskChecklistsRequest => $this->mapper->listTaskChecklists($input),
            fn (ListTaskChecklistsRequest $request): array => $this->handler->routes(),
            fn (ListTaskChecklistsRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
