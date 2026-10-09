<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\ShowTaskAccessHandler;
use Bitrix24\CLI\Application\Task\Request\ShowTaskAccessRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:access:show', description: 'Показать права текущего пользователя')]
final class ShowTaskAccessCommand extends Command
{
    public function __construct(private readonly ShowTaskAccessHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV columns: permission, allowed. REST 3.0.   Пример: b24cli task:access:show 123');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $input,
            $output,
            'task:access:show',
            'read',
            fn (): ShowTaskAccessRequest => $this->mapper->showTaskAccess($input),
            fn (ShowTaskAccessRequest $request): array => $this->handler->routes(),
            fn (ShowTaskAccessRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
