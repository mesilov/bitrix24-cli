<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task\Port;

interface ParticipantGateway
{
    public function ids(int $taskId, array $roles): array;
    public function set(int $taskId, ?array $accomplices, ?array $auditors): void;
}
