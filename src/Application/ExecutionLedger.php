<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application;

final class ExecutionLedger
{
    private array $confirmed = [];
    private array $steps = [];

    public function confirm(array $operation): void
    {
        $this->confirmed[] = $operation;
        $this->steps[] = [...$operation, 'status' => 'confirmed'];
    }

    public function stop(int $failedId, array $remaining, Failure $failure): OperationResult
    {
        $this->steps[] = ['resourceId' => $failedId, 'status' => $failure->outcomeUnknown ? 'unknown' : 'failed'];
        foreach ($remaining as $id) {
            $this->steps[] = ['resourceId' => $id, 'status' => 'skipped'];
        }

        if ($this->confirmed === []) {
            throw new Failure($failure->errorCode, $failure->getMessage(), $failure->exitStatus, ['ledger' => $this->steps, ...$failure->details], $failure->outcomeUnknown);
        }

        return new OperationResult('mutation', ['items' => $this->confirmed], ['ledger' => $this->steps, 'outcomeUnknown' => $failure->outcomeUnknown, 'reason' => $failure->getMessage(), 'complete' => false], $failure->exitStatus === 130 ? 130 : 3);
    }

    public function result(): OperationResult
    {
        return new OperationResult('mutation', ['items' => $this->confirmed], ['ledger' => $this->steps, 'complete' => true]);
    }
}
