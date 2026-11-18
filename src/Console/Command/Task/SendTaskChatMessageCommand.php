<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\TaskChatService;
use Bitrix24\CLI\Application\Task\Request\SendTaskChatMessageRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:chat:send', description: 'Отправить сообщение в чат задачи')]
final class SendTaskChatMessageCommand extends Command
{
    public function __construct(private readonly TaskChatService $service, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('text', null, InputOption::VALUE_REQUIRED, 'Текст.');
        $this->addOption('text-file', null, InputOption::VALUE_REQUIRED, 'Текст из PATH или - (stdin).');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи; проверочные чтения обозначены.');
        $this->setHelp('JSON: структура data/meta/error; столбцы TSV при --plain: taskId, resourceId, action, changedFields. REST 3.0. Сервер подтверждает отправку без ID, поэтому CLI проверяет привязку чата к задаче, отправляет сообщение один раз и находит его в чате: data.operation содержит messageId (он же resourceId), chatId и dialogId (chatN), readback=verified. Если отправленное сообщение не найдено однозначно, возвращается код 3 и reason=readback-unverified с кандидатами в data.readback; отправка не повторяется. Нужно право вебхука im.  Пример: b24cli task:chat:send 123 --text "Договор готов"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): SendTaskChatMessageRequest => $this->mapper->sendTaskChatMessage($input),
            fn (SendTaskChatMessageRequest $request): array => $this->service->sendRoutes(),
            fn (SendTaskChatMessageRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->service->send($request),
        );
    }
}
