<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Bootstrap;

use Bitrix24\CLI\Console\B24Application;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiTransport;
use Bitrix24\CLI\Infrastructure\Connection\ConnectionResolver;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;

final class ApplicationFactory
{
    public function create(string $root, ?ApiTransport $transport = null, ?ConnectionResolver $resolver = null): B24Application
    {
        $container = (new ContainerFactory())->create($root, $transport, $resolver);
        $application = $container->get(B24Application::class);
        $application->setCommandLoader(new ContainerCommandLoader($container, $container->getParameter('b24cli.command_map')));
        return $application;
    }
}
