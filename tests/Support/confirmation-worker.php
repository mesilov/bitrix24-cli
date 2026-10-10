<?php

declare(strict_types=1);

use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\FixturePortal;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$portal = new FixturePortal();
$api = new FakeApiTransport(static function ($method, int $version, array $params, $effect) use ($portal): \Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse {
    if ($effect === 'delete') {
        fwrite(STDERR, "WRITE\n");
    }

    return $portal->respond($method, $version, $params);
});
exit((new ApplicationFactory())->create(dirname(__DIR__, 2), $api)->run());
