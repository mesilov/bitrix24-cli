<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat\Request;

use Bitrix24\CLI\Application\Chat\ChatTarget;
use Bitrix24\CLI\Application\Chat\MessagePeriod;

final readonly class ListChatMessagesRequest
{
    public function __construct(public ChatTarget $target, public MessagePeriod $period, public ?int $limit, public int $maxMessages)
    {
    }
}
