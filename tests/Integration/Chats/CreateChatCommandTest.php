<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Chats;

use Bitrix24\CLI\Tests\Support\LiveCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * REST cannot delete a chat, so the happy path creates exactly one closed chat with a
 * unique title and only the connection user as member; such chats remain on the portal.
 */
#[Group('chats')]
#[Group('command:chats:add')]
final class CreateChatCommandTest extends LiveCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathCreatesOneClosedChatAndBlocksTheDuplicate(): void
    {
        $title = 'b24cli-chats-add ' . bin2hex(random_bytes(4));
        $description = '[b24cli] chats:add integration';

        $plan = $this->invoke(['chats:add', '--title', $title, '--description', $description, '--dry-run']);
        self::assertSame(['im.chat.add', 'im.dialog.get', 'im.dialog.users.list'], array_column($plan['data']['plan']['steps'], 'method'));
        self::assertNotContains('im.chat.add', array_column($plan['meta']['apiCalls'], 'method'));

        $created = $this->invoke(['chats:add', '--title', $title, '--description', $description, '--user-id', $this->userId]);
        $chatId = $created['data']['operation']['chatId'];
        self::assertSame(['create', 'chat' . $chatId, true, 'verified'], [$created['data']['operation']['action'], $created['data']['operation']['target'], $created['data']['operation']['confirmed'], $created['data']['operation']['readback']]);
        self::assertSame([$this->userId], $created['data']['readback']['members']['present']);
        self::assertSame(1, count(array_filter($created['meta']['apiCalls'], static fn (array $call): bool => $call['method'] === 'im.chat.add')));

        $dialog = $this->api->call('im.dialog.get', 1, ['DIALOG_ID' => 'chat' . $chatId])->result;
        self::assertSame([$chatId, $title, $description, $this->userId], [(int) $dialog['id'], $dialog['name'], $dialog['description'], (int) $dialog['owner']]);

        $messageId = $this->api->call('im.message.add', 1, ['DIALOG_ID' => 'chat' . $chatId, 'MESSAGE' => '[b24cli] chats:add writable'], 'write')->result[0] ?? null;
        self::assertGreaterThan(0, (int) $messageId, 'The created chat must accept messages for chats:send.');

        $duplicate = $this->invoke(['chats:add', '--title', $title], [4]);
        self::assertSame('chat-exists', $duplicate['error']['code']);
        self::assertSame(['chat' . $chatId], array_column($duplicate['error']['details']['candidates'], 'target'));
        self::assertNotContains('im.chat.add', array_column($duplicate['meta']['apiCalls'], 'method'));
    }
}
