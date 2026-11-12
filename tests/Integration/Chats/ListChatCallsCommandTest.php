<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Chats;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('chats')]
#[Group('command:chats:calls:list')]
final class ListChatCallsCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathTaskChatWithoutCallsIsCompleteAndEmpty(): void
    {
        $id = $this->createTask();
        $this->createMessage($id, $this->fixtureTitle());
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $result = $this->invoke(['chats:calls:list', 'task:' . $id, '--from', $now->modify('-1 hour')->format(\DATE_ATOM), '--to', $now->modify('+1 hour')->format(\DATE_ATOM)]);
        self::assertTrue($result['meta']['complete']);
        self::assertSame([], $result['data']['items']);
        self::assertSame(0, $result['meta']['calls']['found']);
        self::assertNotContains('im.call.get', array_column($result['meta']['apiCalls'], 'method'));
    }

    #[Group('portal-acceptance')]
    public function testKnownCallIsListedWithoutConnectionSecrets(): void
    {
        [$chat, $callId] = [$this->setting('B24CLI_TEST_CALL_CHAT'), (int) $this->setting('B24CLI_TEST_CALL_ID')];
        if ($chat === '' || $callId < 1) {
            self::markTestSkipped('Call acceptance needs B24CLI_TEST_CALL_CHAT and B24CLI_TEST_CALL_ID of a finished call.');
        }

        $raw = $this->api->call('im.call.get', 1, ['callId' => $callId])->result;
        $start = new \DateTimeImmutable($raw['call']['START_DATE']);
        $end = new \DateTimeImmutable($raw['call']['END_DATE']);
        $result = $this->invoke(['chats:calls:list', $chat, '--from', $start->modify('-1 minute')->format(\DATE_ATOM), '--to', $end->modify('+10 minutes')->format(\DATE_ATOM)], [0, 3]);
        $call = array_values(array_filter($result['data']['items'], static fn (array $item): bool => $item['callId'] === $callId))[0] ?? null;
        self::assertNotNull($call);
        self::assertSame([$end->getTimestamp() - $start->getTimestamp(), 'ok', 1], [$call['durationSeconds'], $call['details']['status'], $call['participants']['inferred']['rulesVersion']]);
        $encoded = json_encode($result, JSON_THROW_ON_ERROR);
        foreach (array_filter([$raw['connectionData']['jwt'] ?? null, $raw['logToken'] ?? null]) as $secret) {
            self::assertStringNotContainsString((string) $secret, $encoded);
        }

        self::assertStringNotContainsString('publicChannels', $encoded);
    }
}
