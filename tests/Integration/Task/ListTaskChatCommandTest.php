<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:chat:list')]
final class ListTaskChatCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathListsMessagesOfSelectedTask(): void
    {
        $id = $this->createTask();
        $text = $this->fixtureTitle();
        $messageId = $this->createMessage($id, $text);
        $messages = $this->invoke(['task:chat:list', $id, '--all'])['data']['items'];
        $own = array_values(array_filter($messages, static fn (array $row): bool => $row['id'] === $messageId));
        self::assertCount(1, $own);
        self::assertSame($text, $own[0]['text']);
        self::assertSame($this->userId, $own[0]['authorId']);
    }
}
