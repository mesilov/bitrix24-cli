<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Connection\ScopeManifest;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Infrastructure\Connection\ScopeValidator;

final readonly class ScopeCheckingTransport implements ScopeAwareTransport
{
    public function __construct(private SdkApiTransport $transport, private ScopeValidator $validator, private ScopeManifest $manifest)
    {
    }

    public function beforeCommand(array $routes, bool $allowNetwork): array
    {
        $required = $this->manifest->required($routes);
        if ($required === []) {
            return [];
        }

        $report = $this->validator->check(allowNetwork: $allowNetwork);
        $missing = $report['status'] === 'fresh' ? $this->manifest->missing($required, $report['grantedScopes']) : [];
        $report = [...$report, 'requiredScopeGroups' => $required, 'missingScopes' => array_column($missing, 0), 'connection' => $this->validator->connectionMetadata()];
        if ($missing !== []) {
            throw $this->missing($report);
        }

        return $report;
    }

    public function checkConnection(): array
    {
        $report = $this->validator->check(force: true);
        $report['connection'] = $this->validator->connectionMetadata();
        if ($report['status'] !== 'fresh') {
            throw new Failure('scope-check-unavailable', 'Could not verify webhook permissions. The last successful snapshot is retained; retry connection:check.', 1, ['scopeValidation' => $report]);
        }

        $report['potentiallyAffectedCommands'] = $this->manifest->affectedCommands($report['grantedScopes']);
        $report['missingScopes'] = array_values(array_unique(array_merge([], ...array_values($report['potentiallyAffectedCommands']))));
        $report['conditionalRequirements'] = 'Affected commands may depend on selected options; only the actual route plan is blocked.';
        if ($report['missingScopes'] !== []) {
            throw $this->missing($report);
        }

        return $report;
    }

    public function call(string $method, int $version, array $parameters, string $effect = 'read'): ApiResponse
    {
        try {
            return $this->transport->call($method, $version, $parameters, $effect);
        } catch (Failure $failure) {
            if (($failure->details['apiErrorCode'] ?? null) === 'insufficient_scope') {
                $this->validator->invalidate();
            }

            throw $failure;
        }
    }

    private function missing(array $report): Failure
    {
        $connection = $report['connection'];
        $name = $connection['profile'] ?? $connection['source'];
        return new Failure('scope-missing', 'Connection ' . $name . ' is missing webhook scopes: ' . implode(', ', $report['missingScopes']) . '. Checked at ' . $report['lastSuccessfulCheckAt'] . '. Add these permissions to the incoming webhook, then run connection:check with the same connection selectors.', 4, ['scopeValidation' => $report]);
    }
}
