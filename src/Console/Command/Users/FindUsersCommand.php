<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Users;

use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\User\Handler\FindUsersHandler;
use Bitrix24\CLI\Application\User\Request\FindUsersRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\UserInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'users:find', description: 'Найти активных сотрудников по ФИО или email')]
final class FindUsersCommand extends Command
{
    public function __construct(private readonly FindUsersHandler $handler, private readonly CommandRunner $runner, private readonly UserInputMapper $mapper)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'ФИО: все токены без регистра Unicode.');
        $this->addOption('email', null, InputOption::VALUE_REQUIRED, 'Точный email; выбрать только один критерий.');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Лимит вывода (по умолчанию 50); не ограничивает обход.');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Снять только лимит вывода.');
        $this->addOption('max-scan', null, InputOption::VALUE_REQUIRED, 'Максимум просмотренных сотрудников (по умолчанию 1000).');
        $this->setHelp('REST1; права user/department выбранного подключения. JSON data/meta/error; TSV при --plain: ID, NAME, SECOND_NAME, LAST_NAME, EMAIL, UF_DEPARTMENT. Поиск возвращает кандидатов; полный пустой результат exit0, неполный exit3. Пример: b24cli users:find --name "Иванов Иван" --all --max-scan 5000 --json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): FindUsersRequest => $this->mapper->find($input),
            fn (FindUsersRequest $request): array => $this->handler->routes(),
            fn (FindUsersRequest $request): PreparedOperation => $this->handler->prepare($request),
        );
    }
}
