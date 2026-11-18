<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\TestCase;

/**
 * #223: tasks.task.chat.message.send answers only true, so the sent message is found by a
 * readback after the remembered latest message ID. The webhook user of SpyConnectionResolver is 1.
 */
final class TaskChatSendReadbackTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $messages = [];

    /** @var list<array<string, mixed>> messages the server adds when the send is accepted */
    private array $onSend = [];

    private array $chat = ['id' => 456, 'entityId' => 123, 'entityType' => 'TASKS_TASK'];

    private bool $readFails = false;

    private function portal(string $method, int $version, array $params): ApiResponse
    {
        return match ($method) {
            'tasks.task.get' => new ApiResponse(['item' => ['id' => 123, 'chat' => $this->chat]]),
            'im.dialog.get' => new ApiResponse(['id' => 456, 'dialog_id' => 'chat456', 'last_message_id' => max([0, ...array_column($this->messages, 'id')])]),
            'tasks.task.chat.message.send' => $this->accept(),
            'im.dialog.messages.get' => $this->page($params),
            'im.message.update' => new ApiResponse(['result' => true]),
            default => throw new \LogicException('Unexpected ' . $method),
        };
    }

    private function accept(): ApiResponse
    {
        array_push($this->messages, ...$this->onSend);
        return new ApiResponse(['result' => true]);
    }

    private function page(array $params): ApiResponse
    {
        if ($this->readFails && isset($params['FIRST_ID'])) {
            throw new Failure('api-error', 'Fixture read failure.', 1);
        }

        $rows = $this->messages;
        if (isset($params['FIRST_ID'])) {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => $row['id'] > $params['FIRST_ID']));
            usort($rows, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);
            $rows = array_slice($rows, 0, $params['LIMIT']);
        } elseif (isset($params['LAST_ID'])) {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => $row['id'] < $params['LAST_ID']));
            usort($rows, static fn (array $a, array $b): int => $b['id'] <=> $a['id']);
            $rows = array_slice($rows, 0, $params['LIMIT']);
        }

        return new ApiResponse(['chat_id' => 456, 'messages' => $rows]);
    }

    private function message(int $id, int $author, string $text): array
    {
        return ['id' => $id, 'chat_id' => 456, 'author_id' => $author, 'text' => $text, 'date' => '2026-11-18T10:00:00+03:00', 'unread' => false];
    }

    /** @return array{0: int, 1: array, 2: list<array>} */
    private function invoke(array $argv): array
    {
        $fakeApiTransport = new FakeApiTransport($this->portal(...));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, new SpyConnectionResolver());
        $result = ConsoleHarness::run($app, ['--json', ...$argv]);
        return [$result->status, $result->json(), $fakeApiTransport->calls];
    }

    private function sends(array $calls): int
    {
        return count(array_filter($calls, static fn (array $call): bool => $call['method'] === 'tasks.task.chat.message.send'));
    }

    public function testSingleNewOwnMessageIsVerifiedAndOlderOrForeignTwinsAreIgnored(): void
    {
        $this->messages = [$this->message(10, 1, 'Договор готов'), $this->message(11, 2, 'Ок')];
        $this->onSend = [$this->message(12, 2, 'Договор готов'), $this->message(13, 1, "Договор готов"), $this->message(14, 2, 'Спасибо')];
        [$status, $json, $calls] = $this->invoke(['task:chat:send', '123', '--text', 'Договор готов']);
        self::assertSame(0, $status, json_encode($json));
        $operation = $json['data']['operation'];
        self::assertSame(13, $operation['resourceId']);
        self::assertSame(13, $operation['messageId']);
        self::assertSame(456, $operation['chatId']);
        self::assertSame('chat456', $operation['dialogId']);
        self::assertSame('verified', $operation['readback']);
        self::assertTrue($operation['confirmed']);
        self::assertSame(['status' => 'verified', 'messageId' => 13, 'chatId' => 456, 'baselineMessageId' => 11, 'scanned' => 3, 'candidates' => [13]], $json['data']['readback']);
        self::assertTrue($json['meta']['complete']);
        self::assertSame(['tasks.task.get', 'im.dialog.get', 'tasks.task.chat.message.send', 'im.dialog.messages.get'], array_column($calls, 'method'));
        self::assertSame(['fields' => ['taskId' => 123, 'text' => 'Договор готов']], $calls[2]['parameters']);
        self::assertSame(['DIALOG_ID' => 'chat456', 'LIMIT' => 50, 'FIRST_ID' => 11], $calls[3]['parameters']);
    }

    public function testMultilineTextMatchesAfterLineEndingNormalization(): void
    {
        $this->onSend = [$this->message(5, 1, "Строка 1\nСтрока 2")];
        [$status, $json] = $this->invoke(['task:chat:send', '123', '--text', "Строка 1\r\nСтрока 2\n"]);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(5, $json['data']['operation']['messageId']);
    }

    public function testTwoMatchingMessagesAreAmbiguousAndTheSendIsNotRepeated(): void
    {
        $this->messages = [$this->message(20, 1, 'old')];
        $this->onSend = [$this->message(21, 1, 'Повтор'), $this->message(22, 1, 'Повтор')];
        [$status, $json, $calls] = $this->invoke(['task:chat:send', '123', '--text', 'Повтор']);
        self::assertSame(3, $status, json_encode($json));
        self::assertNull($json['data']['operation']['messageId']);
        self::assertNull($json['data']['operation']['resourceId']);
        self::assertTrue($json['data']['operation']['confirmed']);
        self::assertSame('ambiguous', $json['data']['readback']['status']);
        self::assertSame([21, 22], $json['data']['readback']['candidates']);
        self::assertFalse($json['meta']['complete']);
        self::assertSame('readback-unverified', $json['meta']['reason']);
        self::assertFalse($json['meta']['outcomeUnknown']);
        self::assertSame(1, $this->sends($calls));
    }

    public function testNoMatchingMessageIsMissing(): void
    {
        $this->onSend = [$this->message(3, 1, 'Изменённый сервером текст')];
        [$status, $json, $calls] = $this->invoke(['task:chat:send', '123', '--text', 'Исходный текст']);
        self::assertSame(3, $status, json_encode($json));
        self::assertSame('missing', $json['data']['readback']['status']);
        self::assertSame([], $json['data']['readback']['candidates']);
        self::assertSame(1, $this->sends($calls));
    }

    public function testReadbackFailureIsReportedWithoutRepeatingTheSend(): void
    {
        $this->readFails = true;
        $this->onSend = [$this->message(3, 1, 'Hello')];
        [$status, $json, $calls] = $this->invoke(['task:chat:send', '123', '--text', 'Hello']);
        self::assertSame(3, $status, json_encode($json));
        self::assertSame('failed', $json['data']['readback']['status']);
        self::assertSame('api-error', $json['data']['readback']['error']);
        self::assertTrue($json['data']['operation']['confirmed']);
        self::assertSame(1, $this->sends($calls));
    }

    public function testExhaustedBudgetIsIncompleteEvenWithOneCandidate(): void
    {
        $this->onSend = [$this->message(1, 1, 'Hello')];
        foreach (range(2, 260) as $id) {
            $this->onSend[] = $this->message($id, 2, 'chatter ' . $id);
        }

        [$status, $json] = $this->invoke(['task:chat:send', '123', '--text', 'Hello']);
        self::assertSame(3, $status, json_encode($json));
        self::assertSame('incomplete', $json['data']['readback']['status']);
        self::assertSame([1], $json['data']['readback']['candidates']);
        self::assertNull($json['data']['operation']['messageId']);
    }

    public function testUnverifiedTaskChatBindingSendsNothing(): void
    {
        $this->chat = ['id' => 456, 'entityId' => 999, 'entityType' => 'TASKS_TASK'];
        [$status, $json, $calls] = $this->invoke(['task:chat:send', '123', '--text', 'Hello']);
        self::assertSame(4, $status, json_encode($json));
        self::assertSame('message-binding-unverified', $json['error']['code']);
        self::assertSame(0, $this->sends($calls));
    }

    public function testDryRunChecksBindingAndPlansTheReadbackWithoutWriting(): void
    {
        [$status, $json, $calls] = $this->invoke(['task:chat:send', '123', '--text', 'Hello', '--dry-run']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(['tasks.task.get'], array_column($calls, 'method'));
        self::assertSame(['im.dialog.get', 'tasks.task.chat.message.send', 'im.dialog.messages.get'], array_column($json['data']['plan']['steps'], 'method'));
    }

    public function testUpdateReturnsChatAndMessageIds(): void
    {
        $this->messages = [$this->message(900, 1, 'Original')];
        [$status, $json] = $this->invoke(['task:chat:update', '123', '--message', '900', '--text', 'Changed']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(900, $json['data']['operation']['resourceId']);
        self::assertSame(900, $json['data']['operation']['messageId']);
        self::assertSame(456, $json['data']['operation']['chatId']);
    }
}
