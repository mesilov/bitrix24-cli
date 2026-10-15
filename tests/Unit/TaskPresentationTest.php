<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Console\ResultPresenter;
use Bitrix24\CLI\Tests\Support\CapturedOutput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Output\OutputInterface;

final class TaskPresentationTest extends TestCase
{
    public function testSemanticCardUsesAvailableNamesAndRetainsExtraFields(): void
    {
        $data = ['task' => ['id' => 123, 'title' => 'Подготовить договор', 'status' => 'in_progress', 'responsible' => ['id' => 42, 'name' => 'Анна Иванова', 'email' => 'anna@example.invalid'], 'groupId' => 7, 'deadline' => '2026-10-15T18:00:00+06:00', 'description' => 'Проверить реквизиты.', 'custom' => ['a' => 1]]];
        $output = new CapturedOutput();
        (new ResultPresenter())->success(new OperationResult('task-card', $data), 'human', $output, 'task:show', []);
        $text = $output->fetch();
        self::assertStringContainsString('123 · Подготовить договор', $text);
        self::assertStringContainsString('В работе (in_progress)', $text);
        self::assertStringContainsString('Анна Иванова (#42)', $text);
        self::assertStringContainsString('Проект: #7', $text);
        self::assertStringContainsString('anna@example.invalid', $text);
        self::assertStringContainsString('custom', $text);
        self::assertStringContainsString('{"a":1}', $text);
        self::assertLessThan(strpos($text, 'Описание'), strpos($text, 'Срок:'));
        $json = new CapturedOutput();
        (new ResultPresenter())->success(new OperationResult('task-card', $data), 'json', $json, 'task:show', []);
        self::assertSame($data, json_decode($json->fetch(), true, 512, JSON_THROW_ON_ERROR)['data']);
    }

    public function testNarrowUnicodeCardDoesNotLoseTextOrExecuteTerminalControls(): void
    {
        $previousWidth = getenv('COLUMNS');
        putenv('COLUMNS=32');
        try {
            $capturedOutput = new CapturedOutput();
            $title = str_repeat('界', 35) . ' <error>данные</error>';
            $description = str_repeat('Длинное русское описание ', 8) . "\033[31mцвет\rперезапись\x08";
            (new ResultPresenter())->success(new OperationResult('task-card', ['task' => ['id' => 9, 'title' => $title, 'description' => $description]]), 'human', $capturedOutput, 'task:show', []);
            $text = $capturedOutput->fetch();
            foreach (explode("\n", trim($text)) as $line) {
                self::assertLessThanOrEqual(32, Helper::width($line), $line);
            }

            self::assertStringNotContainsString("\033", $text);
            self::assertStringNotContainsString("\r", $text);
            self::assertStringNotContainsString("\x08", $text);
            self::assertSame(35, substr_count($text, '界'));
            self::assertStringContainsString('<error>данные</error>', str_replace("\n", '', $text));
            self::assertSame(8, substr_count(str_replace("\n", '', $text), 'Длинное русское описание '));
        } finally {
            putenv($previousWidth === false ? 'COLUMNS' : 'COLUMNS=' . $previousWidth);
        }
    }

    public function testCompleteLimitedAndEmptySummariesObeyStreamsAndQuiet(): void
    {
        $meta = ['scope' => 'tasks-visible-to-connection', 'returned' => 1, 'matched' => 3, 'scanned' => 5, 'complete' => true, 'limitApplied' => true, 'order' => ['id' => 'DESC']];
        $operationResult = new OperationResult('title-matches', ['items' => [['id' => 9, 'title' => 'Договор']]], $meta);
        $capturedOutput = new CapturedOutput();
        (new ResultPresenter())->success($operationResult, 'plain', $capturedOutput, 'task:find', []);
        self::assertSame("9\tДоговор\n", $capturedOutput->fetch());
        $summary = $capturedOutput->getErrorOutput()->fetch();
        self::assertStringContainsString('Показано 1, найдено 3, просмотрено 5', $summary);
        self::assertStringContainsString('complete=true', $summary);
        self::assertStringContainsString('limitApplied=true', $summary);
        self::assertStringContainsString('id:DESC', $summary);
        $capturedOutput->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        (new ResultPresenter())->success($operationResult, 'plain', $capturedOutput, 'task:find', []);
        self::assertSame('', $capturedOutput->fetch() . $capturedOutput->getErrorOutput()->fetch());
        $capturedOutput->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
        (new ResultPresenter())->success(new OperationResult('title-matches', ['items' => []], [...$meta, 'returned' => 0, 'matched' => 0, 'limitApplied' => false]), 'human', $capturedOutput, 'task:find', []);
        self::assertStringContainsString('Совпадений нет', $capturedOutput->fetch());
    }

    public function testPartialDiagnosticsIncludeSafeNextStepAndSurviveQuiet(): void
    {
        foreach (['scan-budget-exhausted' => '--max-scan', 'page-error' => 'Повторите', 'missing-id' => 'данные', 'missing-predicate-field' => 'данные', 'pagination-no-progress' => 'пагинацию', 'role-read-error' => 'участников'] as $reason => $hint) {
            $result = new OperationResult('title-matches', ['items' => []], ['scope' => 'explicit-task-ids', 'returned' => 0, 'matched' => 0, 'scanned' => 5, 'complete' => false, 'limitApplied' => false, 'reason' => $reason, 'order' => ['id' => 'DESC']], 3);
            $output = new CapturedOutput();
            $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
            (new ResultPresenter())->success($result, 'human', $output, 'task:find', []);
            self::assertSame('', $output->fetch());
            $error = $output->getErrorOutput()->fetch();
            self::assertStringContainsString($reason, $error);
            self::assertStringContainsString($hint, $error);
            $output->setVerbosity(OutputInterface::VERBOSITY_SILENT);
            (new ResultPresenter())->success($result, 'human', $output, 'task:find', []);
            self::assertSame('', $output->fetch() . $output->getErrorOutput()->fetch());
        }
    }
    public function testHumanRenderingKeepsTheRequestProjectionAndCallCount(): void
    {
        $fixturePortal = new \Bitrix24\CLI\Tests\Support\FixturePortal();
        $fixturePortal->task['responsible'] = ['id' => 42, 'name' => 'Анна'];
        $select = ['id', 'title', 'responsible.id', 'responsible.name', 'group.id'];
        $calls = [];
        foreach (['human', 'json', 'plain'] as $mode) {
            $api = new \Bitrix24\CLI\Tests\Support\FakeApiTransport($fixturePortal->respond(...));
            $argv = ['task:show', '123'];
            foreach ($select as $field) {
                $argv[] = '--select';
                $argv[] = $field;
            }

            if ($mode !== 'human') {
                $argv[] = '--' . $mode;
            }

            $run = \Bitrix24\CLI\Tests\Support\ConsoleHarness::run((new \Bitrix24\CLI\Bootstrap\ApplicationFactory())->create(dirname(__DIR__, 2), $api), $argv);
            self::assertSame(0, $run->status, $run->stdout . $run->stderr);
            self::assertCount(1, $api->calls);
            self::assertSame($select, $api->calls[0]['parameters']['select']);
            $calls[] = $api->calls;
        }

        self::assertSame($calls[0], $calls[1]);
        self::assertSame($calls[0], $calls[2]);
    }

    public function testTablesFitTerminalWidthAndRenderMarkupAndControlsAsData(): void
    {
        $previousWidth = getenv('COLUMNS');
        try {
            foreach (['title-matches' => 32, 'task-list' => 110] as $profile => $width) {
                putenv('COLUMNS=' . $width);
                $capturedOutput = new CapturedOutput();
                (new ResultPresenter())->success(new OperationResult($profile, ['items' => [['id' => 123, 'title' => str_repeat('界', 25) . '<error>текст</error>' . "\033]8;;https://portal.invalid\x07", 'responsibleId' => 42, 'groupId' => 7, 'status' => 'unknown_status', 'deadline' => null]]], ['scope' => 'tasks-visible-to-connection', 'scanned' => 1, 'matched' => 1, 'returned' => 1, 'complete' => true, 'limitApplied' => false, 'order' => ['id' => 'DESC']]), 'human', $capturedOutput, 'task:list', []);
                $text = $capturedOutput->fetch();
                foreach (explode("\n", trim($text)) as $line) {
                    self::assertLessThanOrEqual($width, Helper::width($line), $line);
                }

                self::assertSame(25, substr_count($text, '界'));
                self::assertStringNotContainsString("\033", $text);
                self::assertStringNotContainsString("\x07", $text);
                $titleText = '';
                foreach (explode("\n", $text) as $line) {
                    if (preg_match('/^\|[^|]*\| (.*?) *\|/u', $line, $cell)) {
                        $titleText .= $cell[1];
                    }
                }

                self::assertStringContainsString('<error>текст</error>', $titleText);
            }
        } finally {
            putenv($previousWidth === false ? 'COLUMNS' : 'COLUMNS=' . $previousWidth);
        }
    }

}
