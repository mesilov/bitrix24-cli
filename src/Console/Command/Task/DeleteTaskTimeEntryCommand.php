<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\DeleteTaskTimeEntryHandler;
use Bitrix24\CLI\Application\Task\Request\DeleteTaskTimeEntryRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:time:delete', description: 'Удалить запись времени')]
final class DeleteTaskTimeEntryCommand extends Command
{
    public function __construct(private readonly DeleteTaskTimeEntryHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('entry', null, InputOption::VALUE_REQUIRED, 'ID записи времени в задаче.');
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Подтвердить удаление без диалога; проверки доступа и принадлежности сохраняются.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи; проверочные чтения обозначены.');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV columns: taskId, resourceId, action, changedFields. REST 1.0 companion route; strict-rest3 отклоняет вызов до подключения.   Пример: b24cli task:time:delete 123 --entry 30 --force');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $input,
            $output,
            'task:time:delete',
            'delete',
            fn (): DeleteTaskTimeEntryRequest => $this->mapper->deleteTaskTimeEntry($input),
            fn (DeleteTaskTimeEntryRequest $request): array => $this->handler->routes(),
            fn (DeleteTaskTimeEntryRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
