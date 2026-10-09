<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\RuntimeState;
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
        parent::__construct('b24cli', '0.1.0');
        $this->setAutoExit(false);
        $this->setCatchExceptions(false);
        $this->setCatchErrors(false);
    }

    protected function getDefaultInputDefinition(): InputDefinition
    {
        $definition = parent::getDefaultInputDefinition();
        $definition->addOptions([
            new InputOption('json', null, InputOption::VALUE_NONE, 'One JSON envelope on stdout.'),
            new InputOption('plain', null, InputOption::VALUE_NONE, 'TSV without headers or decoration.'),
            new InputOption('profile', null, InputOption::VALUE_REQUIRED, 'Connection profile (default only).'),
            new InputOption('config', null, InputOption::VALUE_REQUIRED, 'Non-secret JSON runtime config.'),
            new InputOption('timeout', null, InputOption::VALUE_REQUIRED, 'HTTP timeout in seconds (default 30, maximum 300).'),
            new InputOption('api-policy', null, InputOption::VALUE_REQUIRED, 'task-v3 (default) or strict-rest3.'),
        ]);
        return $definition;
    }

    public function find(string $name): Command
    {
        try {
            return parent::find($name);
        } catch (CommandNotFoundException | NamespaceNotFoundException) {
            throw Failure::usage('Unknown or ambiguous command. Use b24cli list task to choose a full name.');
        }
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

                if (in_array($tokens[$i], ['--config', '--description-file', '--fields-file', '--text-file', '--where-file', '--params-file'], true) && ($tokens[$i + 1] ?? null) === '-') {
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
            $failure = $exception instanceof Failure ? $exception : ($exception instanceof \Symfony\Component\Console\Exception\ExceptionInterface
                ? Failure::usage('Invalid command arguments or options. Use the command --help for syntax.')
                : new Failure('configuration-error', 'The CLI could not complete this operation; check configuration.'));
            $this->presenter->failure($failure, $input->hasParameterOption('--json', true) ? 'json' : 'human', $output, $this->state->calls);
            return $failure->exitStatus;
        }
    }
}
