<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Chats;

use Bitrix24\CLI\Application\Chat\ChatFindService;
use Bitrix24\CLI\Application\Chat\Request\FindChatsRequest;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Console\ChatInputMapper;
use Bitrix24\CLI\Console\CommandRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'chats:find', description: 'Найти чат по названию: групповые чаты, проекты и задачи')]
final class FindChatCommand extends Command
{
    public function __construct(private readonly ChatFindService $service, private readonly ChatInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Название чата, проекта или заголовок задачи (не короче 2 символов).');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Лимит кандидатов в выводе (по умолчанию 50); точные совпадения идут первыми.');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Показать всех кандидатов без лимита вывода.');
        $this->addOption('max-scan', null, InputOption::VALUE_REQUIRED, 'Бюджет просмотра на каждый источник (по умолчанию 10000).');
        $this->setHelp(<<<'HELP'
            Ищет адресат по названию в трёх источниках: групповые чаты — im.search.chat.list (REST 1.0, область доступа im); общие чаты проектов — sonet_group.get (REST 1.0, sonet_group), кандидат sgN; чаты задач — заголовки задач через tasks.task.list (REST 3.0, task), кандидат task:N. Поиск чатов не возвращает чаты проектов и задач и находит чаты также по именам участников, поэтому названия сравниваются локально; совпадения только по участнику не выводятся (meta.sources.chat.participantOnly). strict-rest3 отклоняет команду до подключения. Чтение не меняет прочитанность.

            Точное совпадение — название после обрезки пробелов, с учётом регистра. Для точных совпадений проекта и задачи чат подтверждается как в chats:resolve; недоступный чат помечается reason и не выбирается. data.selection.status: unique — ровно одно точное совпадение с подтверждённым чатом при полном поиске (target — адресат); none — точных совпадений нет; ambiguous — их несколько, укажите TARGET явно; unavailable — единственный найденный чат недоступен; incomplete — хотя бы один источник просмотрен не полностью (exit 3), адресат не выбирается.

            JSON data: name, selection (status, target, exactMatches), items (source, target, name, exact, writable, chatId, reason, chatType|groupId|taskId); meta.sources: complete, reason, scanned, matched по каждому источнику. TSV при --plain: source, target, name, exact, writable, chatId, reason. Пример: b24cli chats:find --name 'Команда проекта' --json
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): FindChatsRequest => $this->mapper->find($input),
            fn (): array => $this->service->routes(),
            fn (FindChatsRequest $request): PreparedOperation => $this->service->prepare($request),
        );
    }
}
