<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Bootstrap;

use Bitrix24\CLI\Infrastructure\Bitrix24\ApiTransport;
use Bitrix24\CLI\Infrastructure\Connection\ConnectionResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ContainerFactory
{
    public function create(string $root, ?ApiTransport $transport = null, ?ConnectionResolver $resolver = null): ContainerBuilder
    {
        $containerBuilder = new ContainerBuilder();
        $configure = require $root . '/config/services.php';
        $configure($containerBuilder, $root);
        foreach ([ApiTransport::class => $transport, ConnectionResolver::class => $resolver] as $id => $replacement) {
            if ($replacement !== null) {
                $containerBuilder->removeAlias($id);
                $containerBuilder->register($id)->setSynthetic(true)->setPublic(true);
            }
        }

        $containerBuilder->addCompilerPass(new RegisterTaskCommandsPass());
        $containerBuilder->compile();
        foreach ([ApiTransport::class => $transport, ConnectionResolver::class => $resolver] as $id => $replacement) {
            if ($replacement !== null) {
                $containerBuilder->set($id, $replacement);
            }
        }

        return $containerBuilder;
    }
}
