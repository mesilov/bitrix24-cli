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
        self::assertNull($result['data']['operation']['resourceId']);
        self::assertTrue($result['data']['operation']['confirmed']);
        $own = array_values(array_filter($this->chatMessages($id), static fn (array $row): bool => $row['text'] === $text));
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
