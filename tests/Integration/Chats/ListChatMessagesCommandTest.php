<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Chats;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('chats')]
#[Group('command:chats:messages:list')]
final class ListChatMessagesCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathReturnsOwnMessageOfTaskChatInPeriod(): void
    {
        $id = $this->createTask();
        $text = $this->fixtureTitle();
        $messageId = $this->createMessage($id, $text);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $result = $this->invoke(['chats:messages:list', 'task:' . $id, '--from', $now->modify('-1 hour')->format(\DATE_ATOM), '--to', $now->modify('+1 hour')->format(\DATE_ATOM), '--timezone', 'UTC']);
        self::assertTrue($result['meta']['complete']);
        $own = array_values(array_filter($result['data']['items'], static fn (array $row): bool => $row['id'] === $messageId));
        self::assertCount(1, $own);
        self::assertSame([$text, $this->userId], [$own[0]['text'], $own[0]['authorId']]);
        self::assertContains($this->userId, array_column($result['data']['authors'], 'id'));
        $outside = $this->invoke(['chats:messages:list', 'task:' . $id, '--from', $now->modify('-3 hours')->format(\DATE_ATOM), '--to', $now->modify('-2 hours')->format(\DATE_ATOM)]);
        self::assertSame([], $outside['data']['items']);
        self::assertTrue($outside['meta']['complete']);
    }

    #[Group('portal-acceptance')]
    public function testProjectYesterdayKeepsReadStateAndReportsPeriod(): void
    {
        $group = $this->setting('B24CLI_TEST_GROUP_ID');
        if ($group === '') {
            self::markTestSkipped('Project chat acceptance needs B24CLI_TEST_GROUP_ID.');
        }

        $before = $this->api->call('im.dialog.get', 1, ['DIALOG_ID' => 'sg' . $group])->result;
        $result = $this->invoke(['chats:messages:list', 'group:' . $group, '--period', 'yesterday', '--timezone', 'Europe/Moscow', '--max-messages', '10000'], [0, 3]);
        $after = $this->api->call('im.dialog.get', 1, ['DIALOG_ID' => 'sg' . $group])->result;
        self::assertSame(['yesterday', 'Europe/Moscow'], [$result['data']['period']['kind'], $result['data']['period']['timezone']]);
        self::assertSame((int) $before['id'], $result['data']['destination']['chatId']);
        self::assertSame([$before['counter'] ?? null, $before['unread_id'] ?? null], [$after['counter'] ?? null, $after['unread_id'] ?? null]);
    }
}
