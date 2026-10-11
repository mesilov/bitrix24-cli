<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\ShowTaskHandler;
use Bitrix24\CLI\Application\Task\Request\ShowTaskRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:show', description: 'Показать карточку задачи')]
final class ShowTaskCommand extends Command
{
    public function __construct(private readonly ShowTaskHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('select', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Выбранное поле; флаг можно повторять.');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV columns: field, value. Карточка: REST 3.0. Выбранные auditors/accomplices: явное чтение ID через REST 1.0 и user.get; user_brief для имён, user_basic/user для email. Поддержанные поля: id/name/email/gender/externalAuthId. Недоступные данные: partial (exit 3). strict-rest3 отклоняет обогащение до подключения. Примеры: b24cli task:show 123; b24cli task:show 123 --select title --select auditors --json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $input,
            $output,
            'task:show',
            'read',
            fn (): ShowTaskRequest => $this->mapper->showTask($input),
            fn (ShowTaskRequest $request): array => $this->handler->routes($request),
            fn (ShowTaskRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
