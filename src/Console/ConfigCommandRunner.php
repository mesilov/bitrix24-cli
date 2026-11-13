<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Bootstrap\CommandCatalog;
use Bitrix24\CLI\Infrastructure\Connection\ConfigKeys;
use Bitrix24\CLI\Infrastructure\Connection\ConnectionManager;
use Bitrix24\CLI\Infrastructure\Connection\ProfileRepository;
use Bitrix24\CLI\Infrastructure\Logging\LoggingSettingsReader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Non-secret settings: config:list, config:get, config:set and config:unset.
 * The commands never read credentials, call the portal or open state.json.
 */
final readonly class ConfigCommandRunner
{
    public function __construct(private ConnectionManager $connections, private ProfileRepository $profiles, private CommandRunner $runner, private InputSourceReader $reader, private ResultPresenter $presenter, private LoggingSettingsReader $logging)
    {
    }

    public function run(Command $command, InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $command->getName();
        if ((CommandCatalog::LOCAL_COMMANDS[$name]['class'] ?? null) !== $command::class) {
            throw new Failure('configuration-error', 'Local command is absent from the catalog.');
        }

        if ($input->getOption('env-file') !== null) {
            throw Failure::usage('config:* commands address a saved profile; --env-file does not apply. Use --profile NAME.');
        }

        $this->reader->reset($input);
        $context = $this->runner->context($input);
        $key = $input->hasArgument('KEY') ? (string) $input->getArgument('KEY') : null;
        $write = in_array($name, ['config:set', 'config:unset'], true);
        if ($key !== null) {
            ConfigKeys::assertKnown($key, $write);
            if (ConfigKeys::isRoot($key) && $input->getOption('profile') !== null) {
                throw Failure::usage($key . ' applies to the whole configuration directory; repeat the command without --profile.');
            }
        }

        $directory = $this->connections->directory();
        $alias = $key !== null && ConfigKeys::isRoot($key) ? null : $this->connections->profileAlias();
        if ($key !== null && !ConfigKeys::isRoot($key) && $alias === null) {
            throw Failure::usage('No profile is selected for the profile setting ' . $key . '. Pass --profile NAME, choose a default with auth:switch NAME, or create the first profile with auth:login NAME.');
        }

        $result = match ($name) {
            'config:list' => $this->listing($directory, $alias, $input->getOption('config-dir')),
            'config:get' => new OperationResult('config-value', $this->value($directory, $alias, (string) $key), ['local' => true]),
            'config:set' => new OperationResult('config-value', $this->profiles->set($directory, $alias, (string) $key, (string) $input->getArgument('VALUE')), ['local' => true]),
            'config:unset' => new OperationResult('config-value', $this->profiles->unset($directory, $alias, (string) $key), ['local' => true]),
            default => throw new Failure('configuration-error', 'Unknown local config command.'),
        };
        $this->presenter->success($result, $context->outputMode, $output, $name, []);
        return 0;
    }

    private function value(string $directory, ?string $alias, string $key): array
    {
        if ($alias !== null) {
            $this->profiles->profile($directory, $alias);
        }

        return [...ConfigKeys::read($this->profiles->load($directory), $alias, $key), 'profile' => $alias];
    }

    private function listing(string $directory, ?string $alias, ?string $configDirectory): OperationResult
    {
        if ($alias !== null) {
            $this->profiles->profile($directory, $alias);
        }

        $logging = $this->logging($configDirectory);
        $notices = [];
        if ($alias === null) {
            $notices[] = 'Профиль не выбран: показаны только общие настройки каталога конфигурации. Укажите --profile NAME или выберите профиль через auth:switch NAME.';
        }

        $notices[] = 'logging: статус ' . $logging['status'] . ($logging['reason'] === null ? '' : ' (' . $logging['reason'] . ')') . '; каталог ' . $logging['directory'] . '; файл текущих суток ' . $logging['currentFile'] . '.';
        return new OperationResult('config-settings', ['profile' => $alias, 'settings' => ConfigKeys::listing($this->profiles->load($directory), $alias), 'logging' => $logging], ['local' => true, 'notices' => $notices]);
    }

    /**
     * Effective file logging of the configuration directory; computed without creating the log directory.
     */
    private function logging(?string $configDirectory): array
    {
        $state = $this->logging->read($configDirectory);
        $settings = $state['settings'];
        return [
            'status' => $state['status'],
            'reason' => $state['reason'],
            'enabled' => $state['status'] === 'ok',
            'level' => $settings['level'],
            'directory' => $state['directory'],
            'fileMask' => $settings['fileMask'],
            'retentionDays' => $settings['retentionDays'],
            'currentFile' => $state['currentFile'],
        ];
    }
}
