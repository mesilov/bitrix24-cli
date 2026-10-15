<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Request\TaskTemplateRequest;
use Bitrix24\CLI\Application\Task\TaskTemplateService;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskTemplateInputMapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class TaskTemplateCommand extends Command
{
    protected const string ACTION = '';

    public function __construct(private readonly TaskTemplateService $service, private readonly TaskTemplateInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $action = static::ACTION;
        if (in_array($action, ['get', 'update', 'delete'], true)) {
            $this->addArgument('TEMPLATE_ID', InputArgument::REQUIRED, 'Положительный ID шаблона задачи.');
        }

        if (in_array($action, ['add', 'update'], true)) {
            $this->addOption('fields', null, InputOption::VALUE_REQUIRED, 'JSON-объект полей шаблона с нативными именами REST 1.0.');
            $this->addOption('fields-file', null, InputOption::VALUE_REQUIRED, 'JSON из PATH или - (перенаправленный stdin).');
            foreach (['title' => 'Название (TITLE).', 'description' => 'Описание (DESCRIPTION); пустая строка очищает.', 'creator' => 'ID постановщика (CREATED_BY).', 'responsible' => 'ID исполнителя (RESPONSIBLE_ID).', 'group-id' => 'ID проекта (GROUP_ID); для 0 используйте JSON.'] as $option => $description) {
                $this->addOption($option, null, InputOption::VALUE_REQUIRED, $description);
            }
        }

        if (in_array($action, ['add', 'update', 'delete'], true)) {
            $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи; delete читает цель, add/update работают без API.');
        }

        if ($action === 'delete') {
            $this->addOption('force', null, InputOption::VALUE_NONE, 'Подтвердить запрос удаления без интерактивного вопроса.');
        }

        $example = match ($action) {
            'get' => 'task:template:show 139',
            'fields' => 'task:template:fields:list',
            'add' => 'task:template:add --title "Типовая задача" --creator 1 --responsible 42',
            'update' => 'task:template:update 139 --description "Проверить реквизиты"',
            default => 'task:template:delete 139 --dry-run',
        };
        $this->setHelp('REST 1.0, разрешение task; strict-rest3 запрещает команду до API. JSON: data/meta/error; show — data.template, fields:list — data.fields, изменения — data.operation с templateId. TSV: field,value для чтения; templateId,action,changedFields,confirmed для изменений. Поля записи: TITLE, DESCRIPTION, CREATED_BY, RESPONSIBLE_ID, GROUP_ID, PARENT_ID, ACCOMPLICES, AUDITORS, ALLOW_CHANGE_DEADLINE, ALLOW_TIME_TRACKING, TASK_CONTROL, ADD_IN_REPORT. ID — целые JSON-числа, участники — списки целых чисел, флаги — Y/N. Создание требует TITLE/CREATED_BY/RESPONSIBLE_ID; Изменение сохраняет пропущенные поля. Дубли источников запрещены. Настройки default.task не применяются. Поиск шаблонов, создание задач по шаблону, расписания и чек-листы не поддержаны. Удаление требует --force в автоматизации; false от API не доказывает удаление: exit 3, confirmed=false, visibleAfter. Проверьте портал перед повтором. Пример: b24cli ' . $example);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): TaskTemplateRequest => $this->mapper->map($input, static::ACTION),
            fn (): array => $this->service->routes(static::ACTION),
            fn (TaskTemplateRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->service->prepare(static::ACTION, $request),
        );
    }
}
