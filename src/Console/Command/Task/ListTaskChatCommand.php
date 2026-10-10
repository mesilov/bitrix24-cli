<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\ListTaskChatHandler;
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
    public function __construct(private readonly ListTaskChatHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
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
        $this->addOption('max-messages', null, InputOption::VALUE_REQUIRED, 'Бюджет просмотра сообщений (default 10000).');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV columns: id, createdDate, authorId, text. REST 1.0 companion route; strict-rest3 отклоняет вызов до подключения.  Доступ ограничен правами пользователя webhook и scope im. --all просматривает доступную историю до --max-messages (10000). Пример: b24cli task:chat:list 123 --limit 20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $input,
            $output,
            'task:chat:list',
            'read',
            fn (): ListTaskChatRequest => $this->mapper->listTaskChat($input),
            fn (ListTaskChatRequest $request): array => $this->handler->routes(),
            fn (ListTaskChatRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
