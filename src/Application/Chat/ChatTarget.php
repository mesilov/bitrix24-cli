<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat;

/**
 * Typed chat destination parsed locally from a URL or a typed ID.
 *
 * kind: chat (chatN), project (sgN / group:N) or task (task:N).
 * id: chat ID, group ID or task ID according to kind; IDs never substitute each other.
 */
final readonly class ChatTarget
{
    public const KIND_CHAT = 'chat';

    public const KIND_PROJECT = 'project';

    public const KIND_TASK = 'task';

    public function __construct(
        public string $kind,
        public int $id,
        public string $source,
        public ?string $host = null,
        public ?int $messageId = null,
    ) {
    }

    /** Normalized typed destination: chatN, sgN or task:N. */
    public function target(): string
    {
        return match ($this->kind) {
            self::KIND_CHAT => 'chat' . $this->id,
            self::KIND_PROJECT => 'sg' . $this->id,
            default => 'task:' . $this->id,
        };
    }
}
