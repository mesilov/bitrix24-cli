<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Chats;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('chats')]
#[Group('command:chats:find')]
final class FindChatCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathFindsTaskChatByTaskTitle(): void
    {
        $title = 'b24cli-chats-find ' . bin2hex(random_bytes(4));
        $id = $this->createTask($title);
        $chatId = $this->card($id, ['chat.id'])['chat']['id'];
        $result = $this->invoke(['chats:find', '--name', $title]);
        self::assertSame(['status' => 'unique', 'target' => 'task:' . $id, 'exactMatches' => 1], $result['data']['selection']);
        self::assertSame(['task', $chatId, true], [$result['data']['items'][0]['source'], $result['data']['items'][0]['chatId'], $result['meta']['complete']]);
    }

    #[Group('portal-acceptance')]
    public function testProjectIsFoundByGroupName(): void
    {
        $group = $this->setting('B24CLI_TEST_GROUP_ID');
        if ($group === '') {
            self::markTestSkipped('Project search acceptance needs B24CLI_TEST_GROUP_ID.');
        }

        $name = $this->api->call('sonet_group.get', 1, ['FILTER' => ['ID' => (int) $group]])->result[0]['NAME'];
        $result = $this->invoke(['chats:find', '--name', $name], [0, 3]);
        $project = array_values(array_filter($result['data']['items'], static fn (array $item): bool => $item['target'] === 'sg' . $group));
        self::assertSame([true, true], [$project[0]['exact'], $project[0]['writable']]);
    }
}
