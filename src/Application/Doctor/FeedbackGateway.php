<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Doctor;

interface FeedbackGateway
{
    public function available(): bool;
    public function mocked(): bool;
    public function submit(array $publication, string $idempotencyKey): FeedbackReceipt;
    public function reconcile(string $idempotencyKey): ?FeedbackReceipt;
}
