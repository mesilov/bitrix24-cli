<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\InvocationContext;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\RuntimeState;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final readonly class CommandRunner
{
    public function __construct(private ApiPolicyGuard $guard, private ResultPresenter $presenter, private RuntimeState $state, private InputSourceReader $reader)
    {
    }

    public function run(InputInterface $input, OutputInterface $output, string $command, string $effect, \Closure $requestFactory, \Closure $routes, \Closure $prepare): int
    {
        $this->reader->reset($input);
        $context = $this->context($input);
        $this->state->begin($context->timeout);
        $request = $requestFactory();
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

            $this->presenter->success($result, $context->outputMode, $output, $command, $this->state->calls);
            return $result->exitStatus;
        } finally {
            if ($oldHandler !== null) {
                pcntl_signal(SIGINT, $oldHandler);
                pcntl_async_signals($oldAsync);
            }
        }
    }

    private function context(InputInterface $input): InvocationContext
    {
        if ($input->getOption('json') && $input->getOption('plain')) {
            throw Failure::usage('Choose --json or --plain.');
        }

        $config = [];
        if ($input->getOption('config') !== null) {
            $config = $this->reader->object($this->reader->read($input->getOption('config')));
            if (array_diff(array_keys($config), ['timeout', 'apiPolicy', 'profile']) !== []) {
                throw Failure::usage('Config supports timeout, apiPolicy and profile only; credentials belong in .env.');
            }
        }

        $profile = $input->getOption('profile') ?? $config['profile'] ?? 'default';
        if ($profile !== 'default') {
            throw new Failure('configuration-error', 'Only the default webhook profile is configured.');
        }

        $policy = $input->getOption('api-policy') ?? $config['apiPolicy'] ?? (getenv('B24CLI_API_POLICY') ?: 'task-v3');
        if (!in_array($policy, ['task-v3', 'strict-rest3'], true)) {
            throw Failure::usage('Use --api-policy task-v3 or strict-rest3.');
        }

        $timeout = $input->getOption('timeout') ?? $config['timeout'] ?? (getenv('B24CLI_TIMEOUT') ?: 30);
        if (!is_numeric($timeout) || !is_finite((float) $timeout) || (float) $timeout <= 0 || (float) $timeout > 300) {
            throw Failure::usage('Timeout must be a positive number up to 300 seconds (default 30).');
        }

        return new InvocationContext($input->getOption('json') ? 'json' : ($input->getOption('plain') ? 'plain' : 'human'), $policy, (float) $timeout, $input->hasOption('dry-run') && $input->getOption('dry-run'));
    }
}
