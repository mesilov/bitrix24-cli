<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Connection;

use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Application\Connection\ScopeManifest;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\BuildVersion;
use Bitrix24\CLI\Infrastructure\Bitrix24\SdkApiTransport;

final readonly class ScopeValidator
{
    public const TTL = 86400;
    public const BACKOFF = 300;

    public function __construct(private ConnectionManager $connections, private ScopeStateStore $store, private ScopeManifest $manifest, private Clock $clock, private SdkApiTransport $transport, private B24ClientProvider $provider)
    {
    }

    public function check(bool $force = false, bool $allowNetwork = true): array
    {
        $webhook = $this->provider->resolvedWebhook();
        $metadata = $this->connectionMetadata();
        $key = $this->key($metadata, $webhook);
        try {
            $directory = $this->connections->directory();
            if (!$allowNetwork) {
                return $this->cached($this->store->read($directory)['connections'][$key] ?? null);
            }

            return $this->store->transaction($directory, function (array &$state) use ($key, $force): array {
                $entry = $state['connections'][$key] ?? null;
                $now = $this->clock->now()->getTimestamp();
                $cached = $this->cached($entry);
                if (!$force && ($cached['status'] === 'fresh' || ($entry !== null && $entry['status'] !== 'fresh' && strtotime($entry['nextCheckAt']) > $now))) {
                    return $cached;
                }

                $attempt = $this->timestamp($now);
                try {
                    $scopes = $this->transport->call('scope', 1, [])->result;
                    if (!ScopeStateStore::validScopes($scopes)) {
                        throw new Failure('scope-check-unavailable', 'The scope method did not return a list of permission codes.');
                    }

                    $scopes = array_values(array_unique($scopes));
                    sort($scopes);
                    $completed = $this->clock->now()->getTimestamp();
                    $entry = [
                        'grantedScopes' => $scopes, 'lastSuccessfulCheckAt' => $this->timestamp($completed),
                        'checkedWithAppVersion' => BuildVersion::version(), 'scopeManifestRevision' => $this->manifest->revision(),
                        'lastAttemptAt' => $attempt, 'nextCheckAt' => $this->timestamp($completed + self::TTL), 'status' => 'fresh',
                    ];
                } catch (Failure $failure) {
                    if ($failure->exitStatus === 130) {
                        throw $failure;
                    }

                    $entry ??= ['grantedScopes' => [], 'lastSuccessfulCheckAt' => null, 'checkedWithAppVersion' => BuildVersion::version(), 'scopeManifestRevision' => $this->manifest->revision()];
                    $entry = [...$entry, 'lastAttemptAt' => $attempt, 'nextCheckAt' => $this->timestamp($this->clock->now()->getTimestamp() + self::BACKOFF), 'status' => $entry['lastSuccessfulCheckAt'] === null ? 'unknown' : 'stale'];
                }

                $state['connections'][$key] = $entry;
                return $this->cached($entry);
            });
        } catch (Failure $failure) {
            if ($failure->exitStatus === 130) {
                throw $failure;
            }

            return ['status' => 'unknown', 'grantedScopes' => [], 'lastSuccessfulCheckAt' => null, 'scopeManifestRevision' => $this->manifest->revision(), 'reason' => 'Scope state is unavailable; check state.json storage.'];
        }
    }

    public function invalidate(): void
    {
        $key = $this->key($this->connectionMetadata(), $this->provider->resolvedWebhook());
        try {
            $this->store->transaction($this->connections->directory(), function (array &$state) use ($key): array {
                if (isset($state['connections'][$key])) {
                    $entry = &$state['connections'][$key];
                    $entry['status'] = $entry['lastSuccessfulCheckAt'] === null ? 'unknown' : 'stale';
                    $entry['nextCheckAt'] = $this->timestamp(max($this->clock->now()->getTimestamp(), strtotime($entry['lastAttemptAt']) + self::BACKOFF));
                }

                return [];
            });
        } catch (Failure) {
            // Keep the original API error; invalidation must never trigger a write retry.
        }
    }

    private function key(array $metadata, string $webhook): string
    {
        return hash('sha256', json_encode([$metadata['source'], $metadata['profile'] ?? null, $metadata['path'] ?? null, $metadata['variable'] ?? null, rtrim($webhook, '/')], JSON_THROW_ON_ERROR));
    }

    public function connectionMetadata(): array
    {
        $metadata = $this->connections->metadata();
        return $metadata['source'] === 'none' ? ['source' => 'resolver', 'hostname' => FileCredentialStore::hostname($this->provider->resolvedWebhook())] : $metadata;
    }

    private function cached(?array $entry): array
    {
        $now = $this->clock->now()->getTimestamp();
        $checked = $entry === null || $entry['lastSuccessfulCheckAt'] === null ? null : strtotime($entry['lastSuccessfulCheckAt']);
        $fresh = $entry !== null && $entry['status'] === 'fresh' && $entry['scopeManifestRevision'] === $this->manifest->revision() && $checked !== null && $checked <= $now && $now - $checked < self::TTL;
        return [
            ...($entry ?? ['grantedScopes' => [], 'lastSuccessfulCheckAt' => null]),
            'status' => $fresh ? 'fresh' : ($checked === null ? 'unknown' : 'stale'),
            'currentScopeManifestRevision' => $this->manifest->revision(),
            'reason' => $fresh ? null : 'Webhook scope check is unknown or stale; use connection:check to refresh it.',
        ];
    }

    private function timestamp(int $timestamp): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }
}
