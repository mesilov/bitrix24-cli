<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Chats;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('chats')]
#[Group('command:chats:calls:show')]
final class ShowChatCallCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathKnownCallMatchesCallData(): void
    {
        [$chat, $callId] = [$this->setting('B24CLI_TEST_CALL_CHAT'), (int) $this->setting('B24CLI_TEST_CALL_ID')];
        if ($chat === '' || $callId < 1) {
            self::markTestSkipped('Call acceptance needs B24CLI_TEST_CALL_CHAT and B24CLI_TEST_CALL_ID of a finished call.');
        }

        $raw = $this->api->call('im.call.get', 1, ['callId' => $callId])->result;
        $result = $this->invoke(['chats:calls:show', $chat, $callId], [0, 3]);
        $call = $result['data']['call'];
        self::assertSame([$callId, $raw['call']['UUID'], (int) $raw['call']['CHAT_ID']], [$call['callId'], $call['uuid'], $result['data']['destination']['chatId']]);
        self::assertSame(['start' => true, 'finish' => true], $call['inWindow']);
        self::assertSame(count($raw['users']), count($call['participants']['eligible']['users']));
        $encoded = json_encode($result, JSON_THROW_ON_ERROR);
        foreach (array_filter([$raw['connectionData']['jwt'] ?? null, $raw['logToken'] ?? null]) as $secret) {
            self::assertStringNotContainsString((string) $secret, $encoded);
        }
    }

    #[Group('portal-acceptance')]
    public function testCallOfAnotherChatIsRejected(): void
    {
        $callId = (int) $this->setting('B24CLI_TEST_CALL_ID');
        if ($callId < 1) {
            self::markTestSkipped('Call binding acceptance needs B24CLI_TEST_CALL_ID.');
        }

        $id = $this->createTask();
        $result = $this->invoke(['chats:calls:show', 'task:' . $id, $callId], [4]);
        self::assertSame(['resource-binding-unverified', null], [$result['error']['code'], $result['data']]);
    }
}
