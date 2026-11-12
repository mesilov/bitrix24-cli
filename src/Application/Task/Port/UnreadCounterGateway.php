<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

use Bitrix24\CLI\Application\Task\UnreadCounterSnapshot;

interface UnreadCounterGateway
{
    /**
     * Reads one snapshot of the connection user's unread counters. Transport and API errors are thrown;
     * an unsupported response shape is reported inside the snapshot, never as an empty valid state.
     */
    public function snapshot(): UnreadCounterSnapshot;
}
