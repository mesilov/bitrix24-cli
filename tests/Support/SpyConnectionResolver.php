<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

use Bitrix24\CLI\Infrastructure\Connection\ConnectionResolver;

final class SpyConnectionResolver implements ConnectionResolver
{
    public int $reads = 0;

    public function webhook(): string
    {
        $this->reads++;
        return 'https://portal.invalid/rest/1/secret-fixture/';
    }
}
