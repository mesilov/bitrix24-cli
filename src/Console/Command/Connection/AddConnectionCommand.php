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
        $this->setHelp('Скрытый ввод в TTY или --webhook-stdin; -n требует stdin flag. Не меняет defaultProfile. Webhook хранится отдельно в защищённом plaintext файле.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run($this, $input, $output);
    }
}
