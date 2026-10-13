<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\ExecutionLedger;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\Task\Port\TaskTagGateway;

final readonly class TaskTagMutation
{
    public function __construct(private TaskTagGateway $tags)
    {
    }

    public function finish(int $taskId, array $fields, array $TAGS, string $action, ?array $confirmed): OperationResult
    {
        $executionLedger = new ExecutionLedger();
        if ($confirmed !== null) {
            $executionLedger->confirm($confirmed);
        }

        try {
            $this->tags->set($taskId, $TAGS);
            $executionLedger->confirm(OperationResult::mutation($taskId, $taskId, 'tags:set', ['TAGS' => $TAGS])->data['operation']);
        } catch (Failure $failure) {
            $recovery = 'Inspect task ' . $taskId . ' with task:show ' . $taskId . ' --select tags.id --select tags.name; retry only task:update ' . $taskId . ' with --tag or --clear-tags after checking the result.';
            return $executionLedger->stop($taskId, [], new Failure($failure->errorCode, $failure->getMessage() . ' ' . $recovery, $failure->exitStatus, [...$failure->details, 'taskId' => $taskId], $failure->outcomeUnknown));
        }

        $result = OperationResult::mutation($taskId, $taskId, $action, [...$fields, 'TAGS' => $TAGS]);
        return new OperationResult($result->profile, $result->data, $executionLedger->result()->meta);
    }
}
