<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;

$mode = $argv[1];
$reads = 0;
$api = new FakeApiTransport(static function (string $method) use ($mode, &$reads): ApiResponse {
    if ($method === 'tasks.task.get') {
        if (++$reads > 1) {
            fwrite(STDERR, "UNEXPECTED_POST_READ\n");
        }

        return new ApiResponse(['item' => ['id' => 123, 'status' => 'pending', 'needsControl' => false, 'requireResult' => false, 'containsResults' => false, 'autocompleteSubTasks' => false]]);
    }

    if ($method === 'tasks.task.access.get') {
        return new ApiResponse(['complete' => true]);
    }

    fwrite(STDERR, "READY\n");
    usleep(5000000);
    if ($mode === 'lost') {
        throw new Failure('interrupted', 'Sent write interrupted.', 130, [], true);
    }

    return new ApiResponse(['task' => ['id' => 123]]);
});
$result = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), ['--json', 'task:complete', '123']);
fwrite(STDOUT, $result->stdout);
fwrite(STDERR, $result->stderr);
exit($result->status);
