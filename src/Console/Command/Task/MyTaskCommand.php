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

#[AsCommand(name: 'task:my', description: 'Показать задачи выбранного исполнителя')]
final class MyTaskCommand extends Command
{
    public function __construct(private readonly ListTasksHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('user', null, InputOption::VALUE_REQUIRED, 'Обязательный положительный ID исполнителя (responsibleId); права подключения не меняются.');
        $this->addOption('open', null, InputOption::VALUE_NONE, 'Только pending, in_progress, supposedly_completed и deferred.');
        $this->addOption('due-before', null, InputOption::VALUE_REQUIRED, 'Дедлайн строго раньше ISO8601 с часовым поясом; без дедлайна задача исключается.');
        $this->addOption('max-scan', null, InputOption::VALUE_REQUIRED, 'Максимум просмотренных задач (по умолчанию 10000).');
        $this->setHelp('Пример: b24cli task:my --user 2 --open --due-before 2026-10-20T00:00:00+06:00 --json. --user задаёт исполнителя, не пользователя подключения и не расширяет его права. Фильтры сочетаются через AND; без --open доступны все состояния. REST 3.0, включая strict-rest3; только чтение. Порядок id DESC, лимит вывода 50, бюджет обхода 10000. meta.complete=false/exit 3 означает неполную выборку; увеличьте --max-scan. JSON: data/meta/error; plain TSV: id, title, responsibleId, groupId, status, deadline. Команда помогает выбрать следующую работу (EMP-01); приоритет пользователь определяет сам.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $request = $this->mapper->myTasks($input);
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): ListTasksRequest => $request,
            fn (ListTasksRequest $request): array => $this->handler->routes($request),
            fn (ListTasksRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
