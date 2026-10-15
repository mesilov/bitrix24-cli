<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Users;

use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\User\Handler\ListColleaguesHandler;
use Bitrix24\CLI\Application\User\Request\ListColleaguesRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\UserInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'users:colleagues', description: 'Коллеги текущего отдела по оргструктуре')]
final class ListColleaguesCommand extends Command
{
    public function __construct(private readonly ListColleaguesHandler $handler, private readonly CommandRunner $runner, private readonly UserInputMapper $mapper)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('department-id', null, InputOption::VALUE_REQUIRED, 'Текущий отдел: обязателен при нескольких отделах.');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Лимит вывода (по умолчанию 50); не ограничивает обход.');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Снять только лимит вывода.');
        $this->addOption('max-scan', null, InputOption::VALUE_REQUIRED, 'Максимум просмотренных сотрудников (по умолчанию 1000).');
        $this->setHelp('REST1; права user/department выбранного подключения. JSON data/meta/error; TSV при --plain: ID, NAME, SECOND_NAME, LAST_NAME, EMAIL, UF_DEPARTMENT. Поиск возвращает кандидатов; полный пустой результат exit0, неполный exit3. Пример: b24cli users:colleagues --department-id 7 --json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): ListColleaguesRequest => $this->mapper->colleagues($input),
            fn (ListColleaguesRequest $request): array => $this->handler->routes(),
            fn (ListColleaguesRequest $request): PreparedOperation => $this->handler->prepare($request),
        );
    }
}
