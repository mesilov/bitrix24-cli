<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Auth;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiTransport;
use Bitrix24\CLI\Infrastructure\Bitrix24\ScopeAwareTransport;
use Bitrix24\CLI\Infrastructure\Connection\WebhookOwnerReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'auth:status', description: 'Проверить вебхук: владелец, разрешения и требования CLI')]
final class StatusCommand extends Command
{
    public function __construct(private readonly CommandRunner $runner, private readonly ApiTransport $transport, private readonly WebhookOwnerReader $owners)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('NAME', InputArgument::OPTIONAL, 'Имя сохранённого профиля; иначе общие параметры выбора подключения.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без сети и записи state.json.');
        $this->setHelp('Определяет владельца вебхука методом REST 1.0 profile (отдельное разрешение не требуется), затем принудительно читает разрешения вебхука и обновляет state.json, даже если суточный кеш ещё действителен. Показывает владельца (owner: ID, NAME, LAST_NAME, ADMIN), права, дату проверки и потенциально затронутые команды; зависимости могут зависеть от опций. При нехватке разрешений: код 4; при неудаче проверки или неактивном пользователе вебхука: код 1. Добавьте разрешения в настройках входящего вебхука и повторите auth:status с теми же --profile/--env-file/--config-dir. strict-rest3 запрещает эту REST1 диагностику.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getArgument('NAME') !== null) {
            if ($input->getOption('profile') !== null || $input->getOption('env-file') !== null) {
                throw Failure::usage('Choose NAME or a global connection selector.');
            }

            $input->setOption('profile', $input->getArgument('NAME'));
        }

        return $this->runner->run($this, $input, $output, static fn (): array => [], static fn (): array => [new Route('profile', 1), new Route('scope', 1)], fn (): PreparedOperation => new PreparedOperation(
            [['step' => 1, 'method' => 'profile', 'apiVersion' => '1.0', 'effect' => 'read'], ['step' => 2, 'method' => 'scope', 'apiVersion' => '1.0', 'effect' => 'read']],
            function (): OperationResult {
                if (!$this->transport instanceof ScopeAwareTransport) {
                    throw new Failure('scope-check-unavailable', 'The selected API transport does not support connection scope diagnostics.');
                }

                // The owner is read first: an invalid webhook or inactive user fails before the snapshot is refreshed.
                return new OperationResult('scope-check', $this->transport->checkConnection($this->owners->read($this->transport)));
            },
            'selected incoming webhook',
        ));
    }
}
