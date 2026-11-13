<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Config;

use Bitrix24\CLI\Console\ConfigCommandRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'config:set', description: 'Изменить одну настройку без повторного ввода вебхука')]
final class SetCommand extends Command
{
    public function __construct(private readonly ConfigCommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('KEY', InputArgument::REQUIRED, 'Ключ настройки, например default.task.groupId.');
        $this->addArgument('VALUE', InputArgument::REQUIRED, 'Новое значение; тип определяется ключом.');
        $this->setHelp(<<<'HELP'
Локально меняет одно значение в config.json; остальные настройки, профили и учётные данные сохраняются. Тип значения задаётся ключом и не угадывается: ID и счётчики — целое от 1 цифрами, флаги — только true или false, logging.level — в нижнем регистре.

Ключи — точные пути config.json:
  name                                    описательный домен портала
  default.task.groupId|creatorId|responsibleId   ID по умолчанию для task:add
  default.task.allowsTimeTracking         true или false
  default.users.observerId|managerId      рабочие адресаты профиля
  default.users.autoAddObserver           true или false; true требует observerId
  logging.enabled|level|directory|fileMask|retentionDays
  telemetry.collectProblemTrajectories|maxProblemTrajectoryFiles

Профильные ключи меняются в профиле --profile, иначе B24CLI_PROFILE, иначе defaultProfile. Ключи logging.* и telemetry.* относятся ко всему каталогу конфигурации и не принимают --profile. defaultProfile меняет auth:switch, вебхук и connection.hostname — auth:login NAME --replace.
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run($this, $input, $output);
    }
}
