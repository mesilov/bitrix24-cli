<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Bootstrap\CommandCatalog;
use Bitrix24\CLI\Tests\Integration\Task\ShowTaskCommandTest;
use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use Bitrix24\CLI\Tests\Support\LiveCommandTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

final class IntegrationCommandCoverageTest extends TestCase
{
    public function testEveryApplicationCommandHasAnIntegrationHappyPathScenario(): void
    {
        $directory = __DIR__ . '/../Integration';
        $covered = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), 'Test.php')) {
                continue;
            }

            $relativeClass = substr($file->getPathname(), strlen($directory) + 1, -4);
            $class = new \ReflectionClass('Bitrix24\\CLI\\Tests\\Integration\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relativeClass));
            if ($class->isAbstract() || !$class->isSubclassOf(TestCase::class)) {
                continue;
            }

            self::assertTrue($class->isSubclassOf(LiveCommandTestCase::class), $class->name);
            $groups = array_map(static fn (\ReflectionAttribute $attribute): string => $attribute->newInstance()->name(), $class->getAttributes(Group::class));
            $commands = array_values(array_filter($groups, static fn (string $group): bool => str_starts_with($group, 'command:')));
            self::assertCount(1, $commands, 'Each integration class must belong to exactly one command: ' . $class->name);
            $command = substr($commands[0], strlen('command:'));
            self::assertArrayHasKey($command, CommandCatalog::COMMANDS);
            $commandClass = new \ReflectionClass(CommandCatalog::COMMANDS[$command]['class']);
            self::assertSame(str_replace('\\Console\\Command\\', '\\Tests\\Integration\\', $commandClass->name) . 'Test', $class->name);
            $happyPaths = [];
            foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if (!str_starts_with($method->name, 'test') || $method->isStatic()) {
                    continue;
                }

                foreach ($method->getAttributes(Group::class) as $attribute) {
                    $group = $attribute->newInstance()->name();
                    self::assertFalse(str_starts_with($group, 'command:'), 'Command ownership is declared only on the test class.');
                    if ($group === 'happy-path') {
                        $happyPaths[] = $method->name;
                    }
                }
            }

            self::assertNotEmpty($happyPaths, 'Missing dedicated happy-path method: ' . $command);
            self::assertArrayNotHasKey($command, $covered, 'Duplicate test class for command: ' . $command);
            $covered[$command] = $class->name;
        }

        $expected = array_keys(CommandCatalog::COMMANDS);
        self::assertSame([], array_values(array_diff($expected, array_keys($covered))), 'Commands missing a dedicated integration test class.');
        self::assertSame([], array_values(array_diff(array_keys($covered), $expected)), 'Integration classes referring to unknown commands.');
    }

    public function testLiveHarnessRejectsAnotherCommandBeforeConsoleExecution(): void
    {
        $showTaskCommandTest = new ShowTaskCommandTest('testHappyPathReturnsTaskCard');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Fixtures and readback must use SDK helpers, not another CLI command.');
        (new \ReflectionMethod(LiveTaskCommandTestCase::class, 'invoke'))->invoke($showTaskCommandTest, ['task:add']);
    }
}
