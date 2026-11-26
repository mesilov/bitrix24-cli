<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Console\WebhookPrompt;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

final class WebhookPromptTest extends TestCase
{
    public function testInteractiveTerminalShowsTheHintBeforeTheHiddenRussianQuestion(): void
    {
        $helper = new class () extends QuestionHelper {
            public ?Question $question = null;
            public string $outputBeforeQuestion = '';

            public function ask(InputInterface $input, OutputInterface $output, Question $question): mixed
            {
                $this->question = $question;
                $this->outputBeforeQuestion = $output instanceof BufferedOutput ? $output->fetch() : '';
                $output->write($question->getQuestion());
                return 'https://fixture.invalid/rest/1/RecognizableFixtureSecret/';
            }
        };
        $bufferedOutput = new BufferedOutput();

        $answer = (new WebhookPrompt())->ask($this->input(true), $bufferedOutput, true, $helper);

        self::assertSame('https://fixture.invalid/rest/1/RecognizableFixtureSecret/', $answer);
        self::assertSame(implode(PHP_EOL, WebhookPrompt::HINT) . PHP_EOL, $helper->outputBeforeQuestion);
        self::assertStringContainsString('Разработчикам → Готовые сценарии → Другое → Входящий вебхук', $helper->outputBeforeQuestion);
        self::assertStringContainsString('Ввод скрыт', $helper->outputBeforeQuestion);
        self::assertStringContainsString('docs/auth.md#incoming-webhook', $helper->outputBeforeQuestion);
        self::assertInstanceOf(Question::class, $helper->question);
        self::assertSame('URL входящего вебхука: ', $helper->question->getQuestion());
        self::assertTrue($helper->question->isHidden());
        self::assertFalse($helper->question->isHiddenFallback());
        self::assertSame('URL входящего вебхука: ', $bufferedOutput->fetch());
    }

    public function testNoTerminalOrNonInteractiveInputFailsBeforeAnyOutput(): void
    {
        $helper = new class () extends QuestionHelper {
            public function ask(InputInterface $input, OutputInterface $output, Question $question): mixed
            {
                throw new \LogicException('The question must not be asked.');
            }
        };

        foreach ([[true, false], [false, true]] as [$interactive, $terminal]) {
            $errorOutput = new BufferedOutput();
            try {
                (new WebhookPrompt())->ask($this->input($interactive), $errorOutput, $terminal, $helper);
                self::fail('A missing terminal must fail.');
            } catch (Failure $failure) {
                self::assertSame('usage-error', $failure->errorCode);
                self::assertSame(2, $failure->exitStatus);
                self::assertStringContainsString('--webhook-stdin', $failure->getMessage());
            }

            self::assertSame('', $errorOutput->fetch());
        }
    }

    private function input(bool $interactive): ArrayInput
    {
        $arrayInput = new ArrayInput([]);
        $arrayInput->setInteractive($interactive);
        return $arrayInput;
    }
}
