<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class RussianListCommand extends ListCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        HelpDescriptors::create()->describe($output, $this->getApplication(), [
            'format' => $input->getOption('format'),
            'raw_text' => $input->getOption('raw'),
            'namespace' => $input->getArgument('namespace'),
            'short' => $input->getOption('short'),
        ]);
        return 0;
    }
}
