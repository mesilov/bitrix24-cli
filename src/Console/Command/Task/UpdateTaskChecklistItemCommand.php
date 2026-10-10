<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\UpdateTaskChecklistItemHandler;
use Bitrix24\CLI\Application\Task\Request\UpdateTaskChecklistItemRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:checklist:item:update', description: 'Изменить заголовок пункта')]
final class UpdateTaskChecklistItemCommand extends Command
{
    public function __construct(private readonly UpdateTaskChecklistItemHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('item', null, InputOption::VALUE_REQUIRED, 'ID пункта, не корневого чек-листа.');
        $this->addOption('title', null, InputOption::VALUE_REQUIRED, 'Название.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи; проверочные чтения обозначены.');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV columns: taskId, resourceId, action, changedFields. REST 1.0 companion route; strict-rest3 отклоняет вызов до подключения.   Пример: b24cli task:checklist:item:update 123 --item 11 --title "Уточнить реквизиты"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $input,
            $output,
            'task:checklist:item:update',
            'write',
            fn (): UpdateTaskChecklistItemRequest => $this->mapper->updateTaskChecklistItem($input),
            fn (UpdateTaskChecklistItemRequest $request): array => $this->handler->routes(),
            fn (UpdateTaskChecklistItemRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
