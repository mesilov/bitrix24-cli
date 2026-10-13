<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Doctor;

use Bitrix24\CLI\Application\Doctor\FeedbackGateway;
use Bitrix24\CLI\Application\Doctor\FeedbackReceipt;
use Bitrix24\CLI\Application\Failure;

final class UnavailableFeedbackGateway implements FeedbackGateway
{
    public function available(): bool
    {
        return false;
    }

    public function mocked(): bool
    {
        return false;
    }

    public function submit(array $publication, string $idempotencyKey): FeedbackReceipt
    {
        throw new Failure('endpoint-unavailable', 'Feedback endpoint is not available; local cards and analysis are retained. Use --mock-feedback only for an offline rehearsal.');
    }

    public function reconcile(string $idempotencyKey): ?FeedbackReceipt
    {
        throw new Failure('endpoint-unavailable', 'Feedback endpoint reconciliation is not available.');
    }
}
