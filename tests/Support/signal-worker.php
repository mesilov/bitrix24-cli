<?php

declare(strict_types=1);

use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Bootstrap\CommandCatalog;
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
$container->compile();
$state = $container->get(RuntimeState::class);
$local = ($argv[1] ?? '') === 'local';
$path = sys_get_temp_dir() . '/b24cli-signal-' . bin2hex(random_bytes(5)) . '.bin';
$api = new FakeApiTransport(static function (string $method) use ($state, $local): ApiResponse {
    $state->checkCancellation();
    fwrite(STDERR, "READY\n");
    usleep(5000000);
    return new ApiResponse($local ? ['ID' => '789', 'NAME' => 'signal.bin'] : ['result' => true]);
});
$container->set(ApiTransport::class, $api);
$app = $container->get(B24Application::class);
$app->setCommandLoader(new ContainerCommandLoader($container, array_map(static fn (array $command): string => $command['class'], CommandCatalog::COMMANDS)));
if ($local) {
    file_put_contents($path, 'Disposable signal fixture');
}

try {
    $arguments = $local ? ['--file', $path, '--folder-id', '456'] : ['--file-id', '50', '--file-id', '51', '--file-id', '52'];
    $status = $app->run(new ArgvInput(['b24cli', '--json', 'task:file:attach', '123', ...$arguments]));
} finally {
    if ($local) {
        unlink($path);
    }
}

exit($status);
