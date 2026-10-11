<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application;

final class Failure extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $exitStatus = 1,
        public readonly array $details = [],
        public readonly bool $outcomeUnknown = false,
    ) {
        parent::__construct($message);
    }

    public static function usage(string $message): self
    {
        return new self('usage-error', $message, 2);
    }

    public static function binding(string $message, bool $chat = false): self
    {
        return new self($chat ? 'message-binding-unverified' : 'resource-binding-unverified', $message, 4);
    }
}
