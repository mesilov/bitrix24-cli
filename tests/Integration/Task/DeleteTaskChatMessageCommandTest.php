<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:chat:delete')]
final class DeleteTaskChatMessageCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathDeletesOwnTaskMessage(): void
    {
        $id = $this->createTask();
        $text = $this->fixtureTitle();
        $messageId = $this->createMessage($id, $text);
        $result = $this->invoke(['task:chat:delete', $id, '--message', $messageId, '--force']);
        self::assertTrue($result['data']['operation']['confirmed']);
        $messages = $this->chatMessages($id);
        self::assertNotContains($text, array_column($messages, 'text'));
    }
}
