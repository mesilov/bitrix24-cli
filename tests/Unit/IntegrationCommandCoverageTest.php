<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Bootstrap\CommandCatalog;
use PHPUnit\Framework\Attributes\Group;
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

            foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if (!str_starts_with($method->name, 'test') || $method->isStatic()) {
                    continue;
                }

                foreach ($method->getAttributes(Group::class) as $attribute) {
                    $group = $attribute->newInstance()->name();
                    if (str_starts_with($group, 'command:')) {
                        $covered[] = substr($group, strlen('command:'));
                    }
                }
            }
        }

        $expected = array_keys(CommandCatalog::COMMANDS);
        self::assertSame([], array_values(array_diff($expected, $covered)), 'Commands missing an integration happy-path group.');
        self::assertSame([], array_values(array_diff($covered, $expected)), 'Integration groups referring to unknown commands.');
    }
}
