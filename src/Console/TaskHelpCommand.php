<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class TaskHelpCommand extends HelpCommand
{
    private ?Command $target = null;

    public function setCommand(Command $command): void
    {
        $this->target = $command;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command = $this->target ?? $this->getApplication()->find($input->getArgument('command_name'));
        try {
            HelpDescriptors::create()->describe($output, $command, ['format' => $input->getOption('format'), 'raw_text' => $input->getOption('raw')]);
            return 0;
        } finally {
            $this->target = null;
        }
    }
}
