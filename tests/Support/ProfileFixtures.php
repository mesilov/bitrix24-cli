<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

use Bitrix24\CLI\Infrastructure\Connection\ProfileRepository;

/**
 * Seeds a saved profile with defaults through the public storage API, without selecting it by default.
 */
final class ProfileFixtures
{
    public static function add(ProfileRepository $repository, string $directory, string $alias, string $webhook, ?string $name = null, array $task = [], array $users = []): array
    {
        $default = $repository->load($directory)['defaultProfile'];
        $repository->login($directory, $alias, $webhook);
        return $repository->transaction($directory, static function (array &$config) use ($alias, $default, $name, $task, $users): array {
            $config['defaultProfile'] = $default;
            if ($name !== null) {
                $config['profiles'][$alias]['name'] = $name;
            }

            if ($task !== []) {
                $config['profiles'][$alias]['default']['task'] = $task;
            }

            if ($users !== []) {
                $config['profiles'][$alias]['default']['users'] = $users;
            }

            return $config['profiles'][$alias];
        });
    }
}
