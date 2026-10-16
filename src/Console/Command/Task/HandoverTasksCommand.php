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
        $this->addOption('max-scan', null, InputOption::VALUE_REQUIRED, 'Бюджет обхода доступных задач (по умолчанию 10000).');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Проверить состав и список, показать план без записи.');
        $this->setHelp('Примеры: b24cli task:handover --group-id 234 --responsible 14 --dry-run; руководитель: b24cli task:handover --group-id 234 --from 14 --responsible 16 --dry-run. Передаются все доступные задачи исходного исполнителя в проекте в состояниях pending, in_progress, supposedly_completed, deferred, включая задачи без срока. Оба сотрудника должны состоять в проекте; права подключения не расширяются. Неполная выборка блокирует все записи. Меняется только responsibleId. Отказ/конфликт/неизвестный исход останавливает следующие назначения; confirmed сохраняются, exit 3, отмена exit 130. Автоматического возврата нет. REST3 задач + REST1 sonet_group.user.get (разрешение sonet); strict-rest3 запрещает команду до API. JSON: data.items/meta/error; plain TSV: taskId, title, fromUserId, responsibleId, status, reason.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $request = $this->mapper->handoverTasks($input);
        return $this->runner->run($this, $input, $output, static fn (): HandoverTasksRequest => $request, fn (): array => $this->service->routes(), fn (): \Bitrix24\CLI\Application\PreparedOperation => $this->service->prepare($request));
    }
}
