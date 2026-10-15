<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Connection;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiTransport;
use Bitrix24\CLI\Infrastructure\Bitrix24\ScopeAwareTransport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'connection:check', description: 'Проверить разрешения выбранного вебхука и требования CLI')]
final class CheckConnectionCommand extends Command
{
    public function __construct(private readonly CommandRunner $runner, private readonly ApiTransport $transport)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('NAME', InputArgument::OPTIONAL, 'Имя сохранённого профиля; иначе общие параметры выбора подключения.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без сети и записи state.json.');
        $this->setHelp('Принудительно читает разрешения вебхука и обновляет state.json, даже если суточный кеш ещё действителен. Показывает права, дату проверки и потенциально затронутые команды; зависимости могут зависеть от опций. При нехватке разрешений: код 4; при неудаче проверки: код 1. Добавьте разрешения в настройках входящего вебхука и повторите connection:check с теми же --profile/--env-file/--config-dir. strict-rest3 запрещает эту REST1 диагностику. Примеры: b24cli connection:check work --json; b24cli connection:check --env-file /absolute/path/portal.env');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getArgument('NAME') !== null) {
            if ($input->getOption('profile') !== null || $input->getOption('env-file') !== null) {
                throw Failure::usage('Choose NAME or a global connection selector.');
            }

            $input->setOption('profile', $input->getArgument('NAME'));
        }

        return $this->runner->run($this, $input, $output, static fn (): array => [], static fn (): array => [new Route('scope', 1)], fn (): PreparedOperation => new PreparedOperation(
            [['step' => 1, 'method' => 'scope', 'apiVersion' => '1.0', 'effect' => 'read']],
            function (): OperationResult {
                if (!$this->transport instanceof ScopeAwareTransport) {
                    throw new Failure('scope-check-unavailable', 'The selected API transport does not support connection scope diagnostics.');
                }

                return new OperationResult('scope-check', $this->transport->checkConnection());
            },
            'selected incoming webhook',
        ));
    }
}
