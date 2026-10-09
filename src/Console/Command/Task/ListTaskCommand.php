<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\ListTasksHandler;
use Bitrix24\CLI\Application\Task\Request\ListTasksRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:list', description: 'Показать доступные задачи')]
final class ListTaskCommand extends Command
{
    public function __construct(private readonly ListTasksHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('id', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'ID для серверной выборки; флаг можно повторять.');
        $this->addOption('responsible', null, InputOption::VALUE_REQUIRED, 'ID ответственного.');
        $this->addOption('project', null, InputOption::VALUE_REQUIRED, 'ID проекта.');
        $this->addOption('status', null, InputOption::VALUE_REQUIRED, 'Локальный фильтр статуса.');
        $this->addOption('due-before', null, InputOption::VALUE_REQUIRED, 'Локальный фильтр срока, ISO8601 с часовым поясом.');
        $this->addOption('where', null, InputOption::VALUE_REQUIRED, 'Локальный фильтр равенств JSON object.');
        $this->addOption('where-file', null, InputOption::VALUE_REQUIRED, 'Локальный JSON filter из PATH или -.');
        $this->addOption('select', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Выбранное поле; флаг можно повторять.');
        $this->addOption('order', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'FIELD:asc или FIELD:desc; флаг можно повторять.');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Лимит вывода; не заменяет бюджет обхода.');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Снять только лимит вывода; бюджет остаётся конечным.');
        $this->addOption('max-scan', null, InputOption::VALUE_REQUIRED, 'Максимум просмотренных задач (default 10000).');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV columns: id, title, responsibleId, groupId, status, deadline. REST 3.0.   Пример: b24cli task:list --responsible 2 --all');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $input,
            $output,
            'task:list',
            'read',
            fn (): ListTasksRequest => $this->mapper->listTasks($input),
            fn (ListTasksRequest $request): array => $this->handler->routes(),
            fn (ListTasksRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
