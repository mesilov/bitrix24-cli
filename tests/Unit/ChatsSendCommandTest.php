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

final class ChatsSendCommandTest extends TestCase
{
    private const PROJECT = ['id' => 10130, 'dialog_id' => 'sg234', 'name' => 'Проект', 'type' => 'collab', 'entity_type' => 'SONET_GROUP', 'entity_id' => '234'];

    /** @var list<array{id: int, name: string, type: string}> */
    private array $chats = [];

    private ?\Closure $add = null;

    private ?\Closure $readback = null;

    private function invoke(array $argv): array
    {
        $fakeApiTransport = new FakeApiTransport($this->portal(...));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, new SpyConnectionResolver());
        $result = ConsoleHarness::run($app, ['--json', ...$argv]);
        return [$result->status, $result->json(), $fakeApiTransport->calls];
    }

    private function portal(string $method, int $version, array $params): ApiResponse
    {
        return match ($method) {
            'im.dialog.get' => match ($params['DIALOG_ID']) {
                'sg234' => new ApiResponse(self::PROJECT),
                'chat9636' => new ApiResponse(['id' => 9636, 'dialog_id' => 'chat9636', 'name' => 'Команда', 'type' => 'chat', 'entity_type' => '', 'entity_id' => '']),
                default => throw new Failure('api-error', 'Bitrix24 rejected the request.', 1, ['apiErrorCode' => 'ACCESS_ERROR']),
            },
            'im.message.add' => $this->add instanceof \Closure ? ($this->add)($params) : new ApiResponse([23622]),
            'im.dialog.messages.get' => $this->readback instanceof \Closure ? ($this->readback)($params) : new ApiResponse(['chat_id' => (int) substr($params['DIALOG_ID'], 4), 'messages' => [['id' => $params['LAST_ID'] - 1, 'chat_id' => (int) substr($params['DIALOG_ID'], 4), 'author_id' => 1, 'date' => '2026-11-12T10:00:00+03:00', 'text' => 'x']]]),
            'im.search.chat.list' => new ApiResponse(array_values(array_filter($this->chats, static fn (array $chat): bool => mb_stripos($chat['name'], $params['FIND']) !== false)), null, count($this->chats)),
            'sonet_group.get' => new ApiResponse([]),
            'tasks.task.list' => new ApiResponse(['items' => []]),
            default => throw new \LogicException('Unexpected ' . $method),
        };
    }

    private function methods(array $calls): array
    {
        return array_column($calls, 'method');
    }

    public function testTypedTargetSendsOnceAndReadsTheMessageBack(): void
    {
        [$status, $json, $calls] = $this->invoke(['chats:send', 'sg234', '--text', 'Сегодня меня не будет']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(['im.dialog.get', 'im.message.add', 'im.dialog.messages.get'], $this->methods($calls));
        self::assertSame(['DIALOG_ID' => 'chat10130', 'MESSAGE' => 'Сегодня меня не будет'], $calls[1]['parameters']);
        self::assertSame('write', $calls[1]['effect']);
        self::assertSame(['DIALOG_ID' => 'chat10130', 'LAST_ID' => 23623, 'LIMIT' => 1], $calls[2]['parameters']);
        self::assertSame(['action' => 'send', 'target' => 'sg234', 'chatId' => 10130, 'messageId' => 23622, 'confirmed' => true, 'readback' => 'verified'], $json['data']['operation']);
        self::assertSame(['project', 234], [$json['data']['destination']['kind'], $json['data']['destination']['groupId']]);
        self::assertFalse($json['meta']['outcomeUnknown']);
    }

    public function testNameSendsOnlyToTheSingleExactMatch(): void
    {
        $this->chats = [['id' => 9636, 'name' => 'Команда', 'type' => 'chat'], ['id' => 9700, 'name' => 'Команда поддержки', 'type' => 'chat']];
        [$status, $json, $calls] = $this->invoke(['chats:send', '--name', 'Команда', '--text', 'Привет']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame('chat9636', $json['data']['operation']['target']);
        self::assertSame('name', $json['data']['destination']['source']);
        self::assertSame(1, count(array_filter($calls, static fn (array $call): bool => $call['method'] === 'im.message.add')));
    }

    public function testNameWithoutUniqueMatchNeverWrites(): void
    {
        $cases = [
            [[], 'chat-not-found', 1],
            [[['id' => 9636, 'name' => 'Команда', 'type' => 'chat'], ['id' => 9637, 'name' => 'Команда', 'type' => 'chat']], 'chat-ambiguous', 4],
        ];
        foreach ($cases as [$chats, $code, $exit]) {
            $this->chats = $chats;
            [$status, $json, $calls] = $this->invoke(['chats:send', '--name', 'Команда', '--text', 'Привет']);
            self::assertSame([$exit, $code], [$status, $json['error']['code']], json_encode($json));
            self::assertNotContains('im.message.add', $this->methods($calls));
        }

        self::assertSame(['chat9636', 'chat9637'], array_column($json['error']['details']['candidates'], 'target'));

        $this->chats = [['id' => 9636, 'name' => 'Команда', 'type' => 'chat']];
        [$status, $json, $calls] = $this->invoke(['chats:send', '--name', 'Команда', '--text', 'Привет', '--max-scan', '1']);
        self::assertSame([0, 'chat9636'], [$status, $json['data']['operation']['target']], json_encode($json));
    }

    public function testIncompleteSearchBlocksTheWrite(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->chats[] = ['id' => 9636 + $i, 'name' => 'Команда ' . $i, 'type' => 'chat'];
        }

        $this->chats[] = ['id' => 9700, 'name' => 'Команда', 'type' => 'chat'];
        $fakeApiTransport = new FakeApiTransport(fn (string $method, int $version, array $params): ApiResponse => $method === 'im.search.chat.list'
            ? new ApiResponse(array_slice($this->chats, $params['OFFSET'], $params['LIMIT']), $params['OFFSET'] + $params['LIMIT'] < 4 ? $params['OFFSET'] + $params['LIMIT'] : null)
            : $this->portal($method, $version, $params));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, new SpyConnectionResolver());
        $result = ConsoleHarness::run($app, ['--json', 'chats:send', '--name', 'Команда', '--text', 'Привет', '--max-scan', '2']);
        self::assertSame([4, 'search-incomplete'], [$result->status, $result->json()['error']['code']]);
        self::assertSame('scan-budget-exhausted', $result->json()['error']['details']['sources']['chat']['reason']);
        self::assertNotContains('im.message.add', array_column($fakeApiTransport->calls, 'method'));
    }

    public function testDryRunVerifiesTheDestinationWithoutSending(): void
    {
        [$status, $json, $calls] = $this->invoke(['chats:send', 'chat9636', '--text', 'Черновик', '--dry-run']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(['im.dialog.get'], $this->methods($calls));
        self::assertSame('chat9636 (chat9636)', $json['data']['plan']['target']);
        self::assertSame(['im.message.add', 'im.dialog.messages.get'], array_column($json['data']['plan']['steps'], 'method'));
        self::assertSame(['target' => 'chat9636', 'dialogId' => 'chat9636', 'chatName' => 'Команда', 'text' => 'Черновик'], $json['data']['plan']['steps'][0]['fields']);
    }

    public function testLostResponseIsOutcomeUnknownAndNotRetried(): void
    {
        $this->add = static fn (): never => throw new Failure('transport-error', 'Request failed; inspect the resource before retrying a write.', 1, [], true);
        [$status, $json, $calls] = $this->invoke(['chats:send', 'sg234', '--text', 'Привет']);
        self::assertSame([1, 'transport-error', true], [$status, $json['error']['code'], $json['meta']['outcomeUnknown']]);
        self::assertSame(['target' => 'sg234', 'chatId' => 10130], $json['error']['details']);
        self::assertStringContainsString('chats:messages:list', $json['error']['message']);
        self::assertSame(['im.dialog.get', 'im.message.add'], $this->methods($calls));

        $this->add = static fn (): ApiResponse => new ApiResponse([]);
        [$status, $json] = $this->invoke(['chats:send', 'sg234', '--text', 'Привет']);
        self::assertSame([1, 'api-error', true], [$status, $json['error']['code'], $json['meta']['outcomeUnknown']]);
    }

    public function testAccessErrorIsNotSentAndNotUnknown(): void
    {
        $this->add = static fn (): never => throw new Failure('api-error', 'Bitrix24 rejected the request.', 1, ['apiErrorCode' => 'ACCESS_ERROR']);
        [$status, $json] = $this->invoke(['chats:send', 'sg234', '--text', 'Привет']);
        self::assertSame([1, 'chat-unavailable', false], [$status, $json['error']['code'], $json['meta']['outcomeUnknown']]);

        [$status, $json, $calls] = $this->invoke(['chats:send', 'chat1', '--text', 'Привет']);
        self::assertSame([1, 'chat-unavailable'], [$status, $json['error']['code']]);
        self::assertSame(['im.dialog.get'], $this->methods($calls));
    }

    public function testUnverifiedReadbackKeepsTheMessageIdAsPartial(): void
    {
        $this->readback = static fn (array $params): ApiResponse => new ApiResponse(['chat_id' => 10130, 'messages' => [['id' => 23600, 'chat_id' => 10130, 'author_id' => 1, 'date' => '2026-11-12T10:00:00+03:00', 'text' => 'older']]]);
        [$status, $json] = $this->invoke(['chats:send', 'sg234', '--text', 'Привет']);
        self::assertSame([3, 'partial-result'], [$status, $json['error']['code']]);
        self::assertSame([23622, true, 'missing'], [$json['data']['operation']['messageId'], $json['data']['operation']['confirmed'], $json['data']['operation']['readback']]);

        $this->readback = static fn (): never => throw new Failure('transport-error', 'Request failed.');
        [$status, $json, $calls] = $this->invoke(['chats:send', 'sg234', '--text', 'Привет']);
        self::assertSame([3, 'failed'], [$status, $json['data']['readback']['status']]);
        self::assertSame(1, count(array_filter($calls, static fn (array $call): bool => $call['method'] === 'im.message.add')));
    }

    public function testTextFileIsSentVerbatim(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'b24cli-send');
        file_put_contents($file, "Строка 1\nСтрока 2");
        try {
            [$status, $json, $calls] = $this->invoke(['chats:send', 'sg234', '--text-file', $file]);
        } finally {
            unlink($file);
        }

        self::assertSame(0, $status, json_encode($json));
        self::assertSame("Строка 1\nСтрока 2", $calls[1]['parameters']['MESSAGE']);
    }

    public function testUsageForeignPortalAndPolicyFailBeforeApi(): void
    {
        foreach ([
            ['sg234', '--name', 'Команда', '--text', 'x'],
            ['--text', 'x'],
            ['sg234'],
            ['sg234', '--text', '   '],
            ['sg234', '--text', 'x', '--text-file', '-'],
            ['sg234', '--text', 'x', '--max-scan', '5'],
            ['--name', 'К', '--text', 'x'],
            ['234', '--text', 'x'],
        ] as $argv) {
            [$status, $json, $calls] = $this->invoke(['chats:send', ...$argv]);
            self::assertSame([2, 'usage-error', []], [$status, $json['error']['code'], $calls], json_encode($argv));
        }

        [$status, $json, $calls] = $this->invoke(['chats:send', 'https://other-portal.invalid/workgroups/group/234/', '--text', 'x']);
        self::assertSame([2, 'foreign-portal', []], [$status, $json['error']['code'], $calls]);

        [$status, $json, $calls] = $this->invoke(['--api-policy', 'strict-rest3', 'chats:send', 'sg234', '--text', 'x']);
        self::assertSame([4, 'policy-denied', []], [$status, $json['error']['code'], $calls]);
    }
}
