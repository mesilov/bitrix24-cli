<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Doctor;

use Bitrix24\CLI\Application\Failure;

final readonly class FeedbackReceipt
{
    public function __construct(public string $receiptId, public bool $mocked, public ?string $accessSecret = null)
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $receiptId) !== 1 || ($accessSecret !== null && preg_match('/^[A-Za-z0-9_-]{8,512}$/D', $accessSecret) !== 1)) {
            throw new Failure('feedback-response-invalid', 'Feedback response has an unsupported receipt format.', 1, [], true);
        }
    }

    public function publicData(): array
    {
        return ['receiptId' => $this->receiptId, 'mocked' => $this->mocked, 'delivered' => !$this->mocked];
    }
}
