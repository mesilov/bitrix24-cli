<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\UpdateTaskHandler;
use Bitrix24\CLI\Application\Task\Request\UpdateTaskRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:deadline:set', description: 'Изменить срок задачи')]
final class SetTaskDeadlineCommand extends Command
{
    public function __construct(private readonly UpdateTaskHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('at', null, InputOption::VALUE_REQUIRED, 'Новый срок, ISO8601 с часовым поясом.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи; проверочные чтения обозначены.');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV columns: taskId, resourceId, action, changedFields. REST 3.0.   Пример: b24cli task:deadline:set 123 --at 2026-12-01T12:00:00+06:00');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $input,
            $output,
            'task:deadline:set',
            'write',
            fn (): UpdateTaskRequest => $this->mapper->setTaskDeadline($input),
            fn (UpdateTaskRequest $request): array => $this->handler->routes($request),
            fn (UpdateTaskRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
