<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Config;

use Bitrix24\CLI\Console\ConfigCommandRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'config:unset', description: 'Удалить настройку и вернуть значение по умолчанию')]
final class UnsetCommand extends Command
{
    public function __construct(private readonly ConfigCommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('KEY', InputArgument::REQUIRED, 'Ключ настройки, например default.task.groupId.');
        $this->setHelp(<<<'HELP'
Локально удаляет ключ из config.json: дальше действует встроенное значение по умолчанию, опустевшие контейнеры удаляются. name удалить нельзя, default.users.observerId — только при autoAddObserver=false.

Профильные ключи удаляются из профиля --profile, иначе B24CLI_PROFILE, иначе defaultProfile. Ключи logging.* и telemetry.* не принимают --profile.
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run($this, $input, $output);
    }
}
