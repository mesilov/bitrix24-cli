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
        public array $order = ['id' => 'DESC'],
        public ?int $limit = 50,
        public int $maxScan = 10000,
        public ?TaskRole $role = null,
        public ?DeadlineWindow $deadlineWindow = null,
        public bool $openOnly = false,
        public bool $verifyExhaustionAtBudget = false,
        public ?string $apiPolicy = null,
        /** @var list<DateWindow> */
        public array $dateWindows = [],
        /** task:list --unread: join one im.counters.get snapshot with the scanned tasks (#148). */
        public bool $unread = false,
    ) {
    }

    /**
     * Temporal flags (deadline, overdue, creation and change windows) select the REST 1.0 date route.
     */
    public function temporal(): bool
    {
        return $this->deadlineWindow instanceof DeadlineWindow || $this->dateWindows !== [];
    }
}
