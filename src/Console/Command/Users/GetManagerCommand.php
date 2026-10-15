<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Users;

use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\User\Handler\GetManagerHandler;
use Bitrix24\CLI\Application\User\Request\GetManagerRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\UserInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'users:manager', description: 'Непосредственный или настроенный руководитель')]
final class GetManagerCommand extends Command
{
    public function __construct(private readonly GetManagerHandler $handler, private readonly CommandRunner $runner, private readonly UserInputMapper $mapper)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('source', null, InputOption::VALUE_REQUIRED, 'org: оргструктура; config: рабочий руководитель профиля.', 'org');
        $this->addOption('department-id', null, InputOption::VALUE_REQUIRED, 'Текущий отдел для org, обязателен при нескольких отделах.');
        $this->setHelp('REST1; права user/department выбранного подключения. JSON data/meta/error; TSV при --plain: ID, NAME, SECOND_NAME, LAST_NAME, EMAIL, UF_DEPARTMENT. Поиск возвращает кандидатов; полный пустой результат exit0, неполный exit3. Пример: b24cli users:manager --source org --json; b24cli --profile work users:manager --source config');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): GetManagerRequest => $this->mapper->manager($input),
            fn (GetManagerRequest $request): array => $this->handler->routes($request),
            fn (GetManagerRequest $request): PreparedOperation => $this->handler->prepare($request),
        );
    }
}
