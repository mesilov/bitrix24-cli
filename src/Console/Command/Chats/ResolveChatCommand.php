<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Chats;

use Bitrix24\CLI\Application\Chat\ChatDestinationService;
use Bitrix24\CLI\Application\Chat\ChatTarget;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Console\ChatInputMapper;
use Bitrix24\CLI\Console\CommandRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'chats:resolve', description: 'Проверить адресат чата по ссылке или типизированному ID')]
final class ResolveChatCommand extends Command
{
    public function __construct(private readonly ChatDestinationService $service, private readonly ChatInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TARGET', InputArgument::REQUIRED, 'Ссылка портала или ID: chatN, sgN, group:N, task:N.');
        $this->setHelp(<<<'HELP'
            Разбирает ссылку локально, без перехода по ней, и подтверждает чат через im.dialog.get (REST 1.0, область доступа im). Для task:N дополнительно проверяет связь задачи с чатом через tasks.task.get (REST 3.0). strict-rest3 отклоняет команду до подключения. Чтение не меняет прочитанность и не добавляет пользователя в чат.

            TARGET: chatN — групповой чат; sgN или group:N — общий чат проекта; task:N — чат задачи. Голое число отклоняется. Ссылки: /online/?IM_DIALOG=chatN|sgN (IM_MESSAGE — ID сообщения, не адресат), /company/personal/user/U/tasks/task/view/T/, /workgroups/group/G/tasks/task/view/T/, /workgroups/group/G/. Ссылка другого портала отклоняется кодом foreign-portal; недоступный чат — chat-unavailable.

            JSON data.destination: kind, target, source, chatId, groupId, taskId, messageId; data.dialog — поля im.dialog.get (id, dialog_id, name, type, entity_type, entity_id). TSV при --plain: field, value. Пример: b24cli chats:resolve 'https://portal.example/workgroups/group/234/' --json
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): ChatTarget => $this->mapper->target($input),
            fn (ChatTarget $target): array => $this->service->routes($target),
            function (ChatTarget $target): PreparedOperation {
                $this->mapper->assertPortal($target);
                return $this->service->prepare($target);
            },
        );
    }
}
