<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat\Request;

use Bitrix24\CLI\Application\Chat\ChatTarget;

/** Exactly one of $target and $name is set. */
final readonly class SendChatMessageRequest
{
    public function __construct(public ?ChatTarget $target, public ?string $name, public string $text, public int $maxScan = 10000)
    {
    }
}
