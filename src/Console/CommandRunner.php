<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\InvocationContext;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Bootstrap\CommandCatalog;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final readonly class CommandRunner
{
    public function __construct(private ApiPolicyGuard $guard, private ResultPresenter $presenter, private RuntimeState $state, private InputSourceReader $reader, private \Bitrix24\CLI\Infrastructure\Connection\ConnectionManager $connections, private \Bitrix24\CLI\Infrastructure\Bitrix24\ApiTransport $transport)
    {
    }

    public function run(Command $consoleCommand, InputInterface $input, OutputInterface $output, \Closure $requestFactory, \Closure $routes, \Closure $prepare): int
    {
        $command = $consoleCommand->getName();
        $definition = CommandCatalog::COMMANDS[$command] ?? null;
        if ($definition === null || $definition['class'] !== $consoleCommand::class) {
            throw new Failure('configuration-error', 'The command is absent from the explicit catalog.');
        }

        $effect = $definition['effect'];
        $this->reader->reset($input);
        $context = $this->context($input);
        $this->state->begin($context->timeout);
        $this->connections->selected($context->dryRun);
        $request = $requestFactory($context);
        $plan = $routes($request);
        $this->guard->validate($command, $plan, $context->policy);
        $tty = defined('STDIN') && stream_isatty(STDIN);
        if ($effect === 'delete' && !$context->dryRun && !$input->getOption('force') && (!$input->isInteractive() || !$tty)) {
            throw Failure::usage('Deletion needs --force in automation; use --dry-run to inspect the target first.');
        }

        $oldHandler = null;
        $oldAsync = null;
        if (function_exists('pcntl_signal')) {
            $oldHandler = pcntl_signal_get_handler(SIGINT);
            $oldAsync = pcntl_async_signals(true);
            pcntl_signal(SIGINT, function (): void {
                $this->state->cancelled = true;
            });
        }

        try {
            $scopeValidation = !$context->dryRun && $this->transport instanceof \Bitrix24\CLI\Infrastructure\Bitrix24\ScopeAwareTransport
                ? $this->transport->beforeCommand($plan, $context->policy !== 'strict-rest3') : [];
            $operation = $prepare($request, $context);
            if ($context->dryRun) {
                $result = new OperationResult('plan', ['plan' => ['command' => $command, 'target' => $operation->target, 'steps' => $operation->plan]], ['dryRun' => true, 'complete' => true]);
            } else {
                if ($effect === 'delete' && !$input->getOption('force')) {
                    $questionOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
                    $accepted = (new QuestionHelper())->ask($input, $questionOutput, new ConfirmationQuestion('Delete ' . $operation->target . '? [y/N] ', false));
                    if (!$accepted) {
                        throw new Failure('cancelled', 'Deletion cancelled.', 130);
                    }
                }

                $this->state->checkCancellation();
                $result = ($operation->execute)();
            }

            if ($this->state->cancelled) {
                $result = new OperationResult($result->profile, $result->data, [...$result->meta, 'reason' => 'Interrupted; confirmed steps are retained.'], 130);
            }

            $result = new OperationResult($result->profile, $result->data, [...$result->meta, ...($scopeValidation === [] ? [] : ['scopeValidation' => $scopeValidation]), 'connection' => $this->connections->metadata($context->dryRun), 'fieldSources' => $this->connections->fieldSources], $result->exitStatus);
            $this->presenter->success($result, $context->outputMode, $output, $command, $this->state->calls);
            return $result->exitStatus;
        } finally {
            if ($oldHandler !== null) {
                pcntl_signal(SIGINT, $oldHandler);
                pcntl_async_signals($oldAsync);
            }
        }
    }

    public function context(InputInterface $input): InvocationContext
    {
        if ($input->getOption('json') && $input->getOption('plain')) {
            throw Failure::usage('Choose --json or --plain.');
        }

        $config = [];
        if ($input->getOption('config') !== null) {
            $config = $this->reader->object($this->reader->read($input->getOption('config')));
            if (array_diff(array_keys($config), ['timeout', 'apiPolicy', 'profile', 'maxUploadBytes']) !== []) {
                throw Failure::usage('Config supports timeout, apiPolicy, profile and maxUploadBytes only; credentials belong in a credential file.');
            }
        }

        if (array_key_exists('profile', $config) && (!is_string($config['profile']) || $config['profile'] === '')) {
            throw Failure::usage('Runtime config profile must be a nonempty string.');
        }

        $maxUploadBytes = $config['maxUploadBytes'] ?? \Bitrix24\CLI\Application\Task\LocalFile::DEFAULT_MAX_BYTES;
        if (array_key_exists('maxUploadBytes', $config) && (!is_int($config['maxUploadBytes']) || $config['maxUploadBytes'] < 1)) {
            throw Failure::usage('Runtime config maxUploadBytes must be a positive JSON integer in bytes.');
        }

        $profile = $input->getOption('profile') ?? $config['profile'] ?? null;
        if ($profile !== null && !is_string($profile)) {
            throw Failure::usage('Runtime config profile must be a nonempty string.');
        }

        $this->connections->configure($profile, $input->getOption('env-file'), $input->getOption('config-dir'));

        $policy = $input->getOption('api-policy') ?? $config['apiPolicy'] ?? (getenv('B24CLI_API_POLICY') ?: 'task-v3');
        if (!in_array($policy, ['task-v3', 'strict-rest3'], true)) {
            throw Failure::usage('Use --api-policy task-v3 or strict-rest3.');
        }

        $timeout = $input->getOption('timeout') ?? $config['timeout'] ?? (getenv('B24CLI_TIMEOUT') ?: 30);
        if (!is_numeric($timeout) || !is_finite((float) $timeout) || (float) $timeout <= 0 || (float) $timeout > 300) {
            throw Failure::usage('Timeout must be a positive number up to 300 seconds (default 30).');
        }

        return new InvocationContext($input->getOption('json') ? 'json' : ($input->getOption('plain') ? 'plain' : 'human'), $policy, (float) $timeout, $input->hasOption('dry-run') && $input->getOption('dry-run'), $maxUploadBytes);
    }
}
