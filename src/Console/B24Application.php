<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\BuildVersion;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\NamespaceNotFoundException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class B24Application extends Application
{
    public function __construct(private readonly ResultPresenter $presenter, private readonly RuntimeState $state)
    {
        parent::__construct('b24cli', BuildVersion::version());
        $this->setAutoExit(false);
        $this->setCatchExceptions(false);
        $this->setCatchErrors(false);
    }

    protected function getDefaultCommands(): array
    {
        return array_map(static fn (Command $command): Command => BuiltinHelpMetadata::localize(match (true) {
            $command instanceof \Symfony\Component\Console\Command\HelpCommand => new TaskHelpCommand(),
            $command instanceof \Symfony\Component\Console\Command\ListCommand => new RussianListCommand(),
            default => $command,
        }), parent::getDefaultCommands());
    }

    protected function getDefaultInputDefinition(): InputDefinition
    {
        $definition = parent::getDefaultInputDefinition();
        $options = $definition->getOptions();
        $descriptions = [
            'help' => 'Справка команды; без имени команды — обзор b24cli.',
            'silent' => 'Не выводить сообщения.',
            'quiet' => 'Выводить только ошибки.',
            'verbose' => 'Подробность сообщений: 1 — обычная, 2 — подробная, 3 — отладка.',
            'version' => 'Показать версию приложения.',
            'ansi' => 'Включить ANSI-оформление (или отключить через --no-ansi).',
            'no-interaction' => 'Не задавать интерактивных вопросов.',
        ];
        foreach ($options as $name => $option) {
            $options[$name] = BuiltinHelpMetadata::option($option, $descriptions[$name]);
        }

        $definition->setOptions($options);
        $definition->addOptions([
            new InputOption('about', null, InputOption::VALUE_NONE, 'Сведения о приложении: назначение, версия, автор, лицензия и ссылки.'),
            new InputOption('json', null, InputOption::VALUE_NONE, 'Единая структура JSON в stdout.'),
            new InputOption('plain', null, InputOption::VALUE_NONE, 'TSV без заголовков и оформления.'),
            new InputOption('profile', null, InputOption::VALUE_REQUIRED, 'Сохранённый профиль подключения; имеет приоритет над учётными данными окружения.'),
            new InputOption('env-file', null, InputOption::VALUE_REQUIRED, 'Явный dotenv-файл учётных данных; несовместим с --profile.'),
            new InputOption('config-dir', null, InputOption::VALUE_REQUIRED, 'Каталог пользовательской конфигурации.'),
            new InputOption('config', null, InputOption::VALUE_REQUIRED, 'JSON-конфигурация выполнения без секретов.'),
            new InputOption('timeout', null, InputOption::VALUE_REQUIRED, 'Таймаут HTTP в секундах (по умолчанию 30, максимум 300).'),
            new InputOption('api-policy', null, InputOption::VALUE_REQUIRED, 'Политика API: task-v3 (по умолчанию) или strict-rest3.'),
        ]);
        return $definition;
    }

    public function find(string $name): Command
    {
        try {
            return parent::find($name);
        } catch (CommandNotFoundException | NamespaceNotFoundException) {
            $suggestion = UsageDiagnostics::closest($name, array_keys($this->all()));
            throw Failure::usage('Неизвестная или неоднозначная команда. ' . ($suggestion === null ? 'Используйте b24cli list для выбора полного имени.' : 'Возможно, нужна ' . $suggestion . '. ' . TaskUsage::advice($this->get($suggestion))));
        }
    }

    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        if ($input->hasParameterOption(['--version', '-V'], true)) {
            return parent::doRun($input, $output);
        }

        try {
            $input->bind($this->getDefinition());
        } catch (\Symfony\Component\Console\Exception\ExceptionInterface) {
            if ($input->hasParameterOption('--about', true)) {
                throw Failure::usage('Используйте b24cli --about без имени команды.');
            }

            return parent::doRun($input, $output);
        }

        $name = $this->getCommandName($input);
        $about = $input->hasParameterOption('--about', true);
        if ($about && $name !== null) {
            throw Failure::usage('Используйте b24cli --about без имени команды.');
        }

        $help = $input->hasParameterOption(['--help', '-h'], true);
        if ($name === null && ($help || $about)) {
            $input->bind(new InputDefinition($this->getDefinition()->getOptions()));
            $input->validate();
            $applicationInformation = new ApplicationInformation();
            if ($help) {
                $applicationInformation->help($this, $output);
            } else {
                $applicationInformation->about($this, $output);
            }

            return 0;
        }

        return parent::doRun($input, $output);
    }

    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        $input ??= new ArgvInput();
        if ($input instanceof ArgvInput) {
            // Symfony treats a separated lone '-' as an option, not a file value.
            $tokens = $input->getRawTokens();
            $normalized = [];
            $counter = count($tokens);
            for ($i = 0; $i < $counter; $i++) {
                if ($tokens[$i] === '--') {
                    array_push($normalized, ...array_slice($tokens, $i));
                    break;
                }

                if (in_array($tokens[$i], ['--config', '--description-file', '--fields-file', '--user-fields-file', '--text-file', '--where-file', '--params-file', '--report-file', '--settings-file', '--batch-file'], true) && ($tokens[$i + 1] ?? null) === '-') {
                    $normalized[] = $tokens[$i] . '=-';
                    $i++;
                } else {
                    $normalized[] = $tokens[$i];
                }
            }

            if ($normalized !== $tokens) {
                $argvInput = new ArgvInput(['b24cli', ...$normalized]);
                $argvInput->setInteractive($input->isInteractive());
                $argvInput->setStream($input->getStream());
                $input = $argvInput;
            }
        }

        $output ??= new ConsoleOutput();
        $this->state->calls = [];
        if ($input->hasParameterOption(['--json', '--plain', '--no-ansi'], true) || (getenv('NO_COLOR') ?: '') !== '' || getenv('TERM') === 'dumb') {
            $output->setDecorated(false);
            if ($output instanceof ConsoleOutputInterface) {
                $output->getErrorOutput()->setDecorated(false);
            }
        }

        try {
            return parent::run($input, $output);
        } catch (\Throwable $exception) {
            $command = null;
            try {
                $name = $input->getFirstArgument();
                $command = $name === null ? null : parent::find($name);
            } catch (\Throwable) {
                // Arbitrary input is never echoed while recovering command metadata.
            }

            $failure = $exception instanceof Failure ? $exception : ($exception instanceof \Symfony\Component\Console\Exception\ExceptionInterface
                ? UsageDiagnostics::parser($exception, $command)
                : new Failure('configuration-error', 'The CLI could not complete this operation; check configuration.'));
            if ($exception instanceof Failure && $exception->errorCode === 'usage-error' && $command instanceof \Symfony\Component\Console\Command\Command) {
                $failure = new Failure($exception->errorCode, $exception->getMessage() . ' ' . TaskUsage::advice($command), $exception->exitStatus, $exception->details, $exception->outcomeUnknown);
            }

            $mode = $input->hasParameterOption('--json', true) ? 'json' : ($input->hasParameterOption('--plain', true) ? 'plain' : 'human');
            $this->presenter->failure($failure, $mode, $output, $this->state->calls);
            return $failure->exitStatus;
        }
    }
}
