<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Descriptor\TextDescriptor;
use Symfony\Component\Console\Formatter\OutputFormatter;

final class TaskHelpDescriptor extends TextDescriptor
{
    protected function describeCommand(Command $command, array $options = []): void
    {
        if (!str_starts_with($command->getName() ?? '', 'task:')) {
            parent::describeCommand($command, $options);
            return;
        }

        $command->mergeApplicationDefinition(false);
        $this->writeText("<comment>Назначение:</comment>\n  " . $command->getDescription() . "\n\n<comment>Примеры:</comment>\n", $options);
        foreach (TaskUsage::examples($command) as $example) {
            $this->writeText('  ' . OutputFormatter::escape($example) . "\n", $options);
        }

        $this->writeText("\n<comment>Usage:</comment>\n  " . OutputFormatter::escape($command->getSynopsis(true)) . "\n\n", $options);
        $this->describeInputDefinition($command->getDefinition(), $options);
        $this->writeText("\n\n<comment>Advanced input и ограничения:</comment>\n  " . $command->getProcessedHelp() . "\n\nДокументация: https://github.com/mesilov/bitrix24-cli#команды-задач\n", $options);
    }
    private function writeText(string $content, array $options = []): void
    {
        $this->write(($options['raw_text'] ?? false) ? strip_tags($content) : $content, !($options['raw_output'] ?? false));
    }

}
