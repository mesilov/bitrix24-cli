<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Connection;

use Bitrix24\CLI\Console\ConnectionCommandRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'connection:add', description: 'Сохранить локальное подключение')]
final class AddConnectionCommand extends Command
{
    public function __construct(private readonly ConnectionCommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('NAME', InputArgument::REQUIRED, 'Alias профиля: work, pet, clientA, clientB.');
        $this->addOption('webhook-stdin', null, InputOption::VALUE_NONE, 'Прочитать webhook из redirected stdin; секрет не передаётся в argv.');
        $this->addOption('portal-domain', null, InputOption::VALUE_REQUIRED, 'Параметр профиля portal-domain; не требует API.');
        $this->addOption('group-id', null, InputOption::VALUE_REQUIRED, 'Параметр профиля group-id; не требует API.');
        $this->addOption('creator-id', null, InputOption::VALUE_REQUIRED, 'Параметр профиля creator-id; не требует API.');
        $this->addOption('responsible-id', null, InputOption::VALUE_REQUIRED, 'Параметр профиля responsible-id; не требует API.');
        $this->addOption('allows-time-tracking', null, InputOption::VALUE_REQUIRED, 'default.task.allowsTimeTracking: true или false.', 'false');
        $this->addOption('observer-id', null, InputOption::VALUE_REQUIRED, 'default.users.observerId: ID наблюдателя.');
        $this->addOption('manager-id', null, InputOption::VALUE_REQUIRED, 'default.users.managerId: рабочий руководитель, независимо от оргструктуры.');
        $this->addOption('auto-add-observer', null, InputOption::VALUE_REQUIRED, 'Добавлять наблюдателя при task:add: true или false; true требует --observer-id.', 'false');
        $this->setHelp(<<<'HELP'
Создаёт только новый профиль. Если NAME уже существует, возвращает ошибку до ввода webhook; переданные параметры не применяются.
Скрытый ввод в TTY или --webhook-stdin; -n требует stdin flag. Не меняет defaultProfile. Webhook хранится отдельно в защищённом plaintext файле.

Для изменения профиля сначала проверьте defaults через connection:show NAME и обеспечьте доступ к webhook для повторного ввода.
Затем выполните connection:remove NAME и connection:add NAME со всеми нужными defaults: удалённые значения не переносятся, omitted ID defaults отсутствуют, --allows-time-tracking по умолчанию false.
connection:remove удаляет локальный credential file и очищает defaultProfile при совпадении; webhook на портале не отзывается.
Если профиль был выбран по умолчанию, восстановите выбор через connection:use NAME.

Пример в интерактивном терминале (webhook вводится повторно скрыто):
  b24cli connection:show work
  b24cli connection:remove work
  b24cli connection:add work --group-id 187 --creator-id 3 --responsible-id 2 --allows-time-tracking true
  b24cli connection:use work
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run($this, $input, $output);
    }
}
