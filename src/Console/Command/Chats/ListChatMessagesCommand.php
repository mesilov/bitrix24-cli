<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Chats;

use Bitrix24\CLI\Application\Chat\ChatMessagesService;
use Bitrix24\CLI\Application\Chat\Request\ListChatMessagesRequest;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Console\ChatInputMapper;
use Bitrix24\CLI\Console\CommandRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'chats:messages:list', description: 'Показать сообщения чата за период')]
final class ListChatMessagesCommand extends Command
{
    public function __construct(private readonly ChatMessagesService $service, private readonly ChatInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TARGET', InputArgument::REQUIRED, 'Ссылка портала или ID: chatN, sgN, group:N, task:N.');
        $this->addOption('period', null, InputOption::VALUE_REQUIRED, 'Календарный период: yesterday (вчерашние сутки в --timezone).');
        $this->addOption('from', null, InputOption::VALUE_REQUIRED, 'Начало периода включительно, ISO 8601 со смещением.');
        $this->addOption('to', null, InputOption::VALUE_REQUIRED, 'Конец периода не включительно, ISO 8601 со смещением.');
        $this->addOption('timezone', null, InputOption::VALUE_REQUIRED, 'Часовой пояс IANA; обязателен для --period.');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Лимит вывода; при обрезке результат неполный (exit 3).');
        $this->addOption('max-messages', null, InputOption::VALUE_REQUIRED, 'Бюджет просмотра сообщений (по умолчанию 10000).');
        $this->addOption('no-call-details', null, InputOption::VALUE_NONE, 'Не запрашивать данные звонков и участников; сообщения о звонке остаются размеченными.');
        $this->setHelp(<<<'HELP'
            Читает сообщения выбранного чата за интервал [from, to). Адресат проверяется как в chats:resolve. Сообщения читаются через im.dialog.messages.get (REST 1.0, область доступа im) страницами по 50 от последнего сообщения к первому; strict-rest3 отклоняет команду до подключения. Чтение не меняет прочитанность.

            API не фильтрует по дате, а порядок дат по ID не доказан, поэтому CLI просматривает историю до её начала в пределах --max-messages. complete=true — история просмотрена полностью; иначе exit 3, а meta.reason объясняет причину (message-budget-exhausted, page-error, pagination-no-progress, conflicting-rows, output-limit). meta.periodStartPassed показывает, что просмотр уже ушёл раньше начала периода.

            JSON data: destination, period (kind, from, to, timezone, now), items (id, createdDate, authorId, text, chatId, kind, callRef) по времени и ID, authors (id, name из ответа API), calls. TSV при --plain: id, createdDate, authorId, text, kind, callId; сводка звонков — в stderr.

            Звонки: kind=call и callRef у сообщений о звонке (по компоненту CallMessage, текст не разбирается). Если в периоде есть такие сообщения, data.calls по умолчанию содержит по записи на звонок: время, длительность, инициатор, состояние записи, состав чата, фактические участники только из AI Follow-up (call.followup.get, REST 3.0, необязательная область доступа call) и оценку остальных по правилам v1 с доказательствами. Оценка не факт и не основание для дисциплинарных решений. Данные звонка берутся из im.call.get, которого нет в документации Битрикс24; его отказ не меняет полноту истории и код выхода. Токены подключения не выводятся. Начало звонка до периода берётся из данных звонка, сообщения вне периода не добавляются. --no-call-details отключает дополнительные запросы. Пример: b24cli chats:messages:list sg234 --period yesterday --timezone Europe/Moscow --json
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): ListChatMessagesRequest => $this->mapper->listMessages($input),
            fn (ListChatMessagesRequest $request): array => $this->service->routes($request),
            function (ListChatMessagesRequest $request): PreparedOperation {
                $this->mapper->assertPortal($request->target);
                return $this->service->prepare($request);
            },
        );
    }
}
