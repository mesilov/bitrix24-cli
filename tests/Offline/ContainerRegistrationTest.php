<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Offline;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Bootstrap\RegisterTaskCommandsPass;
use Bitrix24\CLI\Console\Command\Task\AddTaskCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ContainerRegistrationTest extends TestCase
{
    public static function invalidRegistrations(): iterable
    {
        yield 'duplicate' => ['task:add', true];
        yield 'mismatch' => ['task:show', false];
        yield 'alias' => ['task:create', false];
    }

    #[DataProvider('invalidRegistrations')]
    public function testCompilerPassRejectsBadRegistration(string $name, bool $duplicate): void
    {
        $containerBuilder = new ContainerBuilder();
        $configure = require dirname(__DIR__, 2) . '/config/services.php';
        $configure($containerBuilder, dirname(__DIR__, 2));
        $definition = $containerBuilder->getDefinition(AddTaskCommand::class);
        if (!$duplicate) {
            $definition->clearTag('console.command');
        }

        $definition->addTag('console.command', ['name' => $name]);
        $this->expectException(Failure::class);
        (new RegisterTaskCommandsPass())->process($containerBuilder);
    }
}
