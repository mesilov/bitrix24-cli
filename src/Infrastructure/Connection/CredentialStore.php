<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Connection;

interface CredentialStore
{
    public function read(string $directory, string $reference): string;
    public function write(string $directory, string $webhook): string;
    public function delete(string $directory, string $reference): void;
}
