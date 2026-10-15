<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\TaskChatService;
use Bitrix24\CLI\Application\Task\Request\UpdateTaskChatMessageRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:chat:update', description: 'Изменить сообщение чата задачи')]
final class UpdateTaskChatMessageCommand extends Command
{
    public function __construct(private readonly TaskChatService $service, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('message', null, InputOption::VALUE_REQUIRED, 'ID сообщения в выбранном чате задачи.');
        $this->addOption('text', null, InputOption::VALUE_REQUIRED, 'Текст.');
        $this->addOption('text-file', null, InputOption::VALUE_REQUIRED, 'Текст из PATH или - (stdin).');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи; проверочные чтения обозначены.');
        $this->setHelp('JSON: структура data/meta/error; столбцы TSV при --plain: taskId, resourceId, action, changedFields. Дополнительный маршрут REST 1.0; strict-rest3 отклоняет вызов до подключения.   Пример: b24cli task:chat:update 123 --message 900 --text "Договор согласован"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): UpdateTaskChatMessageRequest => $this->mapper->updateTaskChatMessage($input),
            fn (UpdateTaskChatMessageRequest $request): array => $this->service->updateRoutes(),
            fn (UpdateTaskChatMessageRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->service->update($request),
        );
    }
}
