<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

use Bitrix24\CLI\Application\Task\Page;

interface TaskGateway
{
    public function add(array $fields): int;
    public function get(int $id, array $select = []): array;
    public function page(array $filter, array $select, array $order, int $offset, int $limit): Page;
    public function update(int $id, array $fields): void;
    public function delete(int $id): void;
    public function fields(?string $name = null): array;
    public function access(int $id): array;
    public function attach(int $id, int $fileId): void;
}
