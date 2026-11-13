<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Connection;

use Bitrix24\CLI\Application\Failure;

final class ConnectionManager implements ConnectionResolver
{
    private ?string $profile = null;
    private ?string $file = null;
    private ?string $configDirectory = null;
    private ?array $selection = null;
    private ?string $candidate = null;
    public array $fieldSources = [];

    public function __construct(private readonly ConfigDirectoryResolver $directories, private readonly ProfileRepository $profiles, private readonly CredentialStore $credentials, private readonly string $projectRoot, private readonly bool $packaged = false)
    {
    }

    public function configure(?string $profile, ?string $file, ?string $directory): void
    {
        if ($profile !== null && $file !== null) {
            throw Failure::usage('Choose --profile or --env-file, including the runtime config profile.');
        }

        if ($profile !== null) {
            ProfileRepository::alias($profile);
        }

        if ($directory === '') {
            throw Failure::usage('--config-dir requires a nonempty path.');
        }

        if ($file === '') {
            throw Failure::usage('--env-file requires a nonempty path.');
        }

        $this->profile = $profile;
        $this->file = $file;
        $this->configDirectory = $directory;
        $this->selection = null;
        $this->candidate = null;
        $this->fieldSources = [];
    }

    /**
     * Selects a webhook that is not saved yet as profile $alias, so its verification
     * and scope snapshot already carry the identity of the future profile.
     */
    public function candidate(string $alias, string $webhook): void
    {
        ProfileRepository::alias($alias);
        $hostname = FileCredentialStore::hostname($webhook);
        $this->candidate = $webhook;
        $this->selection = ['source' => 'profile', 'profile' => $alias, 'path' => $this->directory() . '/config.json', 'metadata' => ['name' => $hostname, 'connection' => ['hostname' => $hostname]]];
    }

    /**
     * Saved profile addressed by the --profile option, B24CLI_PROFILE or defaultProfile.
     * Connection selectors that are not this chain are ignored: environment webhooks, dotenv files
     * and the profile of a runtime --config, so shared runtime settings cannot redirect a settings write.
     */
    public function profileAlias(?string $option): ?string
    {
        if ($option !== null) {
            ProfileRepository::alias($option);
            return $option;
        }

        $profile = $this->environment('B24CLI_PROFILE');
        if ($profile !== '') {
            ProfileRepository::alias($profile);
            return $profile;
        }

        return $this->profiles->load($this->directory())['defaultProfile'];
    }

    public function directory(): string
    {
        return $this->directories->resolve($this->configDirectory);
    }

    public function selected(bool $offline = false): array
    {
        if ($this->selection !== null) {
            return $this->selection;
        }

        if ($this->profile !== null) {
            return $this->selection = $this->saved($this->profile);
        }

        if ($this->file !== null) {
            FileCredentialStore::assertEnvironmentPath($this->file);
            return $this->selection = ['source' => 'explicit-file', 'path' => $this->file];
        }

        $file = $this->environment('B24CLI_ENV_FILE');
        $profile = $this->environment('B24CLI_PROFILE');
        if ($file !== '' && $profile !== '') {
            throw new Failure('configuration-error', 'B24CLI_ENV_FILE and B24CLI_PROFILE conflict; choose one or provide an explicit selector.');
        }

        foreach (['BITRIX24_WEBHOOK', 'BITRIX24_PHP_SDK_PLAYGROUND_WEBHOOK'] as $key) {
            if ($this->environment($key) !== '') {
                return $this->selection = ['source' => 'process-env', 'variable' => $key];
            }
        }

        if ($file !== '') {
            FileCredentialStore::assertEnvironmentPath($file);
            return $this->selection = ['source' => 'environment-file', 'path' => $file];
        }

        if ($profile !== '') {
            return $this->selection = $this->saved($profile);
        }

        // Missing OS home must not prevent credential-free offline planning.
        try {
            $directory = $this->directory();
        } catch (Failure $error) {
            if (!$offline || $this->configDirectory !== null || $this->environment('B24CLI_CONFIG_DIR') !== '') {
                throw $error;
            }

            return ['source' => 'none'];
        }

        $default = $this->profiles->load($directory)['defaultProfile'];
        if ($default !== null) {
            return $this->selection = $this->saved($default);
        }

        if (!$this->packaged) {
            foreach (['.env.local', '.env'] as $name) {
                if (is_file($this->projectRoot . '/' . $name)) {
                    return $this->selection = ['source' => 'source-dotenv', 'path' => $this->projectRoot . '/' . $name];
                }
            }
        }

        return $this->selection = ['source' => 'none'];
    }

    public function defaults(bool $offline): array
    {
        return $this->selected($offline)['metadata']['default']['task'] ?? [];
    }

    public function userDefaults(bool $offline = false): array
    {
        return $this->selected($offline)['metadata']['default']['users'] ?? [];
    }

    public function metadata(bool $offline = false): array
    {
        $selection = $this->selected($offline);
        $profile = $selection['metadata'] ?? [];
        unset($selection['metadata']);
        if ($profile !== []) {
            $selection = [...$selection, ...$profile];
            $selection['default']['task']['allowsTimeTracking'] ??= false;
            $selection['default']['users']['autoAddObserver'] ??= false;
        }

        return $selection;
    }

    /**
     * Effective connection of this invocation for local inspection: its source, the relevant
     * profile, path or variable, and the hostname. A saved profile is described by its metadata only.
     */
    public function effective(): array
    {
        $selection = $this->selected();
        $hostname = $selection['metadata']['connection']['hostname'] ?? null;
        unset($selection['metadata']);
        if ($selection['source'] !== 'none' && $hostname === null) {
            $hostname = FileCredentialStore::hostname($this->webhook());
        }

        return [...$selection, 'hostname' => $hostname];
    }

    public function webhook(): string
    {
        if ($this->candidate !== null) {
            return $this->candidate;
        }

        $selection = $this->selected();
        if ($selection['source'] === 'profile') {
            $connection = $selection['metadata']['connection'];
            $webhook = $this->credentials->read($this->directory(), $connection['credentialFile']);
            if (FileCredentialStore::hostname($webhook) !== strtolower($connection['hostname'])) {
                throw new Failure('configuration-error', 'The saved webhook hostname differs from connection.hostname; repair the selected profile.');
            }

            return $webhook;
        }

        if ($selection['source'] === 'process-env') {
            $webhook = $this->environment($selection['variable']);
            FileCredentialStore::hostname($webhook);
            return $webhook;
        }

        if (isset($selection['path'])) {
            return FileCredentialStore::environmentFile($selection['path']);
        }

        throw new Failure('connection-unavailable', 'No connection is configured. Save a webhook with auth:login NAME, select a saved profile with auth:switch NAME or --profile, supply --env-file, or set BITRIX24_WEBHOOK.');
    }

    private function saved(string $alias): array
    {
        $directory = $this->directory();
        return ['source' => 'profile', 'profile' => $alias, 'path' => $directory . '/config.json', 'metadata' => $this->profiles->profile($directory, $alias)];
    }

    private function environment(string $name): string
    {
        $value = getenv($name);
        return $value === false ? (string) ($_ENV[$name] ?? '') : $value;
    }
}
