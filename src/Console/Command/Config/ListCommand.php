<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Config;

use Bitrix24\CLI\Console\ConfigCommandRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'config:list', description: 'Показать действующие настройки профиля и каталога конфигурации')]
final class ListCommand extends Command
{
    public function __construct(private readonly ConfigCommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(<<<'HELP'
Локально показывает действующее значение каждого ключа и его источник: profile, root или default. Профильные ключи берутся из профиля --profile, иначе B24CLI_PROFILE, иначе defaultProfile; без выбранного профиля выводятся только общие ключи logging.* и telemetry.*.

Блок logging (JSON: data.logging, иначе строка в stderr) содержит статус ok, disabled или invalid с причиной, абсолютный каталог логов и файл текущих суток. Каталог логов не создаётся, значения по умолчанию в файл не записываются.
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run($this, $input, $output);
    }
}
