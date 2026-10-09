<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\ShowTaskTimeHandler;
use Bitrix24\CLI\Application\Task\Request\ShowTaskTimeRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:time:show', description: 'Показать контекст учёта времени')]
final class ShowTaskTimeCommand extends Command
{
    public function __construct(private readonly ShowTaskTimeHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV columns: field, value. REST 3.0.  elapsedTime — связанный контекст API; сумма seconds и полнота записей не подразумеваются. Записи: task:time:list. Пример: b24cli task:time:show 123');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $input,
            $output,
            'task:time:show',
            'read',
            fn (): ShowTaskTimeRequest => $this->mapper->showTaskTime($input),
            fn (ShowTaskTimeRequest $request): array => $this->handler->routes(),
            fn (ShowTaskTimeRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
