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
        $this->addOption('group-id', null, InputOption::VALUE_REQUIRED, 'Положительный ID группы/проекта (groupId).');
        $this->addOption('deadline', null, InputOption::VALUE_REQUIRED, 'ISO8601 с часовым поясом.');
        $this->addOption('description', null, InputOption::VALUE_REQUIRED, 'Описание; пустая строка очищает его.');
        $this->addOption('description-file', null, InputOption::VALUE_REQUIRED, 'Описание из PATH или - (stdin).');
        $this->addOption('tag', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Полный набор тегов (TAGS, REST 1.0); повторяйте для нескольких названий.');
        $this->addOption('fields', null, InputOption::VALUE_REQUIRED, 'Отдельный advanced JSON object writable полей.');
        $this->addOption('fields-file', null, InputOption::VALUE_REQUIRED, 'Advanced JSON из PATH или -.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи; проверочные чтения обозначены.');
        $this->setHelp('JSON: data/meta/error envelope; plain TSV columns: taskId, resourceId, action, changedFields. Обычные поля — REST 3.0; --tag — отдельная запись REST 1.0, недоступная в strict-rest3. Теги задаются полным набором; без опции сохраняются. Частичный результат содержит TASK_ID: проверьте task:show с tags.id/tags.name и повторите только task:update с тегами.  --fields/--fields-file — отдельный режим с проверкой editable метаданных. title обязателен; creatorId/responsibleId могут быть взяты из выбранного default.task. Явные поля выше profile defaults; allowsTimeTracking по умолчанию false. Пример: b24cli task:add --title "Подготовить договор" --creator 1 --responsible 2 --group-id 42 --description "Версия для клиента" --tag "#плановая" --tag "Этап 1"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): AddTaskRequest => $this->mapper->addTask($input),
            fn (AddTaskRequest $request): array => $this->handler->routes($request),
            fn (AddTaskRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
