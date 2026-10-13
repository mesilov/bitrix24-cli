<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Console\BuiltinHelpMetadata;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\CapturedOutput;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\FixturePortal;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\DumpCompletionCommand;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class RussianHelpTest extends TestCase
{
    public function testEveryPublicCommandHasRussianMetadataAndBothHelpRoutes(): void
    {
        $spyConnectionResolver = new SpyConnectionResolver();
        $fixturePortal = new FixturePortal();
        $fakeApiTransport = new FakeApiTransport($fixturePortal->respond(...));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        foreach ($app->all() as $name => $command) {
            if ($command->isHidden()) {
                continue;
            }

            $command->mergeApplicationDefinition(false);
            foreach ([$command->getDescription(), $command->getHelp(), ...array_map(static fn ($item): string => $item->getDescription(), [...$command->getDefinition()->getArguments(), ...$command->getDefinition()->getOptions()])] as $text) {
                self::assertMatchesRegularExpression('/[А-Яа-яЁё]/u', $text, $name . ': ' . $text);
                self::assertDoesNotMatchRegularExpression('/\b(envelope|columns|companion|credentials|metadata|Advanced|editable metadata|temporal flags|IANA timezone|redirected|subtree|scope|plaintext)\b/', $text, $name);
            }

            $direct = ConsoleHarness::run($app, [$name, '--help']);
            $explicit = ConsoleHarness::run($app, ['help', $name]);
            self::assertSame(0, $direct->status, $name . $direct->stderr);
            self::assertSame(0, $explicit->status, $name . $explicit->stderr);
            self::assertSame($direct->stdout, $explicit->stdout, $name);
            foreach (['Назначение:', 'Использование:', 'Опции:', 'Пояснения и ограничения:'] as $heading) {
                self::assertStringContainsString($heading, $direct->stdout, $name);
            }

            self::assertDoesNotMatchRegularExpression('/Usage:|Arguments:|Options:|Help:|\[default:|multiple values allowed|\[options\]/', $direct->stdout, $name);
        }

        self::assertSame(0, $spyConnectionResolver->reads);
        self::assertSame([], $fakeApiTransport->calls);
    }

    public function testRootGroupsAreUniqueOrderedAndDerivedFromRegistry(): void
    {
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2));
        foreach (['aaa', 'zzz', 'aaa:read', 'zzz:read', 'aaa:nested:read'] as $name) {
            $app->addCommand(new Command($name)->setDescription('Дополнительная команда'));
        }

        $app->addCommand(new Command('aaa:hidden')->setHidden(true));
        $run = ConsoleHarness::run($app, ['--help']);
        self::assertSame($run->stdout, ConsoleHarness::run($app, ['-h'])->stdout);
        preg_match_all('/^  (Общие|[a-z]+)$/m', $run->stdout, $groups);
        self::assertSame(['Общие', 'aaa', 'connection', 'doctor', 'task', 'users', 'zzz'], $groups[1]);
        preg_match('/^  Общие\n(.*?)(?=^  aaa$)/ms', $run->stdout, $common);
        preg_match_all('/^    ([^ ]+) — /m', $common[1], $names);
        self::assertSame(['aaa', 'completion', 'help', 'list', 'zzz'], $names[1]);
        preg_match_all('/^    ([^ ]+) — /m', $run->stdout, $visible);
        $expected = array_keys(array_filter($app->all(), static fn (Command $command): bool => !$command->isHidden()));
        sort($expected);
        $actual = $visible[1];
        sort($actual);
        self::assertSame($expected, $actual);
        self::assertStringNotContainsString('aaa:hidden', $run->stdout);
        self::assertStringNotContainsString('[global options]', $run->stdout);
        self::assertStringContainsString('[глобальные опции]', $run->stdout);
        self::assertStringNotContainsString('Options:', $run->stdout);
        self::assertStringContainsString('Общие', ConsoleHarness::run($app, ['list'])->stdout);
        self::assertStringContainsString('Команды пространства имён "task":', ConsoleHarness::run($app, ['list', 'task'])->stdout);
    }

    public function testBuiltinDefinitionsAndCompletionSuggestionsKeepNativeSemantics(): void
    {
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2));
        foreach ([new HelpCommand(), new ListCommand(), new DumpCompletionCommand()] as $native) {
            $native->setApplication($app);
            $localized = $app->get($native->getName())->getNativeDefinition();
            $original = $native->getNativeDefinition();
            self::assertSame(array_keys($original->getOptions()), array_keys($localized->getOptions()));
            self::assertSame(array_keys($original->getArguments()), array_keys($localized->getArguments()));
            foreach ($original->getOptions() as $name => $option) {
                self::assertTrue($option->equals($localized->getOption($name)), $native->getName() . ' --' . $name);
                self::assertSame($option->hasCompletion(), $localized->getOption($name)->hasCompletion());
                self::assertSame($this->suggestions($option), $this->suggestions($localized->getOption($name)));
            }

            foreach ($original->getArguments() as $name => $argument) {
                $other = $localized->getArgument($name);
                self::assertSame([$argument->isRequired(), $argument->isArray(), $argument->getDefault(), $argument->hasCompletion()], [$other->isRequired(), $other->isArray(), $other->getDefault(), $other->hasCompletion()]);
                self::assertSame($this->suggestions($argument), $this->suggestions($other));
            }
        }

        foreach ((new Application())->getDefinition()->getOptions() as $name => $option) {
            self::assertTrue($option->equals($app->getDefinition()->getOption($name)), $name);
        }

        $inputOption = new InputOption('values', 'x', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Values', ['one'], ['one', 'two']);
        $translated = BuiltinHelpMetadata::option($inputOption, 'Значения');
        self::assertTrue($inputOption->equals($translated));
        self::assertSame(['one', 'two'], $this->suggestions($translated));
    }

    public function testAllHelpFormatsLocalizeDescriptionsAndRetainNativeSchemas(): void
    {
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2));
        foreach ($app->all() as $name => $command) {
            if ($command->isHidden()) {
                continue;
            }

            foreach (['json', 'xml', 'md'] as $format) {
                $run = ConsoleHarness::run($app, ['help', $name, '--format', $format]);
                self::assertSame(0, $run->status, $name . ': ' . $run->stderr);
                self::assertStringNotContainsString('Negate the', $run->stdout);
                self::assertDoesNotMatchRegularExpression('/### (Usage|Arguments|Options)|\* (Is |Accept value:|Default:)/', $run->stdout);
                $native = new BufferedOutput();
                (new DescriptorHelper())->describe($native, $command, ['format' => $format]);
                if ($format === 'json') {
                    $actual = json_decode($run->stdout, true, 512, JSON_THROW_ON_ERROR);
                    $expected = json_decode($native->fetch(), true, 512, JSON_THROW_ON_ERROR);
                    $expected['definition']['options']['no-ansi']['description'] = 'Отключить опцию "--ansi"';
                    self::assertSame($expected, $actual, $name);
                    self::assertSame($command->getDescription(), $actual['description']);
                } elseif ($format === 'xml') {
                    $document = new \DOMDocument();
                    self::assertTrue($document->loadXML($run->stdout));
                    self::assertSame(str_replace('Negate the "--ansi" option', 'Отключить опцию "--ansi"', $native->fetch()), $run->stdout, $name);
                } else {
                    self::assertStringContainsString('### Использование', $run->stdout);
                    self::assertStringContainsString('### Опции', $run->stdout);
                    self::assertStringContainsString('* По умолчанию:', $run->stdout);
                }
            }
        }

        $plain = ConsoleHarness::run($app, ['help', 'completion', '--raw']);
        self::assertStringNotContainsString('<comment>', $plain->stdout);
        self::assertStringContainsString('Оболочки: bash, fish, zsh', $plain->stdout);
        self::assertStringContainsString('(можно повторять)', ConsoleHarness::run($app, ['task:add', '--help'])->stdout);
        self::assertStringContainsString('[по умолчанию: "record"]', ConsoleHarness::run($app, ['doctor:problem-trajectory', '--help'])->stdout);
        self::assertStringContainsString('groupId', ConsoleHarness::run($app, ['task:add', '--help'])->stdout);
    }

    public function testHelpKeepsQuietSilentAndDecorationBehavior(): void
    {
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2));
        foreach ([['--help'], ['-h'], ['--about'], ['list'], ['help', 'list'], ['help', 'completion'], ['task:add', '--help']] as $argv) {
            foreach (['--quiet', '--silent'] as $flag) {
                $run = ConsoleHarness::run($app, [...$argv, $flag]);
                self::assertSame(0, $run->status);
                self::assertSame('', $run->stdout);
                self::assertSame('', $run->stderr);
            }

            $capturedOutput = new CapturedOutput();
            $capturedOutput->setDecorated(true);
            $input = new ArgvInput(['b24cli', ...$argv, '--no-ansi']);
            self::assertSame(0, $app->run($input, $capturedOutput));
            self::assertStringNotContainsString("\033[", $capturedOutput->fetch());
        }

        $capturedOutput = new CapturedOutput();
        $capturedOutput->setDecorated(true);
        self::assertSame(0, $app->run(new ArgvInput(['b24cli', 'task:add', '--help', '--ansi']), $capturedOutput));
        self::assertStringContainsString("\033[", $capturedOutput->fetch());
    }

    private function suggestions(object $definition): array
    {
        $completionSuggestions = new CompletionSuggestions();
        $definition->complete(CompletionInput::fromTokens(['b24cli', ''], 1), $completionSuggestions);
        return array_map(static fn ($value): string => (string) $value, $completionSuggestions->getValueSuggestions());
    }
}
