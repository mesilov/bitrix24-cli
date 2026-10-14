<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\TaskResultService;
use Bitrix24\CLI\Application\Task\Request\UpdateTaskResultRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:result:update', description: 'Изменить текст результата задачи')]
final class UpdateTaskResultCommand extends Command
{
    public function __construct(private readonly TaskResultService $service, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('result', null, InputOption::VALUE_REQUIRED, 'ID результата выбранной задачи из task:result:list.');
        $this->addOption('text', null, InputOption::VALUE_REQUIRED, 'Непустой текст результата.');
        $this->addOption('text-file', null, InputOption::VALUE_REQUIRED, 'Текст из PATH или - (stdin).');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи; обновление/удаление проверяют принадлежность результата.');
        $this->setHelp('REST 3.0; strict-rest3 поддерживается. JSON: структура data/meta/error; столбцы TSV при --plain: taskId, resourceId, action, changedFields. Результаты задачи доступны в пределах прав подключения. Пример: b24cli task:result:update 123 --result 17 --text "Договор проверен"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): UpdateTaskResultRequest => $this->mapper->updateTaskResult($input),
            fn (UpdateTaskResultRequest $request): array => $this->service->routes('update'),
            fn (UpdateTaskResultRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->service->update($request),
        );
    }
}
