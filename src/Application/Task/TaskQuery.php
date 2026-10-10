<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

final readonly class TaskQuery
{
    public function __construct(
        public array $ids = [],
        public array $where = [],
        public ?string $dueBefore = null,
        public array $select = [],
        public array $order = [],
        public ?int $limit = 50,
        public int $maxScan = 10000,
    ) {
    }
}
