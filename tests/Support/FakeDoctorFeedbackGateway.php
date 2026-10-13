<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

use Bitrix24\CLI\Application\Doctor\FeedbackGateway;
use Bitrix24\CLI\Application\Doctor\FeedbackReceipt;
use Bitrix24\CLI\Application\Failure;

final class FakeDoctorFeedbackGateway implements FeedbackGateway
{
    public int $submissions = 0;
    public array $receipts = [];
    public array $outcomes = [];

    public function available(): bool
    {
        return true;
    }

    public function mocked(): bool
    {
        return false;
    }

    public function submit(array $publication, string $idempotencyKey): FeedbackReceipt
    {
        $outcome = $this->outcomes[$this->submissions++] ?? 'accepted';
        if ($outcome === 'rejected') {
            throw new Failure('feedback-error', 'PrivateFixtureTokenNeverPublic', 1);
        }

        $receipt = $this->receipts[$idempotencyKey] ?? new FeedbackReceipt('test-receipt-' . substr($idempotencyKey, 0, 12), false, 'PrivateFixtureTokenNeverPublic');
        $this->receipts[$idempotencyKey] = $receipt;
        if ($outcome === 'lost') {
            throw new Failure('feedback-error', 'PrivateFixtureTokenNeverPublic', 1, [], true);
        }

        return $receipt;
    }

    public function reconcile(string $idempotencyKey): ?FeedbackReceipt
    {
        return $this->receipts[$idempotencyKey] ?? null;
    }
}
