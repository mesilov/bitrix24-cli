<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\TaskChatService;
use Bitrix24\CLI\Application\Task\Request\ListTaskChatRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:chat:list', description: 'Показать сообщения чата задачи')]
final class ListTaskChatCommand extends Command
{
    public function __construct(private readonly TaskChatService $service, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('before', null, InputOption::VALUE_REQUIRED, 'Сообщения старее MESSAGE_ID.');
        $this->addOption('after', null, InputOption::VALUE_REQUIRED, 'Сообщения новее MESSAGE_ID.');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Лимит вывода; не заменяет бюджет обхода.');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Снять только лимит вывода; бюджет остаётся конечным.');
        $this->addOption('max-messages', null, InputOption::VALUE_REQUIRED, 'Бюджет просмотра сообщений (по умолчанию 10000).');
        $this->setHelp('JSON: структура data/meta/error; столбцы TSV при --plain: id, createdDate, authorId, text. Дополнительный маршрут REST 1.0; strict-rest3 отклоняет вызов до подключения.  Доступ ограничен правами пользователя вебхука и областью доступа im. --all просматривает доступную историю до --max-messages (10000). Пример: b24cli task:chat:list 123 --limit 20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): ListTaskChatRequest => $this->mapper->listTaskChat($input),
            fn (ListTaskChatRequest $request): array => $this->service->listRoutes(),
            fn (ListTaskChatRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->service->list($request),
        );
    }
}
