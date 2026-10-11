<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiTransport;

final class FakeApiTransport implements ApiTransport
{
    public array $calls = [];

    public function __construct(private readonly \Closure $respond)
    {
    }

    public function call(string $method, int $version, array $parameters, string $effect = 'read'): ApiResponse
    {
        $this->calls[] = ['method' => $method, 'version' => $version, 'parameters' => $parameters, 'effect' => $effect];
        return ($this->respond)($method, $version, $parameters, $effect);
    }
}
