<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\TaskResultService;
use Bitrix24\CLI\Application\Task\Request\DeleteTaskResultRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:result:delete', description: 'Удалить результат задачи')]
final class DeleteTaskResultCommand extends Command
{
    public function __construct(private readonly TaskResultService $service, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('result', null, InputOption::VALUE_REQUIRED, 'ID результата выбранной задачи из task:result:list.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи; update/delete проверяют принадлежность результата.');
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Подтвердить удаление без диалога; проверка принадлежности сохраняется.');
        $this->setHelp('REST 3.0; strict-rest3 поддерживается. JSON: data/meta/error envelope; plain TSV columns: taskId, resourceId, action, changedFields. Результаты задачи доступны в пределах прав подключения. Пример: b24cli task:result:delete 123 --result 17 --force');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): DeleteTaskResultRequest => $this->mapper->deleteTaskResult($input),
            fn (DeleteTaskResultRequest $request): array => $this->service->routes('delete'),
            fn (DeleteTaskResultRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->service->delete($request),
        );
    }
}
