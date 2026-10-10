<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Bootstrap;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Console\ResultPresenter;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

final class Launcher
{
    public static function run(string $root): int
    {
        try {
            return (new ApplicationFactory())->create($root)->run();
        } catch (\Throwable) {
            (new ResultPresenter())->failure(new Failure('configuration-error', 'Cannot initialize the CLI; check dependencies and service definitions.'), (new ArgvInput())->hasParameterOption('--json', true) ? 'json' : 'human', new ConsoleOutput());
            return 1;
        }
    }
}
