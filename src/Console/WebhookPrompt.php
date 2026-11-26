<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Failure;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Hidden interactive webhook input for auth:login. The hint and the question go to the
 * error output only, so stdout stays reserved for the command result.
 */
final readonly class WebhookPrompt
{
    public const HINT = [
        'Вставьте URL входящего вебхука Битрикс24. Ввод скрыт: в адресе есть секретный код.',
        'Где взять: Разработчикам → Готовые сценарии → Другое → Входящий вебхук, поле «Вебхук для вызова rest api».',
        'Пошаговая инструкция и права: https://github.com/mesilov/bitrix24-cli/blob/main/docs/auth.md#incoming-webhook',
    ];

    public const QUESTION = 'URL входящего вебхука: ';

    /**
     * $terminal and $helper exist for tests; production detects the terminal from STDIN.
     */
    public function ask(InputInterface $input, OutputInterface $errorOutput, ?bool $terminal = null, ?QuestionHelper $helper = null): mixed
    {
        $terminal ??= defined('STDIN') && stream_isatty(STDIN);
        if (!$input->isInteractive() || !$terminal) {
            throw Failure::usage('Use --webhook-stdin with redirected input, or run auth:login in an interactive terminal.');
        }

        foreach (self::HINT as $line) {
            $errorOutput->writeln($line, OutputInterface::OUTPUT_RAW);
        }

        $question = new Question(self::QUESTION);
        $question->setHidden(true)->setHiddenFallback(false);

        return ($helper ?? new QuestionHelper())->ask($input, $errorOutput, $question);
    }
}
