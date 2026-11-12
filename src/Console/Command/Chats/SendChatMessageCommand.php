<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Chats;

use Bitrix24\CLI\Application\Chat\ChatSendService;
use Bitrix24\CLI\Application\Chat\ChatTarget;
use Bitrix24\CLI\Application\Chat\Request\SendChatMessageRequest;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Console\ChatInputMapper;
use Bitrix24\CLI\Console\CommandRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'chats:send', description: 'Отправить сообщение в чат по ссылке, ID или точному названию')]
final class SendChatMessageCommand extends Command
{
    public function __construct(private readonly ChatSendService $service, private readonly ChatInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TARGET', InputArgument::OPTIONAL, 'Ссылка портала или ID: chatN, sgN, group:N, task:N.');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Точное название чата, проекта или заголовок задачи вместо TARGET.');
        $this->addOption('text', null, InputOption::VALUE_REQUIRED, 'Текст сообщения.');
        $this->addOption('text-file', null, InputOption::VALUE_REQUIRED, 'Текст из PATH или - (stdin).');
        $this->addOption('max-scan', null, InputOption::VALUE_REQUIRED, 'Бюджет поиска по названию на каждый источник (по умолчанию 10000).');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Проверить адресат и показать план без отправки.');
        $this->setHelp(<<<'HELP'
            Отправляет одно текстовое сообщение через im.message.add (REST 1.0, область доступа im). Адресат проверяется до записи: TARGET — как в chats:resolve; --name — как в chats:find, запись разрешена только при data.selection.status = unique. Иначе ничего не отправляется: chat-not-found, chat-ambiguous (кандидаты в error.details.candidates), chat-unavailable, search-incomplete. strict-rest3 отклоняет команду до подключения.

            --dry-run выполняет проверочные чтения адресата и показывает план без отправки. Запись выполняется один раз. Если ответ потерян, команда завершается с meta.outcomeUnknown = true и не повторяет отправку: проверьте chats:messages:list перед повтором. После отправки CLI читает сообщение по ID и сверяет чат: data.operation.readback = verified; иначе exit 3 (missing, failed, chat-mismatch), а messageId сохраняется.

            JSON data: destination, operation (action, target, chatId, messageId, confirmed, readback), readback. TSV при --plain: target, chatId, messageId, confirmed, readback. Пример: b24cli chats:send --name 'Команда проекта' --text 'Сегодня меня не будет' --json
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): SendChatMessageRequest => $this->mapper->send($input),
            fn (SendChatMessageRequest $request): array => $this->service->routes($request),
            function (SendChatMessageRequest $request): PreparedOperation {
                if ($request->target instanceof ChatTarget) {
                    $this->mapper->assertPortal($request->target);
                }

                return $this->service->prepare($request);
            },
        );
    }
}
