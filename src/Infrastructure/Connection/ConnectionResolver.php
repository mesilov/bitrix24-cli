<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Connection;

interface ConnectionResolver
{
    public function webhook(): string;
}
