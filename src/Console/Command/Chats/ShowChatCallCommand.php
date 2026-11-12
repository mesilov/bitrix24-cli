<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Chats;

use Bitrix24\CLI\Application\Chat\ChatCallsService;
use Bitrix24\CLI\Application\Chat\Request\ShowChatCallRequest;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Console\ChatInputMapper;
use Bitrix24\CLI\Console\CommandRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'chats:calls:show', description: 'Сведения об одном звонке чата')]
final class ShowChatCallCommand extends Command
{
    public function __construct(private readonly ChatCallsService $service, private readonly ChatInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TARGET', InputArgument::REQUIRED, 'Ссылка портала или ID: chatN, sgN, group:N, task:N.');
        $this->addArgument('CALL_ID', InputArgument::REQUIRED, 'ID звонка из callRef сообщения о звонке.');
        $this->addOption('max-messages', null, InputOption::VALUE_REQUIRED, 'Бюджет просмотра сообщений (по умолчанию 10000).');
        $this->setHelp(<<<'HELP'
            Возвращает запись одного звонка выбранного чата: UUID, время, длительность, инициатор, завершивший, сообщения во время звонка, состояние записи с причинами её отсутствия, состав чата, подтверждённые участники из AI Follow-up и оценку остальных по правилам v1 с доказательствами.

            Звонок другого чата отклоняется ошибкой привязки (exit 4) без вывода его данных. Если данные звонка недоступны (im.call.get не описан в документации Битрикс24), команда завершается ошибкой call-unavailable; звонок можно найти в chats:messages:list. История читается в окне от начала звонка до пяти минут после конца; при неполном обходе exit 3. Оценка присутствия — не факт и не основание для дисциплинарных решений. Токены подключения не выводятся. REST 1.0, область доступа im, необязательная call для call.followup.get; strict-rest3 отклоняет команду до подключения.

            JSON data: destination, period, call. TSV при --plain: callId, startedAt, durationSeconds, initiatorId, present, absent, leftEarly, unknown. Пример: b24cli chats:calls:show chat37 1001 --json
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): ShowChatCallRequest => $this->mapper->showCall($input),
            fn (ShowChatCallRequest $request): array => $this->service->routes($request->target),
            function (ShowChatCallRequest $request): PreparedOperation {
                $this->mapper->assertPortal($request->target);
                return $this->service->show($request);
            },
        );
    }
}
