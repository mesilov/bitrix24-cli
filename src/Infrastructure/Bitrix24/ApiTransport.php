<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

interface ApiTransport
{
    public function call(string $method, int $version, array $parameters, string $effect = 'read'): ApiResponse;
}
