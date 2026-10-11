<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Connection;

use Bitrix24\CLI\Application\Failure;

final readonly class ProfileRepository
{
    public function __construct(private FileProtection $protection, private CredentialStore $credentials)
    {
    }

    public static function domain(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= 253 && preg_match('/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/D', $value) === 1;
    }

    public static function alias(mixed $value): void
    {
        if (!is_string($value) || preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/D', $value) !== 1) {
            throw Failure::usage('Profile alias must start with a letter and contain only letters, digits, underscore or hyphen.');
        }
    }

    public function load(string $directory): array
    {
        $path = $directory . '/config.json';
        $this->protection->noLinks($path);
        if (!file_exists($path)) {
            return ['version' => 1, 'defaultProfile' => null, 'profiles' => []];
        }

        $this->protection->check($directory);
        $this->protection->check($path);
        try {
            $raw = @file_get_contents($path);
            $object = json_decode($raw === false ? '' : $raw, false, 64, JSON_THROW_ON_ERROR);
            if (!$object instanceof \stdClass || !($object->profiles ?? null) instanceof \stdClass) {
                throw new \RuntimeException();
            }

            foreach (get_object_vars($object->profiles) as $profile) {
                if (!$profile instanceof \stdClass || !($profile->connection ?? null) instanceof \stdClass) {
                    throw new \RuntimeException();
                }

                if (property_exists($profile, 'default')) {
                    if (!$profile->default instanceof \stdClass || (property_exists($profile->default, 'task') && !$profile->default->task instanceof \stdClass)) {
                        throw new \RuntimeException();
                    }
                }
            }

            $config = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            $this->validate($config);
            return $config;
        } catch (\Throwable) {
            throw new Failure('configuration-error', 'Invalid config.json schema version 1; check profile name, connection and default.task fields (groupId belongs in default.task.groupId).');
        }
    }

    public function profile(string $directory, string $alias): array
    {
        self::alias($alias);
        $profile = $this->load($directory)['profiles'][$alias] ?? null;
        if ($profile === null) {
            throw new Failure('configuration-error', 'The selected saved profile does not exist; use connection:list or connection:add.');
        }

        return $profile;
    }

    public function add(string $directory, string $alias, string $webhook, ?string $name, array $task): array
    {
        self::alias($alias);
        $hostname = FileCredentialStore::hostname($webhook);
        $name ??= $hostname;
        if (!self::domain($name)) {
            throw Failure::usage('--portal-domain must be a bare domain.');
        }

        return $this->locked($directory, function () use ($directory, $alias, $webhook, $hostname, $name, $task): array {
            $config = $this->load($directory);
            if (isset($config['profiles'][$alias])) {
                throw new Failure('configuration-error', 'Profile already exists; remove it explicitly before adding a replacement.');
            }

            $reference = $this->credentials->write($directory, $webhook);
            $profile = ['name' => $name, 'connection' => ['hostname' => $hostname, 'credentialFile' => $reference], 'default' => ['task' => $task]];
            $config['profiles'][$alias] = $profile;
            try {
                $this->publish($directory, $config);
            } catch (\Throwable $error) {
                $this->credentials->delete($directory, $reference);
                throw $error;
            }

            return $profile;
        });
    }

    public function use(string $directory, string $alias): array
    {
        return $this->locked($directory, function () use ($directory, $alias): array {
            $profile = $this->profile($directory, $alias);
            $config = $this->load($directory);
            $config['defaultProfile'] = $alias;
            $this->publish($directory, $config);
            return $profile;
        });
    }

    public function remove(string $directory, string $alias): array
    {
        return $this->locked($directory, function () use ($directory, $alias): array {
            $profile = $this->profile($directory, $alias);
            $config = $this->load($directory);
            unset($config['profiles'][$alias]);
            if ($config['defaultProfile'] === $alias) {
                $config['defaultProfile'] = null;
            }

            $this->publish($directory, $config);
            $this->credentials->delete($directory, $profile['connection']['credentialFile']);
            return $profile;
        });
    }

    private function validate(array $config): void
    {
        if (array_diff(array_keys($config), ['version', 'defaultProfile', 'profiles']) !== [] || ($config['version'] ?? null) !== 1 || !array_key_exists('defaultProfile', $config) || !is_array($config['profiles'] ?? null)) {
            throw new \RuntimeException();
        }

        $references = [];
        foreach ($config['profiles'] as $alias => $profile) {
            self::alias($alias);
            if (!is_array($profile) || array_diff(array_keys($profile), ['name', 'connection', 'default']) !== [] || !self::domain($profile['name'] ?? null)) {
                throw new \RuntimeException();
            }

            $connection = $profile['connection'] ?? null;
            if (!is_array($connection) || array_diff(array_keys($connection), ['hostname', 'credentialFile']) !== [] || !self::domain($connection['hostname'] ?? null) || !is_string($connection['credentialFile'] ?? null) || preg_match('~^credentials/[a-f0-9]{8,64}\.env$~D', $connection['credentialFile']) !== 1) {
                throw new \RuntimeException();
            }

            if (in_array($connection['credentialFile'], $references, true)) {
                throw new \RuntimeException();
            }

            $references[] = $connection['credentialFile'];
            if (array_key_exists('default', $profile)) {
                $default = $profile['default'];
                if (!is_array($default) || array_diff(array_keys($default), ['task']) !== []) {
                    throw new \RuntimeException();
                }

                if (array_key_exists('task', $default)) {
                    $task = $default['task'];
                    if (!is_array($task) || array_diff(array_keys($task), ['groupId', 'creatorId', 'responsibleId', 'allowsTimeTracking']) !== []) {
                        throw new \RuntimeException();
                    }

                    foreach ($task as $field => $value) {
                        if ($field === 'allowsTimeTracking' ? !is_bool($value) : (!is_int($value) || $value < 1)) {
                            throw new \RuntimeException();
                        }
                    }
                }
            }
        }

        if ($config['defaultProfile'] !== null && (!is_string($config['defaultProfile']) || !isset($config['profiles'][$config['defaultProfile']]))) {
            throw new \RuntimeException();
        }
    }

    private function locked(string $directory, \Closure $operation): array
    {
        $this->protection->directory($directory);
        $path = $directory . '/.lock';
        if (file_exists($path)) {
            $this->protection->check($path);
            $stream = @fopen($path, 'r+b');
        } else {
            try {
                $stream = $this->protection->create($path);
            } catch (Failure) {
                // Another writer may have won exclusive creation.
                $this->protection->check($path);
                $stream = @fopen($path, 'r+b');
            }
        }

        if ($stream === false || !flock($stream, LOCK_EX)) {
            throw new Failure('configuration-error', 'Cannot lock configuration storage.');
        }

        try {
            return $operation();
        } finally {
            flock($stream, LOCK_UN);
            fclose($stream);
        }
    }

    private function publish(string $directory, array $config): void
    {
        $this->validate($config);
        foreach ($config['profiles'] as &$profile) {
            if (isset($profile['default']['task'])) {
                $profile['default']['task'] = (object) $profile['default']['task'];
            }

            if (isset($profile['default'])) {
                $profile['default'] = (object) $profile['default'];
            }
        }

        unset($profile);
        $config['profiles'] = (object) $config['profiles'];
        $data = json_encode($config, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        $temporary = $directory . '/.config-' . bin2hex(random_bytes(12));
        $stream = $this->protection->create($temporary);
        try {
            if (fwrite($stream, $data) !== strlen($data) || !fflush($stream) || (function_exists('fsync') && !fsync($stream))) {
                throw new \RuntimeException();
            }

            fclose($stream);
            $stream = null;
            $this->protection->noLinks($directory . '/config.json');
            if (!@rename($temporary, $directory . '/config.json')) {
                throw new \RuntimeException();
            }
        } catch (\Throwable) {
            throw new Failure('configuration-error', 'Cannot publish configuration; the previous configuration is retained.');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (file_exists($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
