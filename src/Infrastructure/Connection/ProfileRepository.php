<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Connection;

use Bitrix24\CLI\Application\Doctor\TelemetrySettings;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Logging\LoggingSettings;

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

            if (property_exists($object, 'telemetry') && !$object->telemetry instanceof \stdClass) {
                throw new \RuntimeException();
            }

            foreach (get_object_vars($object->profiles) as $profile) {
                if (!$profile instanceof \stdClass || !($profile->connection ?? null) instanceof \stdClass) {
                    throw new \RuntimeException();
                }

                if (property_exists($profile, 'default')) {
                    if (!$profile->default instanceof \stdClass || (property_exists($profile->default, 'task') && !$profile->default->task instanceof \stdClass) || (property_exists($profile->default, 'users') && !$profile->default->users instanceof \stdClass)) {
                        throw new \RuntimeException();
                    }
                }
            }

            $config = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            if (property_exists($object, 'logging')) {
                // Logging is validated separately and kept as decoded JSON, so invalid values survive profile writes unchanged.
                $config['logging'] = $object->logging;
            }

            $this->validate($config);
            return $config;
        } catch (\Throwable) {
            throw new Failure('configuration-error', 'Invalid config.json schema version 1; check profile name, connection, default.task, default.users and root telemetry fields (groupId belongs in default.task.groupId).');
        }
    }

    public function profile(string $directory, string $alias): array
    {
        self::alias($alias);
        $profile = $this->load($directory)['profiles'][$alias] ?? null;
        if ($profile === null) {
            throw new Failure('configuration-error', 'The selected saved profile does not exist; inspect saved profiles with auth:list or create one with auth:login NAME.');
        }

        return $profile;
    }

    /**
     * Local precondition of auth:login, checked before the secret is requested or read.
     */
    public function assertCanLogin(string $directory, string $alias, bool $replace): void
    {
        self::alias($alias);
        $this->assertLoginTarget($this->load($directory), $alias, $replace);
    }

    private function assertLoginTarget(array $config, string $alias, bool $replace): void
    {
        $exists = isset($config['profiles'][$alias]);
        if ($exists && !$replace) {
            throw new Failure('configuration-error', sprintf('Profile "%s" already exists; nothing was changed. To replace its webhook and keep the settings, use auth:login %s --replace. To change one setting, use config:set KEY VALUE --profile %s.', $alias, $alias, $alias));
        }

        if (!$exists && $replace) {
            throw new Failure('configuration-error', sprintf('Profile "%s" does not exist, so --replace has nothing to replace. Create it with auth:login %s, or inspect saved profiles with auth:list.', $alias, $alias));
        }
    }

    /**
     * Saves a webhook as a new profile, or replaces only the secret of an existing one.
     * A new profile becomes the default only when no default profile is selected.
     *
     * @return array{profile: array, isDefault: bool, becameDefault: bool, previousHostname: ?string}
     */
    public function login(string $directory, string $alias, string $webhook, bool $replace = false): array
    {
        self::alias($alias);
        $hostname = FileCredentialStore::hostname($webhook);
        return $this->locked($directory, function () use ($directory, $alias, $webhook, $hostname, $replace): array {
            $config = $this->load($directory);
            $this->assertLoginTarget($config, $alias, $replace);
            $previous = $config['profiles'][$alias] ?? null;
            $reference = $this->credentials->write($directory, $webhook);
            $profile = $previous ?? ['name' => $hostname];
            $profile['connection'] = ['hostname' => $hostname, 'credentialFile' => $reference];
            $config['profiles'][$alias] = $profile;
            $becameDefault = $previous === null && $config['defaultProfile'] === null;
            if ($becameDefault) {
                $config['defaultProfile'] = $alias;
            }

            try {
                $this->publish($directory, $config);
            } catch (\Throwable $error) {
                $this->credentials->delete($directory, $reference);
                throw $error;
            }

            if ($previous !== null) {
                // The reference is already switched; the old secret is removed only after publication.
                $this->credentials->delete($directory, $previous['connection']['credentialFile']);
            }

            return ['profile' => $profile, 'isDefault' => $config['defaultProfile'] === $alias, 'becameDefault' => $becameDefault, 'previousHostname' => $previous['connection']['hostname'] ?? null];
        });
    }

    public function use(string $directory, string $alias): array
    {
        // A missing profile is reported before the storage lock creates anything.
        $this->profile($directory, $alias);
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
        $this->profile($directory, $alias);
        return $this->locked($directory, function () use ($directory, $alias): array {
            $profile = $this->profile($directory, $alias);
            $config = $this->load($directory);
            unset($config['profiles'][$alias]);
            $wasDefault = $config['defaultProfile'] === $alias;
            if ($wasDefault) {
                $config['defaultProfile'] = null;
            }

            $this->publish($directory, $config);
            $this->credentials->delete($directory, $profile['connection']['credentialFile']);
            return ['profile' => $profile, 'wasDefault' => $wasDefault];
        });
    }

    /**
     * Sets one documented setting; the whole document passes the same validation as on reading.
     *
     * @return array{key: string, value: mixed, source: string, profile: ?string}
     */
    public function set(string $directory, ?string $alias, string $key, string $value): array
    {
        return $this->change($directory, $alias, $key, ConfigKeys::parse($key, $value), false);
    }

    /**
     * Removes one setting, so its built-in default applies; emptied containers are removed with it.
     *
     * @return array{key: string, value: mixed, source: string, profile: ?string}
     */
    public function unset(string $directory, ?string $alias, string $key): array
    {
        ConfigKeys::assertKnown($key, true);
        if ($key === 'name') {
            throw Failure::usage('name is a required profile field and cannot be unset; change it with config:set name HOSTNAME.');
        }

        return $this->change($directory, $alias, $key, null, true);
    }

    private function change(string $directory, ?string $alias, string $key, mixed $value, bool $unset): array
    {
        $root = ConfigKeys::isRoot($key);
        if ($root) {
            $alias = null;
        } elseif ($alias === null) {
            throw Failure::usage('No profile is selected for the profile setting ' . $key . '. Pass --profile NAME, choose a default with auth:switch NAME, or create the first profile with auth:login NAME.');
        } else {
            // A missing profile is reported before the storage lock creates anything.
            $this->profile($directory, $alias);
        }

        return $this->locked($directory, function () use ($directory, $alias, $key, $value, $unset): array {
            $config = $this->load($directory);
            $before = json_encode($config, JSON_THROW_ON_ERROR);
            $parts = explode('.', $key);
            if ($parts[0] === 'logging') {
                $config = $this->changeLogging($config, $key, $parts[1], $value, $unset);
            } elseif ($parts[0] === 'telemetry') {
                $telemetry = $config['telemetry'] ?? [];
                unset($telemetry[$parts[1]]);
                if (!$unset) {
                    $telemetry[$parts[1]] = $value;
                }

                $config['telemetry'] = $telemetry;
                if ($telemetry === []) {
                    unset($config['telemetry']);
                }
            } else {
                $profile = $config['profiles'][$alias] ?? throw new Failure('configuration-error', 'The selected saved profile does not exist; inspect saved profiles with auth:list or create one with auth:login NAME.');
                if ($key === 'name') {
                    $profile['name'] = $value;
                } else {
                    $profile = $this->changeDefault($profile, $key, $parts[1], $parts[2], $value, $unset);
                }

                $config['profiles'][$alias] = $profile;
            }

            try {
                $this->validate($config);
            } catch (\Throwable) {
                throw Failure::usage('The value of ' . $key . ' does not pass config.json validation; nothing was changed.');
            }

            if (json_encode($config, JSON_THROW_ON_ERROR) !== $before) {
                $this->publish($directory, $config);
            }

            return [...ConfigKeys::read($config, $alias, $key), 'profile' => $alias];
        });
    }

    private function changeLogging(array $config, string $key, string $field, mixed $value, bool $unset): array
    {
        $saved = $config['logging'] ?? new \stdClass();
        if (!$saved instanceof \stdClass) {
            throw Failure::usage('Cannot change ' . $key . ': the saved logging value is not a JSON object. Repair logging in config.json first.');
        }

        // Invalid logging survives unrelated writes, but a logging.* change must leave the whole block valid.
        $logging = clone $saved;
        unset($logging->{$field});
        if (!$unset) {
            $logging->{$field} = $value;
        }

        $problem = LoggingSettings::problem($logging);
        if ($problem !== null) {
            throw Failure::usage('Cannot change ' . $key . ' while logging stays invalid: ' . $problem . ' Fix that setting with config:set or config:unset first.');
        }

        $config['logging'] = $logging;
        if (get_object_vars($logging) === []) {
            unset($config['logging']);
        }

        return $config;
    }

    private function changeDefault(array $profile, string $key, string $group, string $field, mixed $value, bool $unset): array
    {
        $values = $profile['default'][$group] ?? [];
        unset($values[$field]);
        if (!$unset) {
            $values[$field] = $value;
        }

        if ($group === 'users' && ($values['autoAddObserver'] ?? false) && !isset($values['observerId'])) {
            throw Failure::usage($unset
                ? 'default.users.observerId is required while default.users.autoAddObserver is true; set autoAddObserver to false first.'
                : $key . '=true requires default.users.observerId; set the observer ID first.');
        }

        $profile['default'][$group] = $values;
        if ($values === []) {
            unset($profile['default'][$group]);
        }

        if ($profile['default'] === []) {
            unset($profile['default']);
        }

        return $profile;
    }

    private function validate(array $config): void
    {
        if (array_diff(array_keys($config), ['version', 'defaultProfile', 'profiles', 'telemetry', 'logging']) !== [] || ($config['version'] ?? null) !== 1 || !array_key_exists('defaultProfile', $config) || !is_array($config['profiles'] ?? null)) {
            throw new \RuntimeException();
        }

        if (array_key_exists('telemetry', $config)) {
            if (!is_array($config['telemetry'])) {
                throw new \RuntimeException();
            }

            TelemetrySettings::validate($config['telemetry']);
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
                if (!is_array($default) || array_diff(array_keys($default), ['task', 'users']) !== []) {
                    throw new \RuntimeException();
                }

                if (array_key_exists('users', $default)) {
                    if (!is_array($default['users'])) {
                        throw new \RuntimeException();
                    }

                    $this->validateUsers($default['users']);
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

    private function validateUsers(array $users): void
    {
        if (array_diff(array_keys($users), ['observerId', 'managerId', 'autoAddObserver']) !== []) {
            throw new \RuntimeException('Unknown default.users key.');
        }

        foreach ($users as $field => $value) {
            if ($field === 'autoAddObserver' ? !is_bool($value) : (!is_int($value) || $value < 1)) {
                throw new \RuntimeException('Invalid default.users value.');
            }
        }

        if (($users['autoAddObserver'] ?? false) && !isset($users['observerId'])) {
            throw new \RuntimeException('autoAddObserver requires observerId.');
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

    /** The callback receives config by reference; afterCommit runs under the same lock. */
    public function transaction(string $directory, \Closure $operation, ?\Closure $afterCommit = null): array
    {
        return $this->locked($directory, function () use ($directory, $operation, $afterCommit): array {
            $config = $this->load($directory);
            $result = $operation($config);
            $this->publish($directory, $config);
            if ($afterCommit instanceof \Closure) {
                $afterCommit();
            }

            return $result;
        });
    }

    public function readTransaction(string $directory, \Closure $operation): array
    {
        $path = $directory . '/.lock';
        $this->protection->noLinks($path);
        if (!file_exists($path)) {
            return $operation($this->load($directory));
        }

        $this->protection->check($path);
        $stream = @fopen($path, 'rb');
        if ($stream === false || !flock($stream, LOCK_SH)) {
            throw new Failure('configuration-error', 'Cannot read configuration under its storage lock.');
        }

        try {
            return $operation($this->load($directory));
        } finally {
            flock($stream, LOCK_UN);
            fclose($stream);
        }
    }

    private function publish(string $directory, array $config): void
    {
        $this->validate($config);
        foreach ($config['profiles'] as &$profile) {
            if (isset($profile['default']['users'])) {
                $profile['default']['users'] = (object) $profile['default']['users'];
            }

            if (isset($profile['default']['task'])) {
                $profile['default']['task'] = (object) $profile['default']['task'];
            }

            if (isset($profile['default'])) {
                $profile['default'] = (object) $profile['default'];
            }
        }

        unset($profile);
        $config['profiles'] = (object) $config['profiles'];
        if (isset($config['telemetry'])) {
            $config['telemetry'] = (object) $config['telemetry'];
        }

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
