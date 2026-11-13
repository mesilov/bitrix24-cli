<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Auth;

use Bitrix24\CLI\Console\AuthCommandRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'auth:logout', description: 'Удалить локальный профиль и его учётные данные')]
final class LogoutCommand extends Command
{
    public function __construct(private readonly AuthCommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('NAME', InputArgument::REQUIRED, 'Имя профиля: work, pet, clientA, clientB.');
        $this->setHelp(<<<'HELP'
Удаляет метаданные профиля вместе с его настройками и файл учётных данных. Если профиль был выбран по умолчанию, defaultProfile становится null; другой профиль не выбирается автоматически. Вебхук на портале не отзывается: удалите его в настройках портала отдельно.
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run($this, $input, $output);
    }
}
