<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Chats;

use Bitrix24\CLI\Infrastructure\Connection\EnvConnectionResolver;
use Bitrix24\CLI\Infrastructure\Connection\FileCredentialStore;
use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('chats')]
#[Group('command:chats:resolve')]
final class ResolveChatCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathResolvesTaskChatThroughUrlAndTypedId(): void
    {
        $id = $this->createTask();
        $chatId = $this->card($id, ['chat.id'])['chat']['id'];
        $host = FileCredentialStore::hostname((new EnvConnectionResolver($this->root()))->webhook());
        foreach (['task:' . $id, 'https://' . $host . '/company/personal/user/' . $this->userId . '/tasks/task/view/' . $id . '/'] as $target) {
            $result = $this->invoke(['chats:resolve', $target])['data'];
            self::assertSame(['task', $id, $chatId], [$result['destination']['kind'], $result['destination']['taskId'], $result['destination']['chatId']]);
            self::assertSame(['TASKS_TASK', (string) $id], [$result['dialog']['entity_type'], $result['dialog']['entity_id']]);
        }
    }

    #[Group('portal-acceptance')]
    public function testProjectChatIsTheGroupChatAndReadStateIsUnchanged(): void
    {
        $group = $this->setting('B24CLI_TEST_GROUP_ID');
        if ($group === '') {
            self::markTestSkipped('Project chat acceptance needs B24CLI_TEST_GROUP_ID.');
        }

        $before = $this->api->call('im.dialog.get', 1, ['DIALOG_ID' => 'sg' . $group])->result;
        $result = $this->invoke(['chats:resolve', 'group:' . $group])['data'];
        $after = $this->api->call('im.dialog.get', 1, ['DIALOG_ID' => 'sg' . $group])->result;
        self::assertSame(['project', (int) $group, (int) $before['id']], [$result['destination']['kind'], $result['destination']['groupId'], $result['destination']['chatId']]);
        self::assertSame('SONET_GROUP', $result['dialog']['entity_type']);
        self::assertSame([$before['counter'] ?? null, $before['unread_id'] ?? null], [$after['counter'] ?? null, $after['unread_id'] ?? null]);
    }

    #[Group('portal-acceptance')]
    public function testInaccessibleChatAndForeignPortalAreExplicit(): void
    {
        self::assertSame('chat-unavailable', $this->invoke(['chats:resolve', 'chat2147483646'], [1])['error']['code']);
        self::assertSame('foreign-portal', $this->invoke(['chats:resolve', 'https://other-portal.invalid/workgroups/group/1/'], [2])['error']['code']);
    }
}
