<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Application\Task\UnreadCounterSnapshot;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest1ImCounterAdapter;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\ConsoleRun;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Issue #148: server unread state of task chats for the connection user.
 * Counter shapes follow the observed im.counters.get sections (openspec change add-task-unread-state).
 */
final class TaskUnreadStateTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $counters = ['TASKS_TASK' => [], 'CHAT_MUTED' => [], 'TASKS_TASK_UNREAD' => [], 'CHAT' => []];

    /** @var list<array<string, mixed>> */
    private array $tasks = [];

    /** @var array<int, array<string, mixed>|\Throwable> */
    private array $cards = [];

    private function snapshot(array $result): UnreadCounterSnapshot
    {
        $clock = new class () implements Clock {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-11-13T15:00:00+06:00');
            }
        };
        return (new Rest1ImCounterAdapter(new FakeApiTransport(static fn (): ApiResponse => new ApiResponse($result)), $clock))->snapshot();
    }

    public function testAdapterMergesTaskAndMutedCountersAndKeepsManualMarksApart(): void
    {
        $snapshot = $this->snapshot([
            'TASKS_TASK' => [10872 => 2, '10873' => 1, 500 => 4],
            'CHAT_MUTED' => [10874 => 3, 500 => 4, 900 => 1],
            'TASKS_TASK_UNREAD' => [10872, '10875'],
            'CHAT' => [10872 => 99], 'TYPE' => ['ALL' => 5],
        ]);
        self::assertTrue($snapshot->countersComplete);
        self::assertSame([], $snapshot->reasons);
        self::assertSame(2, $snapshot->counter(10872));
        self::assertSame(1, $snapshot->counter(10873));
        self::assertSame(3, $snapshot->counter(10874));
        self::assertSame(4, $snapshot->counter(500), 'An equal duplicate counts once, never as a sum.');
        self::assertSame(0, $snapshot->counter(10875), 'A manual marker alone is not an unread message.');
        self::assertTrue($snapshot->manuallyMarkedUnread(10875));
        self::assertFalse($snapshot->manuallyMarkedUnread(10873));
        self::assertSame(8, $snapshot->processedEntries);
        self::assertSame('2026-11-13T09:00:00Z', $snapshot->observedAt);
        self::assertSame(['source' => 'im.counters.get', 'observedAt' => '2026-11-13T09:00:00Z', 'countersComplete' => true, 'manualMarksComplete' => true, 'processedEntries' => 8, 'entryLimit' => 10000, 'reasons' => []], $snapshot->metadata([]));
    }

    public function testAdapterAcceptsEmptyArraysAsEmptySections(): void
    {
        $snapshot = $this->snapshot(['TASKS_TASK' => [], 'CHAT_MUTED' => [], 'TASKS_TASK_UNREAD' => []]);
        self::assertTrue($snapshot->countersComplete);
        self::assertSame(0, $snapshot->counter(10872));
        self::assertFalse($snapshot->manuallyMarkedUnread(10872));
        self::assertSame([], $snapshot->reasons);
    }

    public static function unsupportedCounters(): iterable
    {
        yield 'task section missing' => [['CHAT_MUTED' => [], 'CHAT' => [10872 => 1], 'TASKS_TASK_UNREAD' => []]];
        yield 'muted section missing' => [['TASKS_TASK' => [], 'TASKS_TASK_UNREAD' => []]];
        yield 'non-empty list instead of a map' => [['TASKS_TASK' => [3, 4], 'CHAT_MUTED' => [], 'TASKS_TASK_UNREAD' => []]];
        yield 'boolean counter' => [['TASKS_TASK' => [10872 => true], 'CHAT_MUTED' => [], 'TASKS_TASK_UNREAD' => []]];
        yield 'string counter' => [['TASKS_TASK' => [10872 => '1'], 'CHAT_MUTED' => [], 'TASKS_TASK_UNREAD' => []]];
        yield 'negative counter' => [['TASKS_TASK' => [10872 => -1], 'CHAT_MUTED' => [], 'TASKS_TASK_UNREAD' => []]];
        yield 'invalid chat id' => [['TASKS_TASK' => ['abc' => 1], 'CHAT_MUTED' => [], 'TASKS_TASK_UNREAD' => []]];
        yield 'scalar section' => [['TASKS_TASK' => 0, 'CHAT_MUTED' => [], 'TASKS_TASK_UNREAD' => []]];
    }

    #[DataProvider('unsupportedCounters')]
    public function testUnsupportedCounterSchemaIsUnknownNotZero(array $result): void
    {
        $snapshot = $this->snapshot($result);
        self::assertFalse($snapshot->countersComplete);
        self::assertContains('unread-counter-schema-unsupported', $snapshot->reasons);
        self::assertNull($snapshot->counter(10872));
        self::assertNull($snapshot->counter(77));
    }

    public function testConflictingCountersMakeOnlyThatChatUnknown(): void
    {
        $snapshot = $this->snapshot(['TASKS_TASK' => [10872 => 2, 5 => 1], 'CHAT_MUTED' => [10872 => 3], 'TASKS_TASK_UNREAD' => []]);
        self::assertTrue($snapshot->conflict(10872));
        self::assertNull($snapshot->counter(10872));
        self::assertSame(1, $snapshot->counter(5));
        self::assertTrue($snapshot->countersComplete);
    }

    public static function unsupportedManualMarks(): iterable
    {
        yield 'missing' => [['TASKS_TASK' => [], 'CHAT_MUTED' => []]];
        yield 'map instead of id list' => [['TASKS_TASK' => [], 'CHAT_MUTED' => [], 'TASKS_TASK_UNREAD' => [10872 => true]]];
        yield 'invalid id' => [['TASKS_TASK' => [], 'CHAT_MUTED' => [], 'TASKS_TASK_UNREAD' => [true]]];
    }

    #[DataProvider('unsupportedManualMarks')]
    public function testUnsupportedManualMarksAreNull(array $result): void
    {
        $snapshot = $this->snapshot($result);
        self::assertTrue($snapshot->countersComplete);
        self::assertNull($snapshot->manuallyMarkedUnread(10872));
        self::assertSame(['manual-unread-state-unavailable'], $snapshot->reasons);
    }

    public function testEntryLimitKeepsProvenCountersButDoesNotProveZero(): void
    {
        $map = [];
        for ($id = 1; $id <= UnreadCounterSnapshot::ENTRY_LIMIT + 5; $id++) {
            $map[$id] = 1;
        }

        $snapshot = $this->snapshot(['TASKS_TASK' => $map, 'CHAT_MUTED' => [], 'TASKS_TASK_UNREAD' => []]);
        self::assertFalse($snapshot->countersComplete);
        self::assertSame(['unread-counter-budget-exhausted', 'manual-unread-state-unavailable'], $snapshot->reasons);
        self::assertSame(1, $snapshot->counter(1));
        self::assertNull($snapshot->counter(UnreadCounterSnapshot::ENTRY_LIMIT + 3));
        self::assertSame(UnreadCounterSnapshot::ENTRY_LIMIT, $snapshot->processedEntries);
    }

    private function task(int $id, ?int $chatId, string $status = 'pending', int $responsible = 1): array
    {
        return ['id' => $id, 'title' => 'Задача ' . $id, 'responsibleId' => $responsible, 'groupId' => 42, 'status' => $status, 'deadline' => null, ...($chatId === null ? [] : ['chatId' => $chatId])];
    }

    private function portal(string $method, int $version, array $params): ApiResponse
    {
        return match ([$method, $version]) {
            ['im.counters.get', 1] => new ApiResponse($this->counters),
            ['tasks.task.list', 3] => new ApiResponse(['items' => array_slice($this->tasks, $params['pagination']['offset'], $params['pagination']['limit'])]),
            ['tasks.task.list', 1] => new ApiResponse(['tasks' => array_map(static fn (array $task): array => [...array_map(static fn (mixed $value): mixed => is_int($value) ? (string) $value : $value, $task), 'status' => '3'], array_slice($this->tasks, $params['start'], 50))], null, count($this->tasks)),
            ['tasks.task.get', 3] => $this->card($params['id']),
            default => throw new \LogicException('Unexpected ' . $method . ' v' . $version),
        };
    }

    private function card(int $id): ApiResponse
    {
        $card = $this->cards[$id] ?? throw new \LogicException('Unexpected task get ' . $id);
        if ($card instanceof \Throwable) {
            throw $card;
        }

        return new ApiResponse(['item' => ['id' => $id, ...$card]]);
    }

    /** @return array{0: ConsoleRun, 1: FakeApiTransport} */
    private function listRun(array $argv, string $format = '--json'): array
    {
        $fakeApiTransport = new FakeApiTransport($this->portal(...));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, new SpyConnectionResolver());
        return [ConsoleHarness::run($app, [...($format === '' ? [] : [$format]), ...$argv]), $fakeApiTransport];
    }

    private function methods(FakeApiTransport $api): array
    {
        return array_column($api->calls, 'method');
    }

    public function testUnreadPredicateRunsBeforeTheLimitAndUsesOneSnapshot(): void
    {
        $this->tasks = [...array_map(fn (int $id): array => $this->task($id, 20000 + $id), range(120, 61, -1)), $this->task(60, 10872), $this->task(59, 10874)];
        $this->counters = ['TASKS_TASK' => [10872 => 1, 777 => 5], 'CHAT_MUTED' => [10874 => 3, 888 => 2], 'TASKS_TASK_UNREAD' => [], 'CHAT' => []];
        [$run, $api] = $this->listRun(['task:list', '--unread', '--limit', '1']);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        $json = $run->json();
        self::assertSame([60], array_column($json['data']['items'], 'id'));
        self::assertSame(['id' => 60, 'title' => 'Задача 60', 'responsibleId' => 1, 'groupId' => 42, 'status' => 'pending', 'deadline' => null, 'chatState' => ['chatId' => 10872, 'counter' => 1, 'manuallyMarkedUnread' => false]], $json['data']['items'][0]);
        self::assertSame(2, $json['meta']['matched'], 'The muted task counts as unread too.');
        self::assertTrue($json['meta']['limitApplied']);
        self::assertTrue($json['meta']['complete']);
        self::assertSame(62, $json['meta']['scanned']);
        self::assertSame(['source' => 'im.counters.get', 'observedAt' => $json['meta']['unreadState']['observedAt'], 'countersComplete' => true, 'manualMarksComplete' => true, 'processedEntries' => 4, 'entryLimit' => 10000, 'reasons' => []], $json['meta']['unreadState']);
        self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $json['meta']['unreadState']['observedAt']);
        self::assertSame(['im.counters.get', 'tasks.task.list', 'tasks.task.list'], $this->methods($api));
        self::assertContains('chatId', $api->calls[1]['parameters']['select']);
        self::assertSame([['im.counters.get', 1], ['tasks.task.list', 3], ['tasks.task.list', 3]], array_map(static fn (array $call): array => [$call['method'], $call['version']], $api->calls));
        self::assertSame(['read'], array_values(array_unique(array_column($api->calls, 'effect'))), 'Observation never writes read state.');
    }

    public function testPlainListIsUnchanged(): void
    {
        $this->tasks = [$this->task(60, 10872)];
        [$run, $api] = $this->listRun(['task:list']);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertArrayNotHasKey('chatState', $run->json()['data']['items'][0]);
        self::assertArrayNotHasKey('unreadState', $run->json()['meta']);
        self::assertSame(['tasks.task.list'], $this->methods($api));
        self::assertNotContains('chatId', $api->calls[0]['parameters']['select']);
    }

    public function testServerRouteJoinsTheRest1ChatIdAndCombinesCriteria(): void
    {
        $this->tasks = [$this->task(60, 10872), $this->task(59, 10873), $this->task(58, 10874)];
        $this->counters = ['TASKS_TASK' => [10872 => 2, 10874 => 1], 'CHAT_MUTED' => [], 'TASKS_TASK_UNREAD' => [10872, 10873], 'CHAT' => []];
        [$run, $api] = $this->listRun(['task:list', '--unread', '--group-id', '42', '--status', 'in_progress', '--role', 'responsible', '--all']);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        $json = $run->json();
        self::assertSame([60, 58], array_column($json['data']['items'], 'id'), 'The manual marker alone (59) does not include a task.');
        self::assertSame(['chatId' => 10872, 'counter' => 2, 'manuallyMarkedUnread' => true], $json['data']['items'][0]['chatState']);
        self::assertSame('1.0', $json['meta']['listRoute']['apiVersion']);
        self::assertSame(['GROUP_ID' => 42, 'RESPONSIBLE_ID' => 1, 'REAL_STATUS' => 3], (array) $api->calls[1]['parameters']['filter']);
        self::assertContains('CHAT_ID', $api->calls[1]['parameters']['select']);
        self::assertArrayNotHasKey('chatId', $json['data']['items'][0]);
    }

    public function testTemporalRouteDeclaresTheSnapshotAndReadsChatId(): void
    {
        $this->tasks = [$this->task(60, 10872)];
        [$run, $api] = $this->listRun(['task:list', '--unread', '--deadline-window', 'this-week', '--timezone', 'UTC']);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame([['im.counters.get', 1], ['tasks.task.list', 1]], array_map(static fn (array $call): array => [$call['method'], $call['version']], $api->calls));
        self::assertContains('CHAT_ID', $api->calls[1]['parameters']['select']);
        self::assertArrayHasKey('deadlineFilter', $run->json()['meta']);
        self::assertArrayHasKey('unreadState', $run->json()['meta']);
    }

    public function testMissingChatIdReadsTheBindingOnlyForMatchedTasks(): void
    {
        $this->tasks = [$this->task(60, null), $this->task(59, null), $this->task(58, null, 'pending', 7), $this->task(57, null)];
        $this->cards = [
            60 => ['chat' => ['id' => 10872, 'entityId' => 60, 'entityType' => 'TASKS_TASK']],
            59 => ['chat' => null],
            57 => ['chat' => ['id' => 10999, 'entityId' => 1, 'entityType' => 'TASKS_TASK']],
        ];
        $this->counters = ['TASKS_TASK' => [10872 => 1, 10999 => 4], 'CHAT_MUTED' => [], 'TASKS_TASK_UNREAD' => [], 'CHAT' => []];
        [$run, $api] = $this->listRun(['task:list', '--unread', '--where', '{"responsibleId":1}', '--all']);
        self::assertSame(3, $run->status, $run->stdout . $run->stderr);
        $json = $run->json();
        self::assertSame([60], array_column($json['data']['items'], 'id'));
        self::assertFalse($json['meta']['complete']);
        self::assertSame('task-chat-association-unavailable', $json['meta']['reason']);
        self::assertSame(['task-chat-association-unavailable'], $json['meta']['unreadState']['reasons']);
        self::assertSame([60, 59, 57], array_column(array_column(array_filter($api->calls, static fn (array $call): bool => $call['method'] === 'tasks.task.get'), 'parameters'), 'id'), 'Task 58 fails the criteria and needs no binding read.');
    }

    public function testUnsupportedSchemaIsPartialEvenWithoutMatches(): void
    {
        $this->tasks = [$this->task(60, 10872)];
        $this->counters = ['CHAT' => [10872 => 1], 'CHAT_MUTED' => [], 'TASKS_TASK_UNREAD' => []];
        [$run] = $this->listRun(['task:list', '--unread']);
        self::assertSame(3, $run->status, $run->stdout . $run->stderr);
        $json = $run->json();
        self::assertSame([], $json['data']['items']);
        self::assertFalse($json['meta']['complete']);
        self::assertSame('unread-counter-schema-unsupported', $json['meta']['reason']);
        self::assertSame('partial-result', $json['error']['code']);
        self::assertFalse($json['meta']['unreadState']['countersComplete']);
    }

    public function testManualSourceUnavailableKeepsTheCounterWithNullMarker(): void
    {
        $this->tasks = [$this->task(60, 10872)];
        $this->counters = ['TASKS_TASK' => [10872 => 2], 'CHAT_MUTED' => []];
        [$run] = $this->listRun(['task:list', '--unread']);
        self::assertSame(3, $run->status, $run->stdout . $run->stderr);
        $json = $run->json();
        self::assertSame(['chatId' => 10872, 'counter' => 2, 'manuallyMarkedUnread' => null], $json['data']['items'][0]['chatState']);
        self::assertSame('manual-unread-state-unavailable', $json['meta']['reason']);
        self::assertFalse($json['meta']['unreadState']['manualMarksComplete']);
    }

    public function testConflictDropsOnlyTheConflictingTask(): void
    {
        $this->tasks = [$this->task(60, 10872), $this->task(59, 10873)];
        $this->counters = ['TASKS_TASK' => [10872 => 1, 10873 => 2], 'CHAT_MUTED' => [10872 => 4], 'TASKS_TASK_UNREAD' => [], 'CHAT' => []];
        [$run] = $this->listRun(['task:list', '--unread']);
        self::assertSame(3, $run->status, $run->stdout . $run->stderr);
        self::assertSame([59], array_column($run->json()['data']['items'], 'id'));
        self::assertSame('unread-counter-conflict', $run->json()['meta']['reason']);
    }

    public function testCompleteEmptySnapshotIsASuccessfulEmptyResult(): void
    {
        $this->tasks = [$this->task(60, 10872)];
        [$run, $api] = $this->listRun(['task:list', '--unread']);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame([], $run->json()['data']['items']);
        self::assertTrue($run->json()['meta']['complete']);
        self::assertSame(['im.counters.get', 'tasks.task.list'], $this->methods($api));
    }

    public function testStrictPolicyRejectsUnreadBeforeAnyRequest(): void
    {
        [$run, $api] = $this->listRun(['--api-policy', 'strict-rest3', 'task:list', '--unread']);
        self::assertSame(4, $run->status, $run->stdout . $run->stderr);
        self::assertSame('policy-denied', $run->json()['error']['code']);
        self::assertSame([], $api->calls);
    }

    public static function otherCommands(): iterable
    {
        yield 'find' => [['task:find', '--title', 'x', '--unread']];
        yield 'my' => [['task:my', '--unread']];
    }

    #[DataProvider('otherCommands')]
    public function testOtherTaskListingsDoNotAcceptTheFlag(array $argv): void
    {
        [$run, $api] = $this->listRun($argv);
        self::assertSame(2, $run->status, $run->stdout . $run->stderr);
        self::assertSame([], $api->calls);
    }

    public function testPlainColumnsAreUnchangedAndHumanShowsTheCounter(): void
    {
        $this->tasks = [$this->task(60, 10872)];
        $this->counters = ['TASKS_TASK' => [10872 => 3], 'CHAT_MUTED' => [], 'TASKS_TASK_UNREAD' => [], 'CHAT' => []];
        [$plain] = $this->listRun(['task:list', '--unread'], '--plain');
        self::assertSame(0, $plain->status, $plain->stdout . $plain->stderr);
        self::assertSame("60\tЗадача 60\t1\t42\tpending\t\n", $plain->stdout);
        self::assertStringContainsString('im.counters.get', $plain->stderr);

        [$human] = $this->listRun(['task:list', '--unread'], '');
        self::assertSame(0, $human->status, $human->stdout . $human->stderr);
        self::assertStringContainsString('3 (ручная метка: нет)', $human->stdout);
        self::assertStringContainsString('не число упоминаний', $human->stdout . $human->stderr);
    }

    private function chatPortal(array $messages): \Closure
    {
        return static fn (string $method): ApiResponse => match ($method) {
            'tasks.task.get' => new ApiResponse(['item' => ['id' => 123, 'chat' => ['id' => 456, 'entityId' => 123, 'entityType' => 'TASKS_TASK']]]),
            'im.dialog.get' => new ApiResponse(['id' => 456, 'last_message_id' => 903]),
            'im.dialog.messages.get' => new ApiResponse(['chat_id' => 456, 'messages' => $messages]),
            default => throw new \LogicException('Unexpected ' . $method),
        };
    }

    private function chatRun(array $messages, string $format): ConsoleRun
    {
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), new FakeApiTransport($this->chatPortal($messages)), new SpyConnectionResolver());
        return ConsoleHarness::run($app, [...($format === '' ? [] : [$format]), 'task:chat:list', '123', '--no-call-details']);
    }

    private function message(int $id, mixed $unread, bool $withFlag = true): array
    {
        return ['id' => $id, 'chat_id' => 456, 'author_id' => 2, 'text' => 'm' . $id, 'date' => '2026-11-13T10:00:00+06:00', ...($withFlag ? ['unread' => $unread] : [])];
    }

    public function testChatMessagesKeepTheServerUnreadFlag(): void
    {
        $run = $this->chatRun([$this->message(902, true), $this->message(901, false)], '--json');
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        $json = $run->json();
        self::assertSame([[901, false], [902, true]], array_map(static fn (array $item): array => [$item['id'], $item['unread']], $json['data']['items']));
        self::assertSame(['id', 'createdDate', 'authorId', 'text', 'chatId', 'unread', 'kind'], array_keys($json['data']['items'][0]));
        self::assertTrue($json['meta']['complete']);
    }

    public static function unknownFlags(): iterable
    {
        yield 'missing' => [null, false];
        yield 'null' => [null, true];
        yield 'string' => ['false', true];
    }

    #[DataProvider('unknownFlags')]
    public function testUnknownMessageFlagIsNullAndPartial(mixed $value, bool $withFlag): void
    {
        $run = $this->chatRun([$this->message(902, $value, $withFlag), $this->message(901, false)], '--json');
        self::assertSame(3, $run->status, $run->stdout . $run->stderr);
        $json = $run->json();
        self::assertSame([false, null], array_column($json['data']['items'], 'unread'));
        self::assertSame(['m901', 'm902'], array_column($json['data']['items'], 'text'));
        self::assertFalse($json['meta']['complete']);
        self::assertSame('message-unread-unavailable', $json['meta']['reason']);
        self::assertSame(1, $json['meta']['unreadUnavailable']);
    }

    public function testChatPlainColumnsAreUnchangedAndHumanShowsThreeStates(): void
    {
        $messages = [$this->message(903, null, false), $this->message(902, true), $this->message(901, false)];
        $plain = $this->chatRun($messages, '--plain');
        self::assertSame(3, $plain->status);
        self::assertSame(["901\t2026-11-13T10:00:00+06:00\t2\tm901\ttext\t", "902\t2026-11-13T10:00:00+06:00\t2\tm902\ttext\t", "903\t2026-11-13T10:00:00+06:00\t2\tm903\ttext\t"], explode("\n", rtrim($plain->stdout, "\n")));

        $human = $this->chatRun($messages, '');
        self::assertStringContainsString('unread', $human->stdout);
        foreach (['нет', 'да', 'неизвестно'] as $state) {
            self::assertMatchesRegularExpression('/\|\s*' . $state . '\s*\|/u', $human->stdout);
        }
    }
}
