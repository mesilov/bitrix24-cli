<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\SetTaskParticipantsHandler;
use Bitrix24\CLI\Application\Task\Request\SetTaskParticipantsRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:participants:set', description: 'Заменить выбранные роли участников')]
final class SetTaskParticipantsCommand extends Command
{
    public function __construct(private readonly SetTaskParticipantsHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('accomplice', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Полный набор соисполнителей; повторяемый ID.');
        $this->addOption('clear-accomplices', null, InputOption::VALUE_NONE, 'Очистить набор соисполнителей.');
        $this->addOption('auditor', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Полный набор наблюдателей; повторяемый ID.');
        $this->addOption('clear-auditors', null, InputOption::VALUE_NONE, 'Очистить набор наблюдателей.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи; проверочные чтения обозначены.');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV columns: taskId, resourceId, action, changedFields. REST 1.0 companion route; strict-rest3 отклоняет вызов до подключения.  Каждая указанная роль заменяется целиком. Пропущенная роль сохраняется; --clear-auditors/--clear-accomplices явно очищает её. Пример: b24cli task:participants:set 123 --auditor 4 --clear-accomplices');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $input,
            $output,
            'task:participants:set',
            'write',
            fn (): SetTaskParticipantsRequest => $this->mapper->setTaskParticipants($input),
            fn (SetTaskParticipantsRequest $request): array => $this->handler->routes(),
            fn (SetTaskParticipantsRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
