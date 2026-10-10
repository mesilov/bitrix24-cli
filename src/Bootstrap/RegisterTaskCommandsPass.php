<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Bootstrap;

use Bitrix24\CLI\Application\Failure;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RegisterTaskCommandsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $map = [];
        foreach ($container->findTaggedServiceIds('console.command') as $id => $tags) {
            $class = $container->getDefinition($id)->getClass() ?? $id;
            if (!class_exists($class)) {
                throw new Failure('configuration-error', 'A command registration must reference an existing class.');
            }

            $attributes = (new \ReflectionClass($class))->getAttributes(AsCommand::class);
            $attributeName = $attributes === [] ? null : $attributes[0]->newInstance()->name;
            foreach ($tags as $tag) {
                $name = $tag['name'] ?? null;
                if (!is_string($name) || $attributeName !== $name || isset($map[$name]) || (CommandCatalog::COMMANDS[$name]['class'] ?? null) !== $class) {
                    throw new Failure('configuration-error', 'Duplicate, aliased or mismatched task command registration.');
                }

                $map[$name] = $id;
            }
        }

        if (array_diff(array_keys(CommandCatalog::COMMANDS), array_keys($map)) !== []) {
            throw new Failure('configuration-error', 'The application does not register the exact MVP command inventory.');
        }

        $container->setParameter('b24cli.command_map', $map);
    }
}
