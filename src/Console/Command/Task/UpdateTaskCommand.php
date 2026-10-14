<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\UpdateTaskHandler;
use Bitrix24\CLI\Application\Task\Request\UpdateTaskRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:update', description: 'Изменить явные поля задачи')]
final class UpdateTaskCommand extends Command
{
    public function __construct(private readonly UpdateTaskHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('title', null, InputOption::VALUE_REQUIRED, 'Название.');
        $this->addOption('creator', null, InputOption::VALUE_REQUIRED, 'ID постановщика.');
        $this->addOption('responsible', null, InputOption::VALUE_REQUIRED, 'ID ответственного.');
        $this->addOption('group-id', null, InputOption::VALUE_REQUIRED, 'Положительный ID группы/проекта (groupId).');
        $this->addOption('deadline', null, InputOption::VALUE_REQUIRED, 'ISO8601 с часовым поясом.');
        $this->addOption('description', null, InputOption::VALUE_REQUIRED, 'Описание; пустая строка очищает его.');
        $this->addOption('description-file', null, InputOption::VALUE_REQUIRED, 'Описание из PATH или - (stdin).');
        $this->addOption('tag', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Полный набор тегов (TAGS, REST 1.0); повторяйте для нескольких названий.');
        $this->addOption('clear-tags', null, InputOption::VALUE_NONE, 'Очистить все теги задачи (TAGS=[]).');
        $this->addOption('user-fields', null, InputOption::VALUE_REQUIRED, 'JSON-объект custom UF_* значений; REST 1.0, недоступен в strict-rest3.');
        $this->addOption('user-fields-file', null, InputOption::VALUE_REQUIRED, 'Custom UF_* JSON из PATH или - (stdin).');
        $this->addOption('fields', null, InputOption::VALUE_REQUIRED, 'Отдельный JSON-объект доступных для записи полей.');
        $this->addOption('fields-file', null, InputOption::VALUE_REQUIRED, 'Расширенный JSON из PATH или -.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи; проверочные чтения обозначены.');
        $this->setHelp('--user-fields/--user-fields-file задаёт точные UF_* коды и значения. Custom task:add — один REST1 add; advanced JSON в этом режиме использует TITLE/CREATED_BY/RESPONSIBLE_ID/GROUP_ID/DESCRIPTION/DEADLINE/ALLOW_TIME_TRACKING (Y/N). Custom task:update — REST1 для UF_*, обычные поля REST3; составное обновление может дать частичный результат. Пропущенные поля сохраняются, пустая строка/список явно очищает; обязательность проверяет портал. Dry-run custom режима читает схему, без записи. ' . 'JSON: структура data/meta/error; столбцы TSV при --plain: taskId, resourceId, action, changedFields. Обычные поля — REST 3.0; --tag/--clear-tags — отдельная запись REST 1.0, недоступная в strict-rest3. Теги задаются полным набором; без опции сохраняются. Частичный результат содержит TASK_ID: проверьте task:show с tags.id/tags.name и повторите только task:update с тегами.  Патч включает только явно переданные поля. --description="" очищает описание. --fields/--fields-file — отдельный режим с локальной проверкой документированных имён и типов; status не поддерживается. Пример: b24cli task:update 123 --title "Согласовать договор" --group-id 42 --tag "#ошибка". Очистка: b24cli task:update 123 --clear-tags');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): UpdateTaskRequest => $this->mapper->updateTask($input),
            fn (UpdateTaskRequest $request): array => $this->handler->routes($request),
            fn (UpdateTaskRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
