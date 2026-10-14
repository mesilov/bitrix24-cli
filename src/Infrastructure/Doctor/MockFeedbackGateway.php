<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Doctor;

use Bitrix24\CLI\Application\Doctor\FeedbackGateway;
use Bitrix24\CLI\Application\Doctor\FeedbackReceipt;

final class MockFeedbackGateway implements FeedbackGateway
{
    public function available(): bool
    {
        return true;
    }

    public function mocked(): bool
    {
        return true;
    }

    public function submit(array $publication, string $idempotencyKey): FeedbackReceipt
    {
        return new FeedbackReceipt('mock-' . hash('sha256', $idempotencyKey), true);
    }

    public function reconcile(string $idempotencyKey): FeedbackReceipt
    {
        return new FeedbackReceipt('mock-' . hash('sha256', $idempotencyKey), true);
    }
}
