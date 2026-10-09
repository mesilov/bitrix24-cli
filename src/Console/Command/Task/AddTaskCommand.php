<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\AddTaskHandler;
use Bitrix24\CLI\Application\Task\Request\AddTaskRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:add', description: 'Создать задачу')]
final class AddTaskCommand extends Command
{
    public function __construct(private readonly AddTaskHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('title', null, InputOption::VALUE_REQUIRED, 'Название.');
        $this->addOption('creator', null, InputOption::VALUE_REQUIRED, 'ID постановщика.');
        $this->addOption('responsible', null, InputOption::VALUE_REQUIRED, 'ID ответственного.');
        $this->addOption('project', null, InputOption::VALUE_REQUIRED, 'ID проекта.');
        $this->addOption('deadline', null, InputOption::VALUE_REQUIRED, 'ISO8601 с часовым поясом.');
        $this->addOption('description', null, InputOption::VALUE_REQUIRED, 'Описание; пустая строка очищает его.');
        $this->addOption('description-file', null, InputOption::VALUE_REQUIRED, 'Описание из PATH или - (stdin).');
        $this->addOption('fields', null, InputOption::VALUE_REQUIRED, 'Отдельный advanced JSON object writable полей.');
        $this->addOption('fields-file', null, InputOption::VALUE_REQUIRED, 'Advanced JSON из PATH или -.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи; проверочные чтения обозначены.');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV columns: taskId, resourceId, action, changedFields. REST 3.0.  --fields/--fields-file — отдельный режим с проверкой editable метаданных. title, creatorId и responsibleId обязательны. Пример: b24cli task:add --title "Подготовить договор" --creator 1 --responsible 2 --description "Версия для клиента"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $input,
            $output,
            'task:add',
            'write',
            fn (): AddTaskRequest => $this->mapper->addTask($input),
            fn (AddTaskRequest $request): array => $this->handler->routes($request),
            fn (AddTaskRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
