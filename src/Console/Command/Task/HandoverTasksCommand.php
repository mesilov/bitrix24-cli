<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Request\HandoverTasksRequest;
use Bitrix24\CLI\Application\Task\TaskHandoverService;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:handover', description: 'Передать незавершённые задачи проекта другому участнику')]
final class HandoverTasksCommand extends Command
{
    public function __construct(private readonly TaskHandoverService $service, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('group-id', null, InputOption::VALUE_REQUIRED, 'Обязательный ID проекта.');
        $this->addOption('responsible', null, InputOption::VALUE_REQUIRED, 'Обязательный ID нового ответственного, активного участника проекта.');
        $this->addOption('from', null, InputOption::VALUE_REQUIRED, 'ID исходного исполнителя; по умолчанию текущий пользователь подключения.');
        $this->addOption('source-status', null, InputOption::VALUE_REQUIRED, 'Исходный статус: pending, in_progress, supposedly_completed, deferred.');
        $this->addOption('exclude-id', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Исключить ID задачи; флаг можно повторять.');
        $this->addOption('target-status', null, InputOption::VALUE_REQUIRED, 'Целевой статус: pending, in_progress, supposedly_completed, completed, deferred; без опции сохранить исходный.');
        $this->addOption('max-scan', null, InputOption::VALUE_REQUIRED, 'Бюджет обхода доступных задач (по умолчанию 10000).');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Проверить состав и список, показать план без записи.');
        $this->setHelp('Примеры: b24cli task:handover --group-id 234 --responsible 14 --source-status deferred --exclude-id 92888 --target-status deferred --dry-run; руководитель: b24cli task:handover --group-id 234 --from 14 --responsible 16 --source-status deferred --exclude-id 92888 --target-status deferred --dry-run. Без фильтра передаются все доступные задачи исходного исполнителя в проекте в pending, in_progress, supposedly_completed, deferred, включая задачи без срока. --source-status ограничивает один открытый статус; повторяемый --exclude-id исключает ID. Без --target-status исходный статус сохраняется и проверяется. Цели: pending, in_progress, supposedly_completed, completed, deferred; declined не поддержан штатным действием. Статус меняется только lifecycle-действиями с актуальными правами, контролем и обязательным native result. Complete может затронуть подзадачи по серверной настройке autocompleteSubTasks; CLI не меняет настройки и не создаёт result. Оба сотрудника должны состоять в проекте; права подключения не расширяются. Неполная выборка блокирует все записи. Confirmed означает проверенные ответственного, проект и ожидаемый статус. Ошибка/конфликт/неизвестный исход останавливает пакет; назначение и переход отражаются отдельно, exit 3, отмена exit 130. Автоматического возврата и повтора нет. Dry-run показывает ID и условный план; доступность будущих действий после назначения не гарантируется. REST3 задач + REST1 sonet_group.user.get (sonet) и lifecycle; strict-rest3 запрещает команду до API. JSON: data.items/meta/error, transitionActions; plain TSV: taskId, title, fromUserId, responsibleId, status, reason, sourceStatus, targetStatus, actualStatus, assignmentStatus, transitionStatus, verificationStatus.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $request = $this->mapper->handoverTasks($input);
        return $this->runner->run($this, $input, $output, static fn (): HandoverTasksRequest => $request, fn (): array => $this->service->routes($request), fn (): \Bitrix24\CLI\Application\PreparedOperation => $this->service->prepare($request));
    }
}
