<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Connection;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiTransport;

/**
 * Identifies the user of the selected incoming webhook through REST 1.0 profile, which needs no dedicated scope.
 */
final readonly class WebhookOwnerReader
{
    /**
     * Owner fields keep their REST 1.0 names.
     *
     * @return array{ID: mixed, NAME: mixed, LAST_NAME: mixed, ADMIN: mixed}
     */
    public function read(ApiTransport $transport): array
    {
        $profile = $transport->call('profile', 1, [])->result;
        if ($profile === []) {
            // An inactive user gets an empty list instead of the user object.
            throw new Failure('connection-unavailable', 'The webhook user is inactive: profile returned no user. Activate the user or create the incoming webhook under an active user.');
        }

        if (!isset($profile['ID'])) {
            throw new Failure('api-error', 'The profile method did not return the webhook user.');
        }

        return ['ID' => $profile['ID'], 'NAME' => $profile['NAME'] ?? null, 'LAST_NAME' => $profile['LAST_NAME'] ?? null, 'ADMIN' => $profile['ADMIN'] ?? null];
    }
}
