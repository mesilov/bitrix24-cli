<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Bootstrap\CommandCatalog;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Attribute\AsCommand;

final class ContainerRegistrationTest extends TestCase
{
    public function testExplicitCatalogMatchesAttributesAndLoadedCommandsWithoutCredentials(): void
    {
        $fakeApiTransport = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected API call.'));
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        $classes = [];
        foreach (CommandCatalog::COMMANDS as $name => $definition) {
            $attributes = (new \ReflectionClass($definition['class']))->getAttributes(AsCommand::class);
            self::assertCount(1, $attributes);
            self::assertSame($name, $attributes[0]->newInstance()->name);
            $command = $app->get($name);
            self::assertSame($definition['class'], $command::class);
            self::assertSame($name, $command->getName());
            self::assertSame([], $command->getAliases());
            $classes[] = $command::class;
        }

        self::assertCount(54, array_unique($classes));
        foreach (CommandCatalog::LOCAL_COMMANDS as $name => $definition) {
            $attribute = (new \ReflectionClass($definition['class']))->getAttributes(AsCommand::class)[0]->newInstance();
            self::assertSame($name, $attribute->name);
            self::assertSame($definition['class'], $app->get($name)::class);
        }

        self::assertSame(0, $spyConnectionResolver->reads);
        self::assertSame([], $fakeApiTransport->calls);
    }
}
