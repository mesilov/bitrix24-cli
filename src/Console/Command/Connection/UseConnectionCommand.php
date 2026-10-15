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

#[AsCommand(name: 'connection:use', description: 'Выбрать сохранённый профиль по умолчанию')]
final class UseConnectionCommand extends Command
{
    public function __construct(private readonly ConnectionCommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('NAME', InputArgument::REQUIRED, 'Имя профиля: work, pet, clientA, clientB.');
        $this->setHelp('Меняет только defaultProfile в config.json. Учётные данные и портал не изменяются.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run($this, $input, $output);
    }
}
