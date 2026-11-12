<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Users;

use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\User\Handler\UserPresenceHandler;
use Bitrix24\CLI\Application\User\Request\UserPresenceRequest;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\UserInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'users:presence', description: 'Последняя активность и отсутствие сотрудников')]
final class UserPresenceCommand extends Command
{
    public function __construct(private readonly UserPresenceHandler $handler, private readonly CommandRunner $runner, private readonly UserInputMapper $mapper)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('user-id', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'ID сотрудника; повторите опцию или перечислите через запятую (до 500).');
        $this->setHelp('REST 1.0 im.user.list.get, область доступа im; strict-rest3 отклоняет вызов до подключения. Для каждого ID: id, name, lastActivityDate, absent, found, observedAt; неизвестный ID остаётся в выводе с found=false и перечислен в meta.notFound. Статус мессенджера и признак онлайна не выводятся: на портале они остаются online у давно неактивных пользователей, поэтому присутствие оценивается только по последней активности. JSON data/meta/error; TSV при --plain: id, name, lastActivityDate, absent. Пример: b24cli users:presence --user-id 11 --user-id 22 --json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): UserPresenceRequest => $this->mapper->presence($input),
            fn (UserPresenceRequest $request): array => $this->handler->routes(),
            fn (UserPresenceRequest $request): PreparedOperation => $this->handler->prepare($request),
        );
    }
}
