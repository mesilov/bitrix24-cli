<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:chat:send')]
final class SendTaskChatMessageCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathSendsMessageToTaskChat(): void
    {
        $id = $this->createTask();
        $text = $this->fixtureTitle();
        $result = $this->invoke(['task:chat:send', $id, '--text', $text]);
        $operation = $result['data']['operation'];
        self::assertTrue($operation['confirmed']);
        self::assertSame('verified', $operation['readback'], json_encode($result['data']['readback']));
        $own = array_values(array_filter($this->chatMessages($id), static fn (array $row): bool => $row['text'] === $text));
        self::assertCount(1, $own);
        self::assertSame($this->userId, $own[0]['authorId']);
        self::assertSame($own[0]['id'], $operation['messageId']);
        self::assertSame($own[0]['id'], $operation['resourceId']);
        self::assertSame($this->card($id, ['chat.id'])['chat']['id'], $operation['chatId']);
        self::assertSame('chat' . $operation['chatId'], $operation['dialogId']);
    }

    #[Group('happy-path')]
    public function testMultilineMessageIsFoundByReadback(): void
    {
        $id = $this->createTask();
        $text = $this->fixtureTitle() . "\nВторая строка";
        $result = $this->invoke(['task:chat:send', $id, '--text', $text]);
        self::assertSame('verified', $result['data']['operation']['readback'], json_encode($result['data']['readback']));
        $own = array_values(array_filter($this->chatMessages($id), static fn (array $row): bool => $row['id'] === $result['data']['operation']['messageId']));
        self::assertCount(1, $own);
        self::assertSame($this->userId, $own[0]['authorId']);
    }

    #[Group('portal-acceptance')]
    public function testSecondUserWithoutTaskRoleCannotAccessOwnedFixture(): void
    {
        [$second] = $this->secondUser();
        $id = $this->createTask();


        $this->assertSecondUserDenied(['task:chat:send', $id, '--text', 'denied']);

    }

}
