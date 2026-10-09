<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\AddTaskChecklistItemHandler;
use Bitrix24\CLI\Application\Task\Request\AddTaskChecklistItemRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:checklist:item:add', description: 'Добавить пункт чек-листа')]
final class AddTaskChecklistItemCommand extends Command
{
    public function __construct(private readonly AddTaskChecklistItemHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('checklist', null, InputOption::VALUE_REQUIRED, 'ID корневого чек-листа выбранной задачи.');
        $this->addOption('title', null, InputOption::VALUE_REQUIRED, 'Название.');
        $this->addOption('parent', null, InputOption::VALUE_REQUIRED, 'ID родительского пункта внутри выбранного чек-листа.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи; проверочные чтения обозначены.');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV columns: taskId, resourceId, action, changedFields. REST 1.0 companion route; strict-rest3 отклоняет вызов до подключения.  --checklist — CHECKLIST_ID корневого узла. --parent — ITEM_ID внутри этого корня; без --parent пункт добавляется непосредственно в корень. Пример: b24cli task:checklist:item:add 123 --checklist 10 --title "Проверить реквизиты"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $input,
            $output,
            'task:checklist:item:add',
            'write',
            fn (): AddTaskChecklistItemRequest => $this->mapper->addTaskChecklistItem($input),
            fn (AddTaskChecklistItemRequest $request): array => $this->handler->routes(),
            fn (AddTaskChecklistItemRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
