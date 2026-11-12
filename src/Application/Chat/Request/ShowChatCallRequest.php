<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat\Request;

use Bitrix24\CLI\Application\Chat\ChatTarget;

final readonly class ShowChatCallRequest
{
    public function __construct(public ChatTarget $target, public int $callId, public int $maxMessages)
    {
    }
}
