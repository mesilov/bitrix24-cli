<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Connection\FileCredentialStore;
use Bitrix24\CLI\Infrastructure\Connection\FileProtection;
use Bitrix24\CLI\Infrastructure\Connection\ProfileRepository;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\ProblemTrajectoryFixtures;

[$script, $directory, $mode, $key] = $argv;
if ($mode === 'profile') {
    $protection = new FileProtection();
    (new ProfileRepository($protection, new FileCredentialStore($protection)))->add($directory, 'fixture', 'https://fixture.invalid/rest/1/ConcurrentFixtureSecret/', null, []);
    exit(0);
}

$app = (new ApplicationFactory())->create(dirname(__DIR__, 2), packaged: true);
$run = ConsoleHarness::run($app, ['--config-dir', $directory, '--json', 'doctor:problem-trajectory', '--report-file', '-', '--idempotency-key', $key], json_encode(ProblemTrajectoryFixtures::report(), JSON_THROW_ON_ERROR));
echo $run->stdout;
exit($run->status);
