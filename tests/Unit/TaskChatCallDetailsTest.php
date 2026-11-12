<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Connection\ScopeManifest;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\MutableDoctorClock;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\TestCase;

/**
 * Call marking and enrichment in task:chat:list and users:presence (#208), offline against fixtures
 * shaped like the portal responses checked on 12.11.2026.
 */
final class TaskChatCallDetailsTest extends TestCase
{
    private const SECRETS = ['SECRET-JWT-VALUE', 'SECRET-CHANNEL-SIGNATURE', 'SECRET-LOG-TOKEN'];

    private array $messages = [];

    private ?\Closure $callDetails = null;

    private ?\Closure $followup = null;

    private array $activity = [];

    private MutableDoctorClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MutableDoctorClock();
        $this->clock->time = new \DateTimeImmutable('2026-11-12T10:40:00+03:00');

        $this->messages = [
            $this->call(5001, 11, '2026-11-12T10:02:51+03:00', 'START', 1001),
            ['id' => 5002, 'chat_id' => 456, 'author_id' => 44, 'date' => '2026-11-12T10:10:00+03:00', 'text' => 'ссылка на документ', 'params' => []],
            $this->call(5003, 22, '2026-11-12T10:29:42+03:00', 'FINISH', 1001),
        ];
        $this->activity = [11 => '2026-11-12T10:35:00+03:00', 22 => '2026-11-12T10:35:00+03:00', 33 => '2026-11-12T10:35:00+03:00', 44 => '2026-11-12T10:35:00+03:00', 55 => '2026-11-12T09:00:00+03:00', 66 => '2026-11-12T10:35:00+03:00', 77 => '2026-11-12T10:15:00+03:00'];
        $this->callDetails = fn (int $callId): array => $this->callResponse($callId);
        $this->followup = static fn (): never => throw new Failure('api-error', 'Bitrix24 rejected the request; verify the target, fields and permissions.', 1, ['apiErrorCode' => 'bitrix_rest_v3_exception_methodnotfoundexception']);
    }

    private function call(int $id, int $author, string $date, string $type, int $callId): array
    {
        $params = ['MESSAGE_TYPE' => $type, 'CALL_ID' => $callId];
        if ($type === 'FINISH') {
            $params += ['INITIATOR_ID' => $author, 'MESSAGE_TEXT' => 'Звонок завершён (26 мин 51 сек)'];
        }

        return ['id' => $id, 'chat_id' => 456, 'author_id' => $author, 'date' => $date, 'text' => $type === 'START' ? 'Начат звонок' : 'Звонок завершён (26 мин 51 сек)', 'params' => ['COMPONENT_ID' => 'CallMessage', 'COMPONENT_PARAMS' => $params, 'NOTIFY' => 'N']];
    }

    private function callResponse(int $callId): array
    {
        return [
            'call' => ['ID' => (string) $callId, 'UUID' => '00000000-0000-4000-8000-00000000' . $callId, 'TYPE' => 2, 'STATE' => 'finished', 'INITIATOR_ID' => 11, 'IS_PUBLIC' => 'Y', 'PUBLIC_ID' => 'public', 'PROVIDER' => 'Bitrix', 'CHAT_ID' => 456, 'START_DATE' => '2026-11-12T10:02:51+03:00', 'END_DATE' => '2026-11-12T10:29:42+03:00', 'LOG_URL' => null, 'RECORD_AUDIO' => false, 'AI_ANALYZE' => false, 'ENDPOINT' => 'wss://SECRET-CHANNEL-SIGNATURE'],
            'connectionData' => ['jwt' => 'SECRET-JWT-VALUE', 'endpoint' => 'wss://example'],
            'users' => [11, 22, 33, 44, 55, 66, 77],
            'userData' => ['11' => ['id' => 11, 'name' => 'Пользователь 11', 'role' => 'ADMIN', 'last_activity_date' => '2026-11-12T10:35:00+03:00'], '22' => ['id' => 22, 'name' => 'Пользователь 22', 'role' => 'MEMBER']],
            'publicChannels' => ['11' => ['signature' => 'SECRET-CHANNEL-SIGNATURE']],
            'logToken' => 'SECRET-LOG-TOKEN',
            'ai' => ['serviceEnabled' => true, 'settingsEnabled' => false, 'recordingMinUsers' => 0, 'agreementAccepted' => false, 'tariffAvailable' => false, 'baasAvailable' => true],
        ];
    }

    private function portal(string $method, int $version, array $params): ApiResponse
    {
        $last = max(array_column($this->messages, 'id'));
        return match ($method) {
            'tasks.task.get' => new ApiResponse(['item' => ['id' => 123, 'chat' => ['id' => 456, 'entityId' => 123, 'entityType' => 'TASKS_TASK']]]),
            'im.dialog.get' => new ApiResponse(['id' => 456, 'dialog_id' => 'chat456', 'name' => 'Команда', 'type' => 'chat', 'entity_type' => '', 'entity_id' => '', 'last_message_id' => $last, 'readed_list' => [
                ['user_id' => 33, 'user_name' => 'Пользователь 33', 'message_id' => 5003, 'date' => '2026-11-12T10:29:43+03:00'],
                ['user_id' => 66, 'user_name' => 'Пользователь 66', 'message_id' => 4990, 'date' => '2026-11-11T18:00:00+03:00'],
            ]]),
            'im.dialog.messages.get' => new ApiResponse(['chat_id' => 456, 'messages' => $this->page($params)]),
            'im.call.get' => new ApiResponse(($this->callDetails)($params['callId'])),
            'call.followup.get' => new ApiResponse(($this->followup)($params['callId'])),
            'im.user.list.get' => new ApiResponse(array_combine(array_map('strval', $params['ID']), array_map(fn (int $id): ?array => isset($this->activity[$id]) ? ['id' => $id, 'name' => 'Пользователь ' . $id, 'status' => 'online', 'idle' => false, 'last_activity_date' => $this->activity[$id], 'absent' => false] : null, $params['ID']))),
            default => throw new \LogicException('Unexpected ' . $method),
        };
    }

    private function page(array $params): array
    {
        $rows = array_filter($this->messages, static fn (array $row): bool => (!isset($params['LAST_ID']) || $row['id'] < $params['LAST_ID']) && (!isset($params['FIRST_ID']) || $row['id'] > $params['FIRST_ID']));
        usort($rows, static fn (array $a, array $b): int => isset($params['FIRST_ID']) ? $a['id'] <=> $b['id'] : $b['id'] <=> $a['id']);
        return array_slice($rows, 0, $params['LIMIT']);
    }

    private function invoke(array $argv, string $mode = '--json'): array
    {
        $fakeApiTransport = new FakeApiTransport($this->portal(...));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, new SpyConnectionResolver(), clock: $this->clock);
        $run = ConsoleHarness::run($app, [$mode, ...$argv]);
        return [$run, $fakeApiTransport->calls];
    }

    public function testCallIsMarkedAndEnrichedWithInferredParticipants(): void
    {
        [$run, $calls] = $this->invoke(['task:chat:list', '123', '--all']);
        $json = $run->json();
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame(['call', 'text', 'call'], array_column($json['data']['items'], 'kind'));
        self::assertSame(['callId' => 1001, 'event' => 'finish'], $json['data']['items'][2]['callRef']);
        self::assertSame('Звонок завершён (26 мин 51 сек)', $json['data']['items'][2]['text']);
        self::assertArrayNotHasKey('params', $json['data']['items'][0]);

        $call = $json['data']['calls'][0];
        self::assertSame([1001, 'ok', 'finished', true, 1611, 'Звонок завершён (26 мин 51 сек)', 11, 22], [$call['callId'], $call['details']['status'], $call['state'], $call['isPublic'], $call['durationSeconds'], $call['durationText'], $call['initiatorId'], $call['finishedById']]);
        self::assertSame(['startId' => 5001, 'finishId' => 5003, 'duringIds' => [5002]], $call['messages']);
        self::assertSame(['ai-settings-disabled', 'ai-agreement-not-accepted', 'ai-tariff-unavailable'], $call['recording']['unavailableReasons']);
        self::assertSame(['serviceEnabled' => true, 'settingsEnabled' => false, 'agreementAccepted' => false, 'tariffAvailable' => false], $call['recording']['ai']);
        self::assertSame([11, 22, 33, 44, 55, 66, 77], array_column($call['participants']['eligible']['users'], 'userId'));
        self::assertSame(['available' => false, 'source' => 'call.followup.get', 'reason' => 'followup-method-unavailable', 'users' => []], $call['participants']['confirmed']);

        $inferred = $call['participants']['inferred'];
        self::assertSame([1, 'fresh', ['public-link-guests-invisible']], [$inferred['rulesVersion'], $inferred['evidenceFreshness'], $inferred['caveats']]);
        $verdicts = array_map(static fn (array $user): array => [$user['verdict'], $user['confidence'], array_column($user['evidence'], 'rule')], array_column($inferred['users'], null, 'userId'));
        self::assertSame([
            11 => ['present', 'medium', ['call-initiator']],
            22 => ['present', 'high', ['finish-author']],
            33 => ['present', 'high', ['read-finish-at-end']],
            44 => ['present', 'medium', ['message-during-call']],
            55 => ['absent', 'high', ['last-activity-before-start']],
            66 => ['absent', 'high', ['last-read-before-start']],
            77 => ['left-early', 'low', ['last-activity-before-finish']],
        ], $verdicts);
        self::assertSame(['present' => 4, 'absent' => 2, 'leftEarly' => 1, 'unknown' => 0], $inferred['summary']);
        self::assertSame(['found' => 1, 'enriched' => 1, 'unavailable' => 0, 'confirmed' => 0], $json['meta']['calls']);
        self::assertSame(['tasks.task.get', 'im.dialog.get', 'im.dialog.messages.get', 'im.dialog.get', 'im.call.get', 'call.followup.get', 'im.user.list.get'], array_column($calls, 'method'));
        self::assertSame(['callId' => 1001], $calls[4]['parameters']);
    }

    public function testConnectionSecretsNeverReachAnyOutput(): void
    {
        foreach (['--json', '--plain', '--no-ansi'] as $mode) {
            [$run] = $this->invoke(['task:chat:list', '123'], $mode);
            self::assertSame(0, $run->status, $run->stdout . $run->stderr);
            foreach (self::SECRETS as $secret) {
                self::assertStringNotContainsString($secret, $run->stdout . $run->stderr, $mode);
            }
        }

        [$run] = $this->invoke(['task:chat:list', '123']);
        self::assertStringNotContainsString('connectionData', $run->stdout);
        self::assertStringNotContainsString('publicChannels', $run->stdout);
        self::assertStringNotContainsString('logToken', $run->stdout);
    }

    public function testConfirmedParticipantsComeFromFollowupAndSkipInference(): void
    {
        $this->followup = static fn (int $callId): array => ['item' => ['callId' => $callId, 'participants' => [['userId' => 22, 'name' => 'Пользователь 22', 'talkedSeconds' => 600], ['userId' => 55, 'name' => 'Пользователь 55', 'talkedSeconds' => 0]]]];
        [$run, $calls] = $this->invoke(['task:chat:list', '123']);
        $call = $run->json()['data']['calls'][0];
        self::assertSame(['available' => true, 'source' => 'call.followup.get', 'reason' => null, 'users' => [['userId' => 22, 'name' => 'Пользователь 22', 'talkedSeconds' => 600], ['userId' => 55, 'name' => 'Пользователь 55', 'talkedSeconds' => 0]]], $call['participants']['confirmed']);
        self::assertSame([11, 33, 44, 66, 77], array_column($call['participants']['inferred']['users'], 'userId'));
        self::assertSame(1, $run->json()['meta']['calls']['confirmed']);
        $followup = array_values(array_filter($calls, static fn (array $call): bool => $call['method'] === 'call.followup.get'))[0];
        self::assertSame([3, ['callId' => 1001, 'select' => ['callId', 'chatId', 'startDate', 'endDate', 'durationSeconds', 'participants', 'outcomes'], 'mentionFormat' => 'none']], [$followup['version'], $followup['parameters']]);
    }

    public function testFollowupFailuresMapToReasons(): void
    {
        foreach ([
            'followup-scope-missing' => new Failure('permission-denied', 'denied', 1, ['apiErrorCode' => 'bitrix_rest_v3_exception_insufficientscopeexception']),
            'followup-not-found' => new Failure('api-error', 'rejected', 1, ['apiErrorCode' => 'bitrix_rest_v3_exception_entitynotfoundexception']),
            'followup-access-denied' => new Failure('permission-denied', 'denied'),
            'followup-error' => new Failure('transport-error', 'failed'),
        ] as $reason => $failure) {
            $this->followup = static fn (): never => throw $failure;
            [$run] = $this->invoke(['task:chat:list', '123']);
            self::assertSame(0, $run->status, $reason);
            self::assertSame($reason, $run->json()['data']['calls'][0]['participants']['confirmed']['reason']);
        }
    }

    public function testCallDetailsFailureKeepsHistoryAndFallsBackToChatMembers(): void
    {
        $this->callDetails = static fn (): never => throw new Failure('api-error', 'rejected', 1, ['apiErrorCode' => 'error_method_not_found']);
        [$run] = $this->invoke(['task:chat:list', '123', '--all']);
        $json = $run->json();
        self::assertSame([0, true, [5001, 5002, 5003]], [$run->status, $json['meta']['complete'], array_column($json['data']['items'], 'id')]);
        $call = $json['data']['calls'][0];
        self::assertSame(['status' => 'unavailable', 'source' => 'im.call.get', 'reason' => 'call-method-unavailable'], $call['details']);
        self::assertSame(['2026-11-12T10:02:51+03:00', '2026-11-12T10:29:42+03:00', 1611, null, null], [$call['startedAt'], $call['endedAt'], $call['durationSeconds'], $call['recording'], $call['uuid']]);
        self::assertSame(['im.dialog.get.readed_list', [33, 66]], [$call['participants']['eligible']['source'], array_column($call['participants']['eligible']['users'], 'userId')]);
        self::assertSame(['found' => 1, 'enriched' => 0, 'unavailable' => 1, 'confirmed' => 0], $json['meta']['calls']);
    }

    public function testCallOfAnotherChatIsNotUsed(): void
    {
        $this->callDetails = fn (int $callId): array => ['call' => ['CHAT_ID' => 999] + $this->callResponse($callId)['call']] + $this->callResponse($callId);
        [$run] = $this->invoke(['task:chat:list', '123']);
        self::assertSame(['unavailable', 'call-chat-mismatch'], [$run->json()['data']['calls'][0]['details']['status'], $run->json()['data']['calls'][0]['details']['reason']]);
    }

    public function testTwoCallsAndStartOutsideTheWindow(): void
    {
        $this->messages[] = $this->call(5004, 33, '2026-11-12T11:00:00+03:00', 'START', 1002);
        $this->messages[] = $this->call(5005, 33, '2026-11-12T11:10:00+03:00', 'FINISH', 1002);
        [$run, $calls] = $this->invoke(['task:chat:list', '123', '--after', '5001', '--all']);
        $json = $run->json();
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame([1001, 1002], array_column($json['data']['calls'], 'callId'));
        self::assertSame([['start' => false, 'finish' => true], ['start' => true, 'finish' => true]], array_column($json['data']['calls'], 'inWindow'));
        self::assertSame('2026-11-12T10:02:51+03:00', $json['data']['calls'][0]['startedAt']);
        self::assertSame('stale', $json['data']['calls'][0]['participants']['inferred']['evidenceFreshness']);
        self::assertNotContains(5001, array_column($json['data']['items'], 'id'));
        self::assertSame(['im.call.get', 'call.followup.get', 'im.call.get', 'im.user.list.get'], array_values(array_filter(array_column($calls, 'method'), static fn (string $method): bool => in_array($method, ['im.call.get', 'call.followup.get', 'im.user.list.get'], true))));
    }

    public function testNoCallDetailsAndHistoryWithoutCallsMakeNoExtraRequests(): void
    {
        [$run, $calls] = $this->invoke(['task:chat:list', '123', '--no-call-details']);
        self::assertSame(0, $run->status);
        self::assertSame(['call', 'text', 'call'], array_column($run->json()['data']['items'], 'kind'));
        self::assertArrayNotHasKey('calls', $run->json()['data']);
        self::assertSame(['found' => 1, 'details' => 'disabled'], $run->json()['meta']['calls']);
        self::assertSame(['tasks.task.get', 'im.dialog.get', 'im.dialog.messages.get'], array_column($calls, 'method'));

        $this->messages = [['id' => 7, 'chat_id' => 456, 'author_id' => 5, 'date' => '2026-11-12T10:00:00+03:00', 'text' => 'привет']];
        [$run, $calls] = $this->invoke(['task:chat:list', '123']);
        self::assertSame([[], ['found' => 0, 'enriched' => 0, 'unavailable' => 0, 'confirmed' => 0]], [$run->json()['data']['calls'], $run->json()['meta']['calls']]);
        self::assertSame(['tasks.task.get', 'im.dialog.get', 'im.dialog.messages.get'], array_column($calls, 'method'));
    }

    public function testPlainAndHumanShowKindAndCallSummary(): void
    {
        [$run] = $this->invoke(['task:chat:list', '123'], '--plain');
        $lines = explode("\n", rtrim($run->stdout, "\n"));
        self::assertSame(['5001', '2026-11-12T10:02:51+03:00', '11', 'Начат звонок', 'call', '1001'], explode("\t", $lines[0]));
        self::assertSame(['text', ''], array_slice(explode("\t", $lines[1]), 4));
        self::assertStringContainsString('Звонок 1001:', $run->stderr);
        self::assertStringContainsString('present 4, absent 2, left-early 1, unknown 0', $run->stderr);
        self::assertStringContainsString('Оценка не является фактом присутствия.', $run->stderr);
    }

    public function testChatHistoryPeriodMarksAndEnrichesCallsWithStartBeforePeriod(): void
    {
        [$run, $calls] = $this->invoke(['chats:messages:list', 'chat456', '--from', '2026-11-12T10:20:00+03:00', '--to', '2026-11-12T11:00:00+03:00']);
        $json = $run->json();
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame([[5003, 'call', ['callId' => 1001, 'event' => 'finish']]], array_map(static fn (array $item): array => [$item['id'], $item['kind'], $item['callRef']], $json['data']['items']));
        self::assertArrayNotHasKey('params', $json['data']['items'][0]);
        $call = $json['data']['calls'][0];
        self::assertSame([['start' => false, 'finish' => true], '2026-11-12T10:02:51+03:00', 1611, 'ok'], [$call['inWindow'], $call['startedAt'], $call['durationSeconds'], $call['details']['status']]);
        self::assertSame(['startId' => null, 'finishId' => 5003, 'duringIds' => []], $call['messages']);
        self::assertSame(['found' => 1, 'enriched' => 1, 'unavailable' => 0, 'confirmed' => 0], $json['meta']['calls']);
        self::assertTrue($json['meta']['complete']);
        self::assertSame(['im.dialog.get', 'im.dialog.messages.get', 'im.dialog.get', 'im.call.get', 'call.followup.get', 'im.user.list.get'], array_column($calls, 'method'));

        [$run, $calls] = $this->invoke(['chats:messages:list', 'chat456', '--from', '2026-11-12T10:00:00+03:00', '--to', '2026-11-12T11:00:00+03:00', '--no-call-details'], '--plain');
        self::assertSame(0, $run->status, $run->stderr);
        self::assertSame(['call', 'text', 'call'], array_map(static fn (string $line): string => explode("\t", $line)[4], explode("\n", rtrim($run->stdout, "\n"))));
        self::assertSame(['im.dialog.get', 'im.dialog.messages.get'], array_column($calls, 'method'));
        foreach (self::SECRETS as $secret) {
            self::assertStringNotContainsString($secret, $run->stdout . $run->stderr);
        }
    }

    public function testStrictPolicyDeniesBeforeAnyRequest(): void
    {
        [$run, $calls] = $this->invoke(['--api-policy', 'strict-rest3', 'task:chat:list', '123']);
        self::assertSame([4, 'policy-denied', []], [$run->status, $run->json()['error']['code'], $calls]);
    }

    public function testScopeManifestKeepsFollowupOptional(): void
    {
        $scopeManifest = new ScopeManifest();
        self::assertSame([['task', 'tasks'], ['im']], $scopeManifest->required([new Route('tasks.task.get', 3), new Route('im.call.get', 1), new Route('im.user.list.get', 1), new Route('call.followup.get', 3)]));
        $affected = $scopeManifest->affectedCommands(['task', 'im', 'user']);
        self::assertArrayNotHasKey('task:chat:list', $affected);
        self::assertArrayNotHasKey('users:presence', $affected);
        self::assertSame(['im'], $scopeManifest->affectedCommands(['task', 'user'])['users:presence']);
    }

    public function testUserPresenceKeepsUnknownIdsAndDropsOnlineFlags(): void
    {
        [$run, $calls] = $this->invoke(['users:presence', '--user-id', '55,11', '--user-id', '999']);
        $json = $run->json();
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame([55, 11, 999], array_column($json['data']['items'], 'id'));
        self::assertSame(['2026-11-12T09:00:00+03:00', true], [$json['data']['items'][0]['lastActivityDate'], $json['data']['items'][0]['found']]);
        self::assertSame(['id' => 999, 'name' => null, 'lastActivityDate' => null, 'absent' => null, 'found' => false, 'observedAt' => '2026-11-12T10:40:00+03:00'], $json['data']['items'][2]);
        self::assertSame([[999], 3, 2], [$json['meta']['notFound'], $json['meta']['requested'], $json['meta']['found']]);
        self::assertStringNotContainsString('online', $run->stdout);
        self::assertSame([['method' => 'im.user.list.get', 'version' => 1, 'parameters' => ['ID' => [55, 11, 999]], 'effect' => 'read']], $calls);

        [$run] = $this->invoke(['users:presence', '--user-id', '55'], '--plain');
        self::assertSame("55\tПользователь 55\t2026-11-12T09:00:00+03:00\tfalse\n", $run->stdout);

        [$run, $calls] = $this->invoke(['users:presence']);
        self::assertSame([2, 'usage-error', []], [$run->status, $run->json()['error']['code'], $calls]);
        [$run, $calls] = $this->invoke(['--api-policy', 'strict-rest3', 'users:presence', '--user-id', '1']);
        self::assertSame([4, 'policy-denied', []], [$run->status, $run->json()['error']['code'], $calls]);
    }
}
