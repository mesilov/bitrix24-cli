<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Users;

use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\User\Handler\CurrentUserHandler;
use Bitrix24\CLI\Application\User\Request\CurrentUserRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'users:current', description: 'Пользователь текущего подключения')]
final class CurrentUserCommand extends Command
{
    public function __construct(private readonly CurrentUserHandler $handler, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {

        $this->setHelp('REST1; права user/department выбранного подключения. JSON data/meta/error; TSV при --plain: ID, NAME, SECOND_NAME, LAST_NAME, EMAIL, UF_DEPARTMENT. Поиск возвращает кандидатов; полный пустой результат exit0, неполный exit3. Пример: b24cli users:current --json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): CurrentUserRequest => new CurrentUserRequest(),
            fn (CurrentUserRequest $request): array => $this->handler->routes(),
            fn (CurrentUserRequest $request): PreparedOperation => $this->handler->prepare(),
        );
    }
}
