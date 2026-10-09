<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class CapturedOutput extends BufferedOutput implements ConsoleOutputInterface
{
    private BufferedOutput $error;

    public function __construct()
    {
        $this->error = new BufferedOutput(self::VERBOSITY_NORMAL, false);
        parent::__construct(self::VERBOSITY_NORMAL, false);
    }

    public function getErrorOutput(): BufferedOutput
    {
        return $this->error;
    }

    public function setErrorOutput(OutputInterface $error): void
    {
        if (!$error instanceof BufferedOutput) {
            throw new \LogicException('The harness requires a buffered error stream.');
        }

        $this->error = $error;
    }

    public function section(): ConsoleSectionOutput
    {
        throw new \LogicException('Sections are outside this capture harness.');
    }

    public function setVerbosity(int $level): void
    {
        parent::setVerbosity($level);
        $this->error->setVerbosity($level);
    }
}
