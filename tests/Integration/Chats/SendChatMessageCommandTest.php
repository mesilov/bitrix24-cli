<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Chats;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('chats')]
#[Group('command:chats:send')]
final class SendChatMessageCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathSendsOneMessageToOwnTaskChatAndReadsItBack(): void
    {
        $title = 'b24cli-chats-send ' . bin2hex(random_bytes(4));
        $id = $this->createTask($title);
        $chatId = $this->card($id, ['chat.id'])['chat']['id'];
        $text = '[b24cli] chats:send ' . bin2hex(random_bytes(4));

        $plan = $this->invoke(['chats:send', '--name', $title, '--text', $text, '--dry-run']);
        self::assertSame('task:' . $id . ' (chat' . $chatId . ')', $plan['data']['plan']['target']);
        self::assertNotContains('im.message.add', array_column($plan['meta']['apiCalls'], 'method'));

        $sent = $this->invoke(['chats:send', 'task:' . $id, '--text', $text]);
        $operation = $sent['data']['operation'];
        self::assertSame(['task:' . $id, $chatId, true, 'verified'], [$operation['target'], $operation['chatId'], $operation['confirmed'], $operation['readback']]);
        self::assertSame(1, count(array_filter($sent['meta']['apiCalls'], static fn (array $call): bool => $call['method'] === 'im.message.add')));

        $rows = $this->api->call('im.dialog.messages.get', 1, ['DIALOG_ID' => 'chat' . $chatId, 'LAST_ID' => $operation['messageId'] + 1, 'LIMIT' => 1])->result['messages'];
        self::assertSame([$operation['messageId'], $chatId, $text], [(int) $rows[0]['id'], (int) $rows[0]['chat_id'], $rows[0]['text']]);
    }
}
