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

#[AsCommand(name: 'connection:remove', description: 'Удалить локальный профиль и его учётные данные')]
final class RemoveConnectionCommand extends Command
{
    public function __construct(private readonly ConnectionCommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('NAME', InputArgument::REQUIRED, 'Имя профиля: work, pet, clientA, clientB.');
        $this->setHelp('Удаляет метаданные и учётные данные выбранного профиля; defaultProfile становится null при удалении текущего профиля по умолчанию. Вебхук на портале не отзывается.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run($this, $input, $output);
    }
}
