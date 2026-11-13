<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Auth;

use Bitrix24\CLI\Console\AuthCommandRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'auth:login', description: 'Сохранить вебхук портала как профиль и проверить его')]
final class LoginCommand extends Command
{
    public function __construct(private readonly AuthCommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('NAME', InputArgument::REQUIRED, 'Имя профиля: work, pet, clientA, clientB.');
        $this->addOption('webhook-stdin', null, InputOption::VALUE_NONE, 'Прочитать вебхук из перенаправленного stdin; секрет не передаётся в argv.');
        $this->addOption('replace', null, InputOption::VALUE_NONE, 'Заменить вебхук существующего профиля, сохранив его настройки.');
        $this->addOption('skip-check', null, InputOption::VALUE_NONE, 'Сохранить профиль без запросов к порталу: офлайн, CI и strict-rest3.');
        $this->setHelp(<<<'HELP'
Сохраняет входящий вебхук в защищённом файле открытого текста и создаёт профиль NAME. Вебхук вводится скрыто в терминале либо читается из stdin с --webhook-stdin; -n требует --webhook-stdin. В аргументах команды секрет не передаётся.

До сохранения вебхук проверяется на портале двумя запросами REST 1.0: владелец вебхука (profile) и выданные ему разрешения. Неверный вебхук, ошибка сети или неактивный пользователь: профиль не сохраняется, код 1. Нехватка прав вход не отменяет: профиль сохраняется, а отчёт о недостающих правах выводится в stderr; точную проверку с кодом 4 даёт auth:status. Если права прочитать не удалось, профиль сохраняется с предупреждением. --skip-check сохраняет профиль без запросов к порталу; strict-rest3 разрешает вход только с --skip-check.

Первый профиль становится профилем по умолчанию. Если профиль по умолчанию уже выбран, он не меняется: переключение выполняет auth:switch NAME.

Существующий NAME без --replace возвращает ошибку до ввода вебхука. --replace заменяет только вебхук: name, default.* и профиль по умолчанию сохраняются, connection.hostname обновляется по новому вебхуку. Значения по умолчанию задаются отдельно, например: b24cli config:set default.task.groupId 187 --profile work.

В CI и скриптах: b24cli auth:login ci --webhook-stdin --skip-check -n < /secure/webhook.txt
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run($this, $input, $output);
    }
}
