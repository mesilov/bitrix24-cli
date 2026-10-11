<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:chat:update')]
final class UpdateTaskChatMessageCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathUpdatesOwnTaskMessage(): void
    {
        $id = $this->createTask();
        $text = $this->fixtureTitle();
        $messageId = $this->createMessage($id, $text);
        $this->invoke(['task:chat:update', $id, '--message', $messageId, '--text', $text . '-changed']);
        $messages = array_column($this->chatMessages($id), null, 'id');
        self::assertSame($text . '-changed', $messages[$messageId]['text']);
    }

    public function testMessageFromOtherTaskIsRejected(): void
    {
        $id = $this->createTask();
        $text = $this->fixtureTitle();
        $messageId = $this->createMessage($id, $text);
        $other = $this->createTask();
        $result = $this->invoke(['task:chat:update', $other, '--message', $messageId, '--text', 'foreign'], [4]);
        self::assertSame('message-binding-unverified', $result['error']['code']);
        self::assertSame($text, array_column($this->chatMessages($id), null, 'id')[$messageId]['text']);
    }
}
