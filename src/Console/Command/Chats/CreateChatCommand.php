<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Chats;

use Bitrix24\CLI\Application\Chat\ChatCreateService;
use Bitrix24\CLI\Application\Chat\Request\CreateChatRequest;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Console\ChatInputMapper;
use Bitrix24\CLI\Console\CommandRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'chats:add', description: 'Создать групповой чат с участниками для рассылки')]
final class CreateChatCommand extends Command
{
    public function __construct(private readonly ChatCreateService $service, private readonly ChatInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('title', null, InputOption::VALUE_REQUIRED, 'Название чата (TITLE), от 2 до 255 символов.');
        $this->addOption('description', null, InputOption::VALUE_REQUIRED, 'Описание чата (DESCRIPTION).');
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'Тип чата (TYPE): CHAT — закрытый (по умолчанию), OPEN — открытый.', suggestedValues: ['CHAT', 'OPEN']);
        $this->addOption('user-id', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'ID участника (USERS); повторите опцию или перечислите через запятую (до 500).');
        $this->addOption('allow-duplicate-title', null, InputOption::VALUE_NONE, 'Создать чат, даже если доступный чат с таким названием уже есть; проверка дубля не выполняется.');
        $this->addOption('max-scan', null, InputOption::VALUE_REQUIRED, 'Бюджет поиска одноимённых чатов (по умолчанию 10000).');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Проверить дубль и показать план без создания.');
        $this->setHelp(<<<'HELP'
            Создаёт групповой чат через im.chat.add (REST 1.0, область доступа im) от имени пользователя подключения; он становится владельцем и участником автоматически. TYPE: CHAT — закрытый, OPEN — открытый. Каналы Битрикс24 REST не создаёт. Удалить чат через REST нельзя. strict-rest3 отклоняет команду до подключения.

            До записи ищет доступные групповые чаты с точно таким же названием (im.search.chat.list, как chats:find; сравнение после обрезки пробелов с учётом регистра). Совпадение — chat-exists (кандидаты в error.details.candidates), неполный поиск — search-incomplete; ничего не создаётся. --allow-duplicate-title пропускает проверку.

            --dry-run выполняет только проверку дубля и показывает план. Запись выполняется один раз; если ответ потерян, meta.outcomeUnknown = true и повтора нет: проверьте chats:find --name перед повтором. После создания CLI читает чат (im.dialog.get) и участников (im.dialog.users.list): data.operation.readback = verified; иначе exit 3 (title-mismatch, members-missing, failed), а chatId сохраняется. Писать в чат — chats:send chatN.

            JSON data: chat (target, chatId, title, type, description, userIds), operation (action, target, chatId, confirmed, readback), readback (status, dialog, members: requested, present, missing), duplicateCheck. TSV при --plain: target, chatId, title, type, confirmed, readback. Пример: b24cli chats:add --title 'ИИ-новости' --user-id 12,34 --json
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): CreateChatRequest => $this->mapper->create($input),
            fn (CreateChatRequest $request): array => $this->service->routes($request),
            fn (CreateChatRequest $request): PreparedOperation => $this->service->prepare($request),
        );
    }
}
