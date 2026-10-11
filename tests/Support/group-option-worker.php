<?php

declare(strict_types=1);

use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\FixturePortal;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$portal = new FixturePortal();
$api = new FakeApiTransport(static function (string $method, int $version, array $params) use ($portal): \Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse {
    fwrite(STDERR, "API_CALL\n");
    return $portal->respond($method, $version, $params);
});
exit((new ApplicationFactory())->create(dirname(__DIR__, 2), $api)->run());
