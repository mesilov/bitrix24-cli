<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Chats;

use Bitrix24\CLI\Application\Chat\ChatCallsService;
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

#[AsCommand(name: 'chats:calls:list', description: 'Звонки чата за период')]
final class ListChatCallsCommand extends Command
{
    public function __construct(private readonly ChatCallsService $service, private readonly ChatInputMapper $mapper, private readonly CommandRunner $runner)
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
        $this->addOption('max-messages', null, InputOption::VALUE_REQUIRED, 'Бюджет просмотра сообщений (по умолчанию 10000).');
        $this->setHelp(<<<'HELP'
            Собирает звонки выбранного чата за интервал [from, to) по сообщениям о звонке в истории (как chats:messages:list) и возвращает по записи на звонок в хронологическом порядке: время, длительность, инициатор, завершивший, состояние записи, состав чата, подтверждённые участники из AI Follow-up и оценку остальных по правилам v1.

            Полнота определяется обходом истории: при неполном обходе exit 3 и meta.reason. Оценка присутствия — не факт и не основание для дисциплинарных решений. Данные звонка из im.call.get (не описан в документации Битрикс24); call.followup.get (REST 3.0) необязателен и требует области доступа call. REST 1.0, область доступа im; strict-rest3 отклоняет команду до подключения. Токены подключения не выводятся.

            JSON data: destination, period, items (записи звонков). TSV при --plain: callId, startedAt, durationSeconds, initiatorId, present, absent, leftEarly, unknown. Пример: b24cli chats:calls:list sg234 --from 2026-11-09T00:00:00+03:00 --to 2026-11-16T00:00:00+03:00 --json
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): ListChatMessagesRequest => $this->mapper->listCalls($input),
            fn (ListChatMessagesRequest $request): array => $this->service->routes($request->target),
            function (ListChatMessagesRequest $request): PreparedOperation {
                $this->mapper->assertPortal($request->target);
                return $this->service->list($request);
            },
        );
    }
}
