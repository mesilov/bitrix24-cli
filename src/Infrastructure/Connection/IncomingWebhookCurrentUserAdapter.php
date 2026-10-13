<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Connection;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\Port\CurrentUserGateway;

final readonly class IncomingWebhookCurrentUserAdapter implements CurrentUserGateway
{
    public function __construct(private ConnectionResolver $resolver)
    {
    }

    public function id(): int
    {
        $webhook = $this->resolver->webhook();
        FileCredentialStore::hostname($webhook);
        $segments = explode('/', (string) parse_url($webhook, PHP_URL_PATH));
        $id = filter_var($segments[2], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new Failure('configuration-error', 'The selected webhook user ID must be a positive integer within the supported range.');
        }

        return $id;
    }
}
