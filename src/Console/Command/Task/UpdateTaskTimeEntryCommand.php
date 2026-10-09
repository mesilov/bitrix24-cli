<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\UpdateTaskTimeEntryHandler;
use Bitrix24\CLI\Application\Task\Request\UpdateTaskTimeEntryRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:time:update', description: 'Исправить запись времени')]
final class UpdateTaskTimeEntryCommand extends Command
{
    public function __construct(private readonly UpdateTaskTimeEntryHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('entry', null, InputOption::VALUE_REQUIRED, 'ID записи времени в задаче.');
        $this->addOption('seconds', null, InputOption::VALUE_REQUIRED, 'Положительное число секунд.');
        $this->addOption('text', null, InputOption::VALUE_REQUIRED, 'Текст.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи; проверочные чтения обозначены.');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV columns: taskId, resourceId, action, changedFields. REST 1.0 companion route; strict-rest3 отклоняет вызов до подключения.   Пример: b24cli task:time:update 123 --entry 30 --seconds 1800');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $input,
            $output,
            'task:time:update',
            'write',
            fn (): UpdateTaskTimeEntryRequest => $this->mapper->updateTaskTimeEntry($input),
            fn (UpdateTaskTimeEntryRequest $request): array => $this->handler->routes(),
            fn (UpdateTaskTimeEntryRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
