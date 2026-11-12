<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat\Request;

/**
 * Normalized chats:add input. $type is the im.chat.add TYPE value: CHAT or OPEN.
 * $maxScan is null when the duplicate title check is skipped.
 */
final readonly class CreateChatRequest
{
    /** @param list<int> $userIds */
    public function __construct(public string $title, public string $type, public ?string $description, public array $userIds, public ?int $maxScan)
    {
    }
}
