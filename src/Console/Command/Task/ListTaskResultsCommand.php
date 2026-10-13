<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\TaskResultService;
use Bitrix24\CLI\Application\Task\Request\ListTaskResultsRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:result:list', description: 'Показать результаты задачи')]
final class ListTaskResultsCommand extends Command
{
    public function __construct(private readonly TaskResultService $service, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Лимит вывода (default 50); не заменяет бюджет обхода.');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Снять лимит вывода; бюджет остаётся конечным.');
        $this->addOption('max-scan', null, InputOption::VALUE_REQUIRED, 'Бюджет просмотра результатов (default 10000).');
        $this->setHelp('REST 3.0; strict-rest3 поддерживается. JSON: data/meta/error envelope; plain TSV columns: id, taskId, authorId, createdAt, status, text. Результаты задачи доступны в пределах прав подключения. Пример: b24cli task:result:list 123 --all');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): ListTaskResultsRequest => $this->mapper->listTaskResults($input),
            fn (ListTaskResultsRequest $request): array => $this->service->routes('list'),
            fn (ListTaskResultsRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->service->list($request),
        );
    }
}
