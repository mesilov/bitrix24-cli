<?php

declare(strict_types=1);

use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Bootstrap\CommandCatalog;
use Bitrix24\CLI\Console\B24Application;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiTransport;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\DependencyInjection\ContainerBuilder;

if (!function_exists('pcntl_signal')) {
    throw new RuntimeException('Unix task cancellation requires pcntl.');
}

$root = dirname(__DIR__);
$container = new ContainerBuilder();
$configure = require $root . '/config/services.php';
$configure($container, $root);
$container->removeAlias(ApiTransport::class);
$container->register(ApiTransport::class)->setSynthetic(true)->setPublic(true);
$container->getDefinition(RuntimeState::class)->setPublic(true);
$container->compile();
$state = $container->get(RuntimeState::class);
$container->set(ApiTransport::class, new readonly class ($state) implements ApiTransport {
    public function __construct(private RuntimeState $state)
    {
    }

    public function call(string $method, int $version, array $parameters, string $effect = 'read'): ApiResponse
    {
        $this->state->checkCancellation();
        if ($method !== 'tasks.task.file.attach' || $version !== 3 || $effect !== 'write') {
            throw new RuntimeException('Unexpected offline signal fixture route.');
        }

        fwrite(STDERR, "READY\n");
        usleep(5000000);
        return new ApiResponse(['result' => true]);
    }
});
$application = $container->get(B24Application::class);
$application->setCommandLoader(new ContainerCommandLoader($container, array_map(static fn (array $command): string => $command['class'], CommandCatalog::COMMANDS)));
exit($application->run(new ArgvInput(['b24cli', '--json', 'task:file:attach', '123', '--file-id', '50', '--file-id', '51', '--file-id', '52'])));
