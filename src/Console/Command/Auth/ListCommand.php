<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Auth;

use Bitrix24\CLI\Console\AuthCommandRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'auth:list', description: 'Показать сохранённые профили и фактическое подключение')]
final class ListCommand extends Command
{
    public function __construct(private readonly AuthCommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('NAME', InputArgument::OPTIONAL, 'Имя профиля; без него выводятся все сохранённые профили.');
        $this->setHelp(<<<'HELP'
Локальный список без обращения к порталу: profile, name, hostname, isDefault. Учётные данные сохранённых профилей не читаются.

Дополнительно показывает фактическое подключение текущего вызова: источник (profile, --env-file, B24CLI_ENV_FILE, BITRIX24_WEBHOOK), связанный профиль, путь или переменную и hostname без токена. В JSON это блок data.effective, в остальных режимах — строка в stderr.
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run($this, $input, $output);
    }
}
