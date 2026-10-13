<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UsageDiagnosticsTest extends TestCase
{
    public static function invalid(): iterable
    {
        yield 'typo' => [['task:find', '--titel', 'secret-value'], ['--titel', '--title']];
        yield 'removed group alias' => [['task:find', '--title', 'x', '--project', '7'], ['--project', '--group-id']];
        yield 'missing value' => [['task:find', '--title'], ['--title', 'требует значения']];
        yield 'missing argument' => [['task:show'], ['TASK_ID', 'обязательный аргумент']];
        yield 'extra secret argument' => [['task:show', '123', 'secret-value'], ['лишний позиционный']];
        yield 'conflicting formats' => [['task:find', '--title', 'x', '--json', '--plain'], ['--json', '--plain']];
        yield 'missing title flag' => [['task:find'], ['--title']];
        yield 'misspelled command' => [['task:shwo', '123'], ['task:show']];
    }

    #[DataProvider('invalid')]
    public function testSafeSpecificErrorsNeverCallApi(array $argv, array $expected): void
    {
        foreach ([[], ['--json']] as $format) {
            $api = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected API call'));
            $resolver = new SpyConnectionResolver();
            $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api, $resolver), [...$format, ...$argv]);
            self::assertSame(2, $run->status, $run->stdout . $run->stderr);
            $text = $format === [] && !in_array('--json', $argv, true) ? $run->stderr : $run->json()['error']['message'];
            foreach ($expected as $fragment) {
                self::assertStringContainsString($fragment, $text);
            }

            self::assertStringContainsString('Пример:', $text);
            self::assertStringNotContainsString('secret-value', $text);
            self::assertSame([], $api->calls);
            self::assertSame(0, $resolver->reads);
        }
    }

    public function testUnknownNamesAndValuesAreNotReflected(): void
    {
        foreach ([['task:find', '--super-secret-value=token-secret'], ['task:find', '--https://portal.invalid/rest/1/token-secret/'], ['task:find', '--json=token-secret'], ['secret-value'], ['task:find', '--title', 'https://portal.invalid/rest/1/token-secret/', 'secret-value']] as $argv) {
            foreach ([[], ['--json']] as $format) {
                $api = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected API call'));
                $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), [...$format, ...$argv]);
                self::assertSame(2, $run->status, $run->stdout . $run->stderr);
                foreach (['token-secret', 'secret-value', 'portal.invalid', 'Stack trace', "\033"] as $secret) {
                    self::assertStringNotContainsString($secret, $run->stdout . $run->stderr);
                }

                self::assertSame([], $api->calls);
            }
        }
    }
    public function testHelpExamplesPrecedeOptionsForEveryTaskCommandWithoutCredentials(): void
    {
        $fakeApiTransport = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected API call'));
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        foreach (array_keys(\Bitrix24\CLI\Bootstrap\CommandCatalog::COMMANDS) as $name) {
            if (!str_starts_with($name, 'task:')) {
                continue;
            }

            $direct = ConsoleHarness::run($app, [$name, '--help']);
            $explicit = ConsoleHarness::run($app, ['help', $name]);
            self::assertSame(0, $direct->status, $direct->stderr);
            self::assertSame($direct->stdout, $explicit->stdout, $name);
            self::assertStringContainsString('Назначение:', $direct->stdout);
            self::assertStringContainsString('b24cli ' . $name, $direct->stdout);
            self::assertLessThan(strpos($direct->stdout, 'Опции:'), strpos($direct->stdout, 'Примеры:'));
            self::assertLessThan(strpos($direct->stdout, 'Пояснения и ограничения:'), strpos($direct->stdout, 'Опции:'));
        }

        foreach ([['help', 'task:show', '--format=json'], ['help', 'task:update', '--format=xml'], ['--version'], ['list', 'task'], ['help', 'task:find']] as $argv) {
            $run = ConsoleHarness::run($app, $argv);
            self::assertSame(0, $run->status, $run->stderr);
            if ($argv === ['help', 'task:find']) {
                self::assertStringContainsString('--group-id 7 --responsible 42', $run->stdout);
            }
        }

        self::assertSame([], $fakeApiTransport->calls);
        self::assertSame(0, $spyConnectionResolver->reads);
    }

    public function testNoColorDumbTerminalAndQuietErrorsPreserveNativeBehavior(): void
    {
        $previousColor = getenv('NO_COLOR');
        $previousTerm = getenv('TERM');
        try {
            foreach ([['NO_COLOR=1', 'TERM=xterm'], ['NO_COLOR', 'TERM=dumb']] as [$color, $term]) {
                putenv($color);
                putenv($term);
                foreach ([[], ['--quiet'], ['--silent'], ['--no-ansi', '--no-interaction']] as $flags) {
                    $fakeApiTransport = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected API.'));
                    $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport);
                    $run = ConsoleHarness::run($app, [...$flags, '--json', 'task:find', '--titel', 'secret-value']);
                    self::assertSame(2, $run->status);
                    self::assertStringNotContainsString("\033", $run->stdout . $run->stderr);
                    self::assertStringNotContainsString('secret-value', $run->stdout . $run->stderr);
                    if ($flags === ['--silent']) {
                        self::assertSame('', $run->stdout . $run->stderr);
                    } elseif ($flags === ['--quiet']) {
                        self::assertSame('', $run->stdout);
                        self::assertStringContainsString('usage-error', $run->stderr);
                    } else {
                        self::assertSame('usage-error', $run->json()['error']['code']);
                    }

                    self::assertSame([], $fakeApiTransport->calls);
                }
            }
        } finally {
            putenv($previousColor === false ? 'NO_COLOR' : 'NO_COLOR=' . $previousColor);
            putenv($previousTerm === false ? 'TERM' : 'TERM=' . $previousTerm);
        }
    }

}
