<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

interface ScopeAwareTransport extends ApiTransport
{
    public function beforeCommand(array $routes, bool $allowNetwork): array;

    public function checkConnection(): array;
}
