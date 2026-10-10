<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\ListTaskChecklistItemsHandler;
use Bitrix24\CLI\Application\Task\Request\ListTaskChecklistItemsRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:checklist:item:list', description: 'Показать пункты чек-листа')]
final class ListTaskChecklistItemsCommand extends Command
{
    public function __construct(private readonly ListTaskChecklistItemsHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('checklist', null, InputOption::VALUE_REQUIRED, 'ID корневого чек-листа выбранной задачи.');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV columns: id, parentId, title, isComplete. REST 1.0 companion route; strict-rest3 отклоняет вызов до подключения.  --checklist — CHECKLIST_ID корня; вывод включает все его вложенные пункты. Пример: b24cli task:checklist:item:list 123 --checklist 10');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $input,
            $output,
            'task:checklist:item:list',
            'read',
            fn (): ListTaskChecklistItemsRequest => $this->mapper->listTaskChecklistItems($input),
            fn (ListTaskChecklistItemsRequest $request): array => $this->handler->routes(),
            fn (ListTaskChecklistItemsRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
