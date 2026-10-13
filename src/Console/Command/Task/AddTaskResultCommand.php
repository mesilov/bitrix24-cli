<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\TaskResultService;
use Bitrix24\CLI\Application\Task\Request\AddTaskResultRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:result:add', description: 'Добавить результат задачи')]
final class AddTaskResultCommand extends Command
{
    public function __construct(private readonly TaskResultService $service, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('text', null, InputOption::VALUE_REQUIRED, 'Непустой текст результата.');
        $this->addOption('text-file', null, InputOption::VALUE_REQUIRED, 'Текст из PATH или - (stdin).');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи; update/delete проверяют принадлежность результата.');
        $this->setHelp('REST 3.0; strict-rest3 поддерживается. JSON: data/meta/error envelope; plain TSV columns: taskId, resourceId, action, changedFields. Результаты задачи доступны в пределах прав подключения. Пример: b24cli task:result:add 123 --text "Договор готов"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): AddTaskResultRequest => $this->mapper->addTaskResult($input),
            fn (AddTaskResultRequest $request): array => $this->service->routes('add'),
            fn (AddTaskResultRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->service->add($request),
        );
    }
}
