<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Task\Port\ParticipantGateway;

final readonly class Rest1ParticipantAdapter implements ParticipantGateway
{
    public function __construct(private ApiTransport $api)
    {
    }

    public function set(int $taskId, ?array $accomplices, ?array $auditors): void
    {
        $fields = [];
        if ($accomplices !== null) {
            $fields['ACCOMPLICES'] = $accomplices;
        }

        if ($auditors !== null) {
            $fields['AUDITORS'] = $auditors;
        }

        ResponseNormalizer::ack($this->api->call('tasks.task.update', 1, ['taskId' => $taskId, 'fields' => $fields], 'write')->result);
    }
}
