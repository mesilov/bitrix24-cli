<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

interface ScopeAwareTransport extends ApiTransport
{
    public function beforeCommand(array $routes, bool $allowNetwork): array;

    /**
     * Forced scope diagnostic of the selected connection; the report carries the webhook owner when it is known.
     */
    public function checkConnection(?array $owner = null): array;

    /**
     * Reads the scopes of the selected webhook without persisting the snapshot.
     */
    public function probeScopes(): array;

    /**
     * Persists a probed snapshot and returns the diagnostic report without failing on missing scopes.
     */
    public function rememberScopes(array $entry): array;
}
