<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Fake chat 500 (project sg7): IDs 1..N, one message per hour from 2026-11-01T00:00Z unless overridden. */
final class ChatsMessagesListCommandTest extends TestCase
{
    private const DAY = ['--from', '2026-11-02T00:00:00Z', '--to', '2026-11-03T00:00:00Z'];

    /** @var array<int, array<string, mixed>> */
    private array $messages = [];

    private int $unreadId = 1;

    private function history(int $count): void
    {
        $this->messages = [];
        for ($id = 1; $id <= $count; $id++) {
            $this->messages[$id] = ['id' => $id, 'chat_id' => 500, 'author_id' => $id % 3 === 0 ? 0 : 1 + $id % 2, 'date' => gmdate('Y-m-d\TH:i:s+00:00', strtotime('2026-11-01T00:00:00Z') + 3600 * ($id - 1)), 'text' => 'm' . $id];
        }
    }

    private function portal(string $method, int $version, array $params): ApiResponse
    {
        if ($method === 'im.dialog.get') {
            return $params['DIALOG_ID'] === 'sg7'
                ? new ApiResponse(['id' => 500, 'dialog_id' => 'sg7', 'name' => 'Проект', 'type' => 'collab', 'entity_type' => 'SONET_GROUP', 'entity_id' => '7', 'last_message_id' => $this->messages === [] ? 0 : max(array_keys($this->messages))])
                : throw new Failure('api-error', 'Rejected.', 1, ['apiErrorCode' => 'ACCESS_ERROR']);
        }

        // Mimics the API: without LAST_ID it returns the window of the first unread message.
        $before = $params['LAST_ID'] ?? $this->unreadId + $params['LIMIT'];
        $rows = array_values(array_filter($this->messages, static fn (array $row): bool => $row['id'] < $before));
        usort($rows, static fn (array $a, array $b): int => $b['id'] <=> $a['id']);
        return new ApiResponse(['chat_id' => 500, 'messages' => array_slice($rows, 0, $params['LIMIT']), 'users' => [['id' => 1, 'name' => 'Анна'], ['id' => 2, 'name' => 'Борис']]]);
    }

    private function invoke(array $argv, ?\Closure $respond = null): array
    {
        $fakeApiTransport = new FakeApiTransport($respond ?? $this->portal(...));
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        $result = ConsoleHarness::run($app, ['--json', 'chats:messages:list', ...$argv]);
        return [$result->status, $result->json(), $fakeApiTransport->calls, $spyConnectionResolver->reads];
    }

    private function pageCalls(array $calls): array
    {
        return array_values(array_map(static fn (array $call): array => $call['parameters'], array_filter($calls, static fn (array $call): bool => $call['method'] === 'im.dialog.messages.get')));
    }

    public function testDayAcrossSeveralPagesStartsFromLatestNotUnreadWindow(): void
    {
        $this->history(120);
        $this->unreadId = 3;
        [$status, $json, $calls] = $this->invoke(['sg7', ...self::DAY, '--timezone', 'UTC']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(range(25, 48), array_column($json['data']['items'], 'id'));
        self::assertSame(['DIALOG_ID' => 'chat500', 'LAST_ID' => 121, 'LIMIT' => 50], $this->pageCalls($calls)[0]);
        self::assertSame([121, 71, 21], array_column($this->pageCalls($calls), 'LAST_ID'));
        self::assertSame(['complete' => true, 'reason' => null, 'scanned' => 120, 'matched' => 24, 'returned' => 24, 'pages' => 3], array_intersect_key($json['meta'], array_flip(['complete', 'reason', 'scanned', 'matched', 'returned', 'pages'])));
        self::assertSame([['id' => 1, 'name' => 'Анна'], ['id' => 2, 'name' => 'Борис']], $json['data']['authors']);
        self::assertSame(['kind' => 'exact', 'from' => '2026-11-02T00:00:00+00:00', 'to' => '2026-11-03T00:00:00+00:00', 'timezone' => 'UTC'], array_intersect_key($json['data']['period'], array_flip(['kind', 'from', 'to', 'timezone'])));
        self::assertSame(['kind' => 'project', 'chatId' => 500, 'groupId' => 7], array_intersect_key($json['data']['destination'], array_flip(['kind', 'chatId', 'groupId'])));
    }

    public function testBoundaryMessagesFromIncludedToExcluded(): void
    {
        $this->history(60);
        [$status, $json] = $this->invoke(['sg7', '--from', '2026-11-01T10:00:00Z', '--to', '2026-11-01T12:00:00Z']);
        self::assertSame(0, $status);
        self::assertSame([11, 12], array_column($json['data']['items'], 'id'));
    }

    public function testEmptyDayIsCompleteOnlyAfterHistoryStart(): void
    {
        $this->history(30);
        [$status, $json] = $this->invoke(['sg7', '--from', '2026-12-01T00:00:00Z', '--to', '2026-12-02T00:00:00Z']);
        self::assertSame(0, $status);
        self::assertSame([], $json['data']['items']);
        self::assertTrue($json['meta']['complete']);
        $this->history(0);
        [$status, $json] = $this->invoke(['sg7', ...self::DAY]);
        self::assertSame(0, $status);
        self::assertSame(0, $json['meta']['scanned']);
    }

    public function testExactBudgetBoundaryIsPartialAndOneMoreIsComplete(): void
    {
        $this->history(100);
        [$status, $json] = $this->invoke(['sg7', ...self::DAY, '--max-messages', '100']);
        self::assertSame(3, $status);
        self::assertSame('partial-result', $json['error']['code']);
        self::assertSame(['complete' => false, 'reason' => 'message-budget-exhausted', 'scanned' => 100], array_intersect_key($json['meta'], array_flip(['complete', 'reason', 'scanned'])));
        self::assertCount(24, $json['data']['items']);
        [$status, $json, $calls] = $this->invoke(['sg7', ...self::DAY, '--max-messages', '101']);
        self::assertSame(0, $status);
        self::assertSame([50, 50, 1], array_column($this->pageCalls($calls), 'LIMIT'));
    }

    public function testBudgetBelowPeriodReportsWhetherPeriodStartWasPassed(): void
    {
        $this->history(120);
        [$status, $json] = $this->invoke(['sg7', ...self::DAY, '--max-messages', '60']);
        self::assertSame(3, $status);
        self::assertFalse($json['meta']['periodStartPassed']);
        self::assertSame([], $json['data']['items']);
        [$status, $json] = $this->invoke(['sg7', ...self::DAY, '--max-messages', '100']);
        self::assertSame(3, $status);
        self::assertTrue($json['meta']['periodStartPassed']);
    }

    public function testOldTimestampDoesNotStopTheWalk(): void
    {
        $this->history(120);
        $this->messages[110]['date'] = '2026-10-01T00:00:00+00:00';
        $this->messages[5]['date'] = '2026-11-02T05:30:00+00:00';
        [$status, $json] = $this->invoke(['sg7', ...self::DAY]);
        self::assertSame(0, $status);
        self::assertContains(5, array_column($json['data']['items'], 'id'));
        self::assertSame([30, 5, 31], array_column(array_slice($json['data']['items'], 5, 3), 'id'), 'Items are ordered by time, then ID.');
    }

    public function testOutputLimitKeepsEarliestMessagesAndIsPartial(): void
    {
        $this->history(120);
        [$status, $json] = $this->invoke(['sg7', ...self::DAY, '--limit', '5']);
        self::assertSame(3, $status);
        self::assertSame([25, 26, 27, 28, 29], array_column($json['data']['items'], 'id'));
        self::assertSame(['output-limit', 24, 5], [$json['meta']['reason'], $json['meta']['matched'], $json['meta']['returned']]);
    }

    public static function brokenPages(): iterable
    {
        yield 'later page error' => ['error', 'page-error'];
        yield 'no progress' => ['repeat', 'pagination-no-progress'];
        yield 'conflicting duplicate' => ['conflict', 'conflicting-rows'];
    }

    #[DataProvider('brokenPages')]
    public function testBrokenLaterPagesKeepKnownMessagesAsPartial(string $mode, string $reason): void
    {
        $this->history(120);
        [$status, $json] = $this->invoke(['sg7', '--from', '2026-11-05T00:00:00Z', '--to', '2026-11-06T00:00:00Z'], function (string $method, int $version, array $params) use ($mode): ApiResponse {
            $response = $this->portal($method, $version, $params);
            if ($method !== 'im.dialog.messages.get' || $params['LAST_ID'] === 121) {
                if ($mode === 'conflict' && $method === 'im.dialog.messages.get') {
                    $rows = $response->result['messages'];
                    $rows[] = [...$rows[0], 'text' => 'изменено'];
                    return new ApiResponse([...$response->result, 'messages' => $rows]);
                }

                return $response;
            }

            return match ($mode) {
                'error' => throw new Failure('api-error', 'Later page failed.'),
                default => $this->portal($method, $version, [...$params, 'LAST_ID' => 121]),
            };
        });
        self::assertSame(3, $status, json_encode($json));
        self::assertSame($reason, $json['meta']['reason']);
        self::assertFalse($json['meta']['complete']);
        if ($mode !== 'conflict') {
            self::assertSame(range(97, 120), array_column($json['data']['items'], 'id'));
        }
    }

    public function testFirstPageFailureAndForeignRowsAreErrors(): void
    {
        $this->history(10);
        [$status, $json] = $this->invoke(['sg7', ...self::DAY], fn (string $method, int $version, array $params): ApiResponse => $method === 'im.dialog.messages.get' ? throw new Failure('permission-denied', 'Denied.') : $this->portal($method, $version, $params));
        self::assertSame(1, $status);
        self::assertSame('permission-denied', $json['error']['code']);
        [$status, $json] = $this->invoke(['sg7', ...self::DAY], fn (string $method, int $version, array $params): ApiResponse => $method === 'im.dialog.messages.get' ? new ApiResponse([...$this->portal($method, $version, $params)->result, 'chat_id' => 501]) : $this->portal($method, $version, $params));
        self::assertSame(4, $status);
        self::assertSame('message-binding-unverified', $json['error']['code']);
        [$status, $json] = $this->invoke(['chat9', ...self::DAY]);
        self::assertSame(1, $status);
        self::assertSame('chat-unavailable', $json['error']['code']);
    }

    public static function invalidInputs(): iterable
    {
        yield 'no period' => [['sg7']];
        yield 'period without timezone' => [['sg7', '--period', 'yesterday']];
        yield 'period and range' => [['sg7', '--period', 'yesterday', '--timezone', 'UTC', ...self::DAY]];
        yield 'bare number' => [['7', ...self::DAY]];
        yield 'zero limit' => [['sg7', ...self::DAY, '--limit', '0']];
        yield 'zero budget' => [['sg7', ...self::DAY, '--max-messages', '0']];
    }

    #[DataProvider('invalidInputs')]
    public function testInvalidInputsDoNotReadCredentialsOrCallApi(array $argv): void
    {
        [$status, $json, $calls, $reads] = $this->invoke($argv);
        self::assertSame(2, $status, json_encode($json));
        self::assertSame('usage-error', $json['error']['code']);
        self::assertSame([], $calls);
        self::assertSame(0, $reads);
    }

    public function testStrictRest3AndForeignPortalStopBeforeRequests(): void
    {
        [$status, $json, $calls, $reads] = $this->invoke(['--api-policy', 'strict-rest3', 'sg7', ...self::DAY]);
        self::assertSame([4, 'policy-denied', [], 0], [$status, $json['error']['code'], $calls, $reads]);
        [$status, $json, $calls] = $this->invoke(['https://other.example/workgroups/group/7/', ...self::DAY]);
        self::assertSame([2, 'foreign-portal', []], [$status, $json['error']['code'], $calls]);
    }

    public function testPlainOutputListsMessages(): void
    {
        $this->history(60);
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), new FakeApiTransport($this->portal(...)), new SpyConnectionResolver());
        $plain = ConsoleHarness::run($app, ['chats:messages:list', 'sg7', '--from', '2026-11-01T10:00:00Z', '--to', '2026-11-01T11:00:00Z', '--plain']);
        self::assertSame(0, $plain->status);
        self::assertSame("11\t2026-11-01T10:00:00+00:00\t2\tm11", trim($plain->stdout));
    }
}
