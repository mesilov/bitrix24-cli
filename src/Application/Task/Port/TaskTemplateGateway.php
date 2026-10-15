<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

interface TaskTemplateGateway
{
    public function get(int $id): ?array;
    public function fields(): array;
    public function add(array $fields): int;
    public function update(int $id, array $fields): void;
    public function delete(int $id): void;
}
