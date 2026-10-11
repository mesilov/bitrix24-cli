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

#[AsCommand(name: 'connection:remove', description: 'Удалить локальный профиль и его credentials')]
final class RemoveConnectionCommand extends Command
{
    public function __construct(private readonly ConnectionCommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('NAME', InputArgument::REQUIRED, 'Alias профиля: work, pet, clientA, clientB.');
        $this->setHelp('Удаляет metadata и credential выбранного профиля; defaultProfile становится null при удалении текущего default. Webhook на портале не отзывается.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run($this, $input, $output);
    }
}
