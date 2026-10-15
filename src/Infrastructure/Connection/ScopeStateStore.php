<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Connection;

use Bitrix24\CLI\Application\Failure;

final readonly class ScopeStateStore
{
    private const MAX_BYTES = 2097152;

    public function __construct(private FileProtection $protection)
    {
    }

    public function read(string $directory): array
    {
        $path = $directory . '/state.json';
        $this->protection->noLinks($path);
        if (!file_exists($path)) {
            return ['version' => 1, 'connections' => []];
        }

        $this->protection->check($path);
        if (!is_file($path) || filesize($path) > self::MAX_BYTES) {
            throw $this->invalid();
        }

        $raw = @file_get_contents($path, false, null, 0, self::MAX_BYTES + 1);
        try {
            $object = is_string($raw) ? json_decode($raw, false, 32, JSON_THROW_ON_ERROR) : null;
            if (!$object instanceof \stdClass || !($object->connections ?? null) instanceof \stdClass) {
                throw $this->invalid();
            }

            foreach (get_object_vars($object->connections) as $entry) {
                if (!$entry instanceof \stdClass || !is_array($entry->grantedScopes ?? null)) {
                    throw $this->invalid();
                }
            }

            $state = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            $this->validate($state);
            return $state;
        } catch (\Throwable) {
            throw $this->invalid();
        }
    }

    /** The callback receives the latest state by reference under an independent lock. */
    public function transaction(string $directory, \Closure $operation): array
    {
        $this->protection->directory($directory);
        $path = $directory . '/.state.lock';
        $this->protection->noLinks($path);
        if (file_exists($path)) {
            $this->protection->check($path);
            $stream = @fopen($path, 'r+b');
        } else {
            try {
                $stream = $this->protection->create($path);
            } catch (Failure) {
                $this->protection->check($path);
                $stream = @fopen($path, 'r+b');
            }
        }

        if ($stream === false) {
            throw $this->invalid();
        }

        try {
            if (!flock($stream, LOCK_EX)) {
                throw $this->invalid();
            }

            $state = $this->read($directory);
            $original = $state;
            $result = $operation($state);
            if ($state !== $original) {
                $this->publish($directory, $state);
            }

            return $result;
        } finally {
            flock($stream, LOCK_UN);
            fclose($stream);
        }
    }

    private function publish(string $directory, array $state): void
    {
        $this->validate($state);
        $state['connections'] = (object) $state['connections'];
        $raw = json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        if (strlen($raw) > self::MAX_BYTES) {
            throw $this->invalid();
        }

        $temporary = $directory . '/.state-' . bin2hex(random_bytes(12));
        $stream = $this->protection->create($temporary);
        try {
            if (fwrite($stream, $raw) !== strlen($raw) || !fflush($stream) || (function_exists('fsync') && !fsync($stream))) {
                throw $this->invalid();
            }

            fclose($stream);
            $stream = null;
            $this->protection->noLinks($directory . '/state.json');
            if (!@rename($temporary, $directory . '/state.json')) {
                throw $this->invalid();
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (file_exists($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function validate(array $state): void
    {
        if (array_diff(array_keys($state), ['version', 'connections']) !== [] || ($state['version'] ?? null) !== 1 || !is_array($state['connections'] ?? null)) {
            throw $this->invalid();
        }

        foreach ($state['connections'] as $key => $entry) {
            $fields = ['grantedScopes', 'lastSuccessfulCheckAt', 'checkedWithAppVersion', 'scopeManifestRevision', 'lastAttemptAt', 'nextCheckAt', 'status'];
            if (!is_string($key) || preg_match('/^[a-f0-9]{64}$/D', $key) !== 1 || !is_array($entry) || array_diff(array_keys($entry), $fields) !== [] || array_diff($fields, array_keys($entry)) !== []) {
                throw $this->invalid();
            }

            if (!self::validScopes($entry['grantedScopes']) || !is_string($entry['checkedWithAppVersion']) || !is_string($entry['scopeManifestRevision']) || preg_match('/^[a-f0-9]{64}$/D', $entry['scopeManifestRevision']) !== 1 || !in_array($entry['status'], ['fresh', 'stale', 'unknown'], true)) {
                throw $this->invalid();
            }

            foreach (['lastSuccessfulCheckAt', 'lastAttemptAt', 'nextCheckAt'] as $field) {
                if ($entry[$field] === null && $field === 'lastSuccessfulCheckAt') {
                    continue;
                }

                $value = $entry[$field];
                $date = is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new \DateTimeZone('UTC')) : false;
                if ($date === false || $date->format('Y-m-d\TH:i:s\Z') !== $value) {
                    throw $this->invalid();
                }
            }
        }
    }

    public static function validScopes(mixed $scopes): bool
    {
        return is_array($scopes) && array_is_list($scopes) && array_all($scopes, static fn ($scope): bool => is_string($scope) && preg_match('/^[a-z][a-z0-9_.:-]*$/D', $scope) === 1);
    }

    private function invalid(): Failure
    {
        return new Failure('scope-state-unavailable', 'Cannot use state.json; check its format, private permissions and available storage. Configuration and credentials are retained.');
    }
}
