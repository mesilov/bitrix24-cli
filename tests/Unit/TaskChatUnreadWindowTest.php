<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\TestCase;

/**
 * Regression for #202: without LAST_ID im.dialog.messages.get returns the window of the
 * first unread message (observed on the portal 12.11.2026), so newer messages were lost.
 */
final class TaskChatUnreadWindowTest extends TestCase
{
    private int $count = 120;

    private int $unreadId = 10;

    private function portal(string $method, int $version, array $params): ApiResponse
    {
        return match ($method) {
            'tasks.task.get' => new ApiResponse(['item' => ['id' => 123, 'chat' => ['id' => 456, 'entityId' => 123, 'entityType' => 'TASKS_TASK']]]),
            'im.dialog.get' => new ApiResponse(['id' => 456, 'dialog_id' => 'chat456', 'type' => 'tasksTask', 'last_message_id' => $this->count, 'unread_id' => $this->unreadId, 'counter' => $this->count - $this->unreadId + 1]),
            'im.dialog.messages.get' => new ApiResponse(['chat_id' => 456, 'messages' => $this->page($params)]),
            'im.message.update' => new ApiResponse(['result' => true]),
            default => throw new \LogicException('Unexpected ' . $method),
        };
    }

    private function page(array $params): array
    {
        if ($this->count === 0 || ($params['LAST_ID'] ?? 2) <= 1) {
            return [];
        }

        if (isset($params['FIRST_ID'])) {
            $ids = range($params['FIRST_ID'] + 1, min($this->count, $params['FIRST_ID'] + $params['LIMIT']));
        } elseif (isset($params['LAST_ID'])) {
            $ids = range(min($this->count, $params['LAST_ID'] - 1), max(1, $params['LAST_ID'] - $params['LIMIT']));
        } else {
            // Unread window: LIMIT messages from the first unread plus two older ones, more than LIMIT rows.
            $ids = range(min($this->count, $this->unreadId + $params['LIMIT'] - 1), max(1, $this->unreadId - 2));
        }

        return array_values(array_map(static fn (int $id): array => ['id' => $id, 'chat_id' => 456, 'author_id' => 2, 'text' => 'm' . $id, 'date' => '2026-11-12T09:00:00+03:00'], array_filter($ids, static fn (int $id): bool => $id >= 1)));
    }

    private function invoke(array $argv): array
    {
        $fakeApiTransport = new FakeApiTransport($this->portal(...));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, new SpyConnectionResolver());
        $result = ConsoleHarness::run($app, ['--json', ...$argv]);
        return [$result->status, $result->json(), $fakeApiTransport->calls];
    }

    public function testLimitReturnsLatestMessagesNotUnreadWindow(): void
    {
        [$status, $json, $calls] = $this->invoke(['task:chat:list', '123', '--limit', '3']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame([118, 119, 120], array_column($json['data']['items'], 'id'));
        self::assertSame(['tasks.task.get', 'im.dialog.get', 'im.dialog.messages.get'], array_column($calls, 'method'));
        self::assertSame(['DIALOG_ID' => 'chat456', 'LIMIT' => 3, 'LAST_ID' => 121], $calls[2]['parameters']);
    }

    public function testFullHistoryIncludesMessagesAfterTheUnreadWindow(): void
    {
        [$status, $json] = $this->invoke(['task:chat:list', '123', '--all']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(range(1, 120), array_column($json['data']['items'], 'id'));
        self::assertTrue($json['meta']['complete']);
    }

    public function testUpdateFindsNewestMessageWhileUnreadExist(): void
    {
        [$status, $json, $calls] = $this->invoke(['task:chat:update', '123', '--message', '120', '--text', 'Изменено']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame('im.message.update', end($calls)['method']);
    }

    public function testExplicitWindowsKeepTheirCursorWithoutDialogLookup(): void
    {
        [$status, $json, $calls] = $this->invoke(['task:chat:list', '123', '--before', '50', '--limit', '2']);
        self::assertSame(0, $status);
        self::assertSame([48, 49], array_column($json['data']['items'], 'id'));
        self::assertNotContains('im.dialog.get', array_column($calls, 'method'));
        [$status, $json, $calls] = $this->invoke(['task:chat:list', '123', '--after', '50', '--limit', '2']);
        self::assertSame(0, $status);
        self::assertSame([51, 52], array_column($json['data']['items'], 'id'));
        self::assertNotContains('im.dialog.get', array_column($calls, 'method'));
    }

    public function testEmptyChatIsCompleteAndStrictPolicyStillDenies(): void
    {
        $this->count = 0;
        [$status, $json] = $this->invoke(['task:chat:list', '123']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame([], $json['data']['items']);
        [$status, $json, $calls] = $this->invoke(['--api-policy', 'strict-rest3', 'task:chat:list', '123']);
        self::assertSame([4, 'policy-denied', []], [$status, $json['error']['code'], $calls]);
    }
}
