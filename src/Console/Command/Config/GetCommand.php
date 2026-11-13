<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Config;

use Bitrix24\CLI\Console\ConfigCommandRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'config:get', description: 'Показать действующее значение одной настройки')]
final class GetCommand extends Command
{
    public function __construct(private readonly ConfigCommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('KEY', InputArgument::REQUIRED, 'Ключ настройки, например default.task.groupId.');
        $this->setHelp(<<<'HELP'
Локально показывает значение ключа и источник: profile, root или default. --plain печатает только значение; JSON содержит key, value, source и profile.

Ключи — точные пути config.json:
  name                                    описательный домен портала
  default.task.groupId|creatorId|responsibleId   ID по умолчанию для task:add
  default.task.allowsTimeTracking         true или false
  default.users.observerId|managerId      рабочие адресаты профиля
  default.users.autoAddObserver           true или false; true требует observerId
  logging.enabled|level|directory|fileMask|retentionDays
  telemetry.collectProblemTrajectories|maxProblemTrajectoryFiles
  telemetry.lastProblemTrajectoryProcessingAt|lastProblemTrajectoryAnalysisOfferedAt   только чтение

Профильные ключи читаются из профиля --profile, иначе B24CLI_PROFILE, иначе defaultProfile. Ключи logging.* и telemetry.* относятся ко всему каталогу конфигурации и не принимают --profile.
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run($this, $input, $output);
    }
}
