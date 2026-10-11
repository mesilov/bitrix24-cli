<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;

final class ConsoleHarness
{
    public static function run(Application $app, array $argv, ?string $stdin = null): ConsoleRun
    {
        $argvInput = new ArgvInput(['b24cli', ...$argv]);
        $argvInput->setInteractive(false);
        if ($stdin !== null) {
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, $stdin);
            rewind($stream);
            $argvInput->setStream($stream);
        }

        $capturedOutput = new CapturedOutput();
        $status = $app->run($argvInput, $capturedOutput);
        return new ConsoleRun($status, $capturedOutput->fetch(), $capturedOutput->getErrorOutput()->fetch());
    }
}
