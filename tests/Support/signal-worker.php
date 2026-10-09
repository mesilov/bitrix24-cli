<?php

declare(strict_types=1);

use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Bootstrap\RegisterTaskCommandsPass;
use Bitrix24\CLI\Console\B24Application;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiTransport;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$root = dirname(__DIR__, 2);
$container = new ContainerBuilder();
$configure = require $root . '/config/services.php';
$configure($container, $root);
$container->removeAlias(ApiTransport::class);
$container->register(ApiTransport::class)->setSynthetic(true)->setPublic(true);
$container->getDefinition(RuntimeState::class)->setPublic(true);
$container->addCompilerPass(new RegisterTaskCommandsPass());
$container->compile();
$state = $container->get(RuntimeState::class);
$api = new FakeApiTransport(static function () use ($state): ApiResponse {
    $state->checkCancellation();
    fwrite(STDERR, "READY\n");
    usleep(5000000);
    return new ApiResponse(['result' => true]);
});
$container->set(ApiTransport::class, $api);
$app = $container->get(B24Application::class);
$app->setCommandLoader(new ContainerCommandLoader($container, $container->getParameter('b24cli.command_map')));
exit($app->run(new ArgvInput(['b24cli', '--json', 'task:file:attach', '123', '--file-id', '50', '--file-id', '51', '--file-id', '52'])));
