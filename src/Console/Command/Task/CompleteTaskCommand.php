<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\TaskLifecycleService;
use Bitrix24\CLI\Application\Task\Request\TaskLifecycleRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:complete', description: 'Завершить задачу или передать на контроль')]
final class CompleteTaskCommand extends Command
{
    public function __construct(private readonly TaskLifecycleService $service, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи; проверочные чтения обозначены.');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV columns: taskId, action, confirmed, status, needsControl, requireResult. REST 1.0 lifecycle; supposedly_completed — ожидание контроля, completed — завершено; strict-rest3 отклоняет вызов до подключения.   Пример: b24cli task:complete 123');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): TaskLifecycleRequest => $this->mapper->lifecycleRequest($input, \Bitrix24\CLI\Application\Task\LifecycleAction::Complete),
            fn (TaskLifecycleRequest $request): array => $this->service->routes($request),
            fn (TaskLifecycleRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->service->prepare($request),
        );
    }
}
