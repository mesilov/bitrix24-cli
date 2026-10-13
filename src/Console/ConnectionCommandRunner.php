<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Bootstrap\CommandCatalog;
use Bitrix24\CLI\Infrastructure\Connection\ConnectionManager;
use Bitrix24\CLI\Infrastructure\Connection\ProfileRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

final readonly class ConnectionCommandRunner
{
    public function __construct(private ConnectionManager $connections, private ProfileRepository $profiles, private CommandRunner $runner, private InputSourceReader $reader, private TaskInputMapper $mapper, private ResultPresenter $presenter)
    {
    }

    public function run(Command $command, InputInterface $input, OutputInterface $output): int
    {
        $name = $command->getName();
        if ((CommandCatalog::LOCAL_COMMANDS[$name]['class'] ?? null) !== $command::class) {
            throw new Failure('configuration-error', 'Local command is absent from the catalog.');
        }

        if ($name === 'connection:add' && $input->getOption('webhook-stdin') && $input->getOption('config') === '-') {
            throw Failure::usage('Webhook input and runtime config cannot both consume stdin.');
        }

        $this->reader->reset($input);
        $context = $this->runner->context($input);
        $alias = $input->hasArgument('NAME') ? $input->getArgument('NAME') : null;
        if ($alias !== null) {
            ProfileRepository::alias($alias);
        }

        if ($name === 'connection:show') {
            if ($alias !== null) {
                if ($input->getOption('profile') !== null || $input->getOption('env-file') !== null) {
                    throw Failure::usage('Choose NAME or a global connection selector.');
                }

                $this->connections->configure($alias, null, $input->getOption('config-dir'));
            }

            $items = [$this->row($this->connections->inspect())];
        } else {
            $directory = $this->connections->directory();
            $items = match ($name) {
                'connection:list' => $this->listing($directory),
                'connection:add' => [$this->savedRow($directory, $alias, $this->add($directory, $alias, $input, $output))],
                'connection:use' => [$this->savedRow($directory, $alias, $this->profiles->use($directory, $alias))],
                'connection:remove' => [$this->savedRow($directory, $alias, $this->profiles->remove($directory, $alias))],
                default => throw new Failure('configuration-error', 'Unknown local connection command.'),
            };
        }

        $this->presenter->success(new OperationResult('connections', ['items' => $items], ['local' => true]), $context->outputMode, $output, $name, []);
        return 0;
    }

    private function add(string $directory, string $alias, InputInterface $input, OutputInterface $output): array
    {
        $this->profiles->assertCanAdd($directory, $alias);
        $task = [];
        foreach (['group-id' => 'groupId', 'creator-id' => 'creatorId', 'responsible-id' => 'responsibleId'] as $flag => $field) {
            if ($input->getOption($flag) !== null) {
                $task[$field] = $this->mapper->id($input->getOption($flag), '--' . $flag);
            }
        }

        $tracking = $input->getOption('allows-time-tracking');
        if (!in_array($tracking, ['true', 'false'], true)) {
            throw Failure::usage('--allows-time-tracking requires true or false.');
        }

        $task['allowsTimeTracking'] = $tracking === 'true';
        if ($input->getOption('webhook-stdin')) {
            $webhook = $this->reader->read('-', 1048576);
            if (strlen($webhook) > 1048576) {
                throw Failure::usage('Webhook input is too large.');
            }
        } else {
            if (!$input->isInteractive() || !defined('STDIN') || !stream_isatty(STDIN)) {
                throw Failure::usage('Use --webhook-stdin with redirected input, or run connection:add in an interactive terminal.');
            }

            $question = new Question('HTTPS incoming webhook: ');
            $question->setHidden(true)->setHiddenFallback(false);
            $webhook = (new QuestionHelper())->ask($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output, $question);
        }

        if (!is_string($webhook)) {
            throw Failure::usage('Provide a webhook through hidden input or --webhook-stdin.');
        }

        return $this->profiles->add($directory, $alias, trim($webhook), $input->getOption('portal-domain'), $task);
    }

    private function listing(string $directory): array
    {
        $config = $this->profiles->load($directory);
        $items = [];
        foreach ($config['profiles'] as $alias => $profile) {
            $items[] = [...$this->savedRow($directory, $alias, $profile), 'isDefault' => $alias === $config['defaultProfile']];
        }

        return $items;
    }

    private function savedRow(string $directory, string $alias, array $profile): array
    {
        $profile['default']['task']['allowsTimeTracking'] ??= false;
        return $this->row(['profile' => $alias, 'source' => 'profile', 'path' => $directory . '/config.json', ...$profile]);
    }

    private function row(array $metadata): array
    {
        return [...$metadata, 'hostname' => $metadata['connection']['hostname'] ?? $metadata['hostname'] ?? null];
    }
}
