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

final class ChatsAddCommandTest extends TestCase
{
    /** @var list<array{id: int, name: string, type: string}> */
    private array $chats = [];

    private ?\Closure $add = null;

    private ?\Closure $dialog = null;

    /** @var list<int> */
    private array $members = [1, 12, 34];

    private function invoke(array $argv, ?\Closure $portal = null): array
    {
        $fakeApiTransport = new FakeApiTransport($portal ?? $this->portal(...));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, new SpyConnectionResolver());
        $result = ConsoleHarness::run($app, ['--json', ...$argv]);
        return [$result->status, $result->json(), $fakeApiTransport->calls, $result];
    }

    private function portal(string $method, int $version, array $params): ApiResponse
    {
        return match ($method) {
            'im.search.chat.list' => new ApiResponse(array_values(array_filter($this->chats, static fn (array $chat): bool => mb_stripos($chat['name'], $params['FIND']) !== false))),
            'im.chat.add' => $this->add instanceof \Closure ? ($this->add)($params) : new ApiResponse([9901]),
            'im.dialog.get' => $this->dialog instanceof \Closure ? ($this->dialog)($params) : new ApiResponse(['id' => 9901, 'dialog_id' => 'chat9901', 'name' => 'ИИ-новости', 'type' => 'chat', 'owner' => 1, 'entity_type' => '', 'entity_id' => '']),
            'im.dialog.users.list' => new ApiResponse(array_map(static fn (int $id): array => ['id' => $id, 'bot' => false], $this->members)),
            default => throw new \LogicException('Unexpected ' . $method),
        };
    }

    private function methods(array $calls): array
    {
        return array_column($calls, 'method');
    }

    private function callCount(array $calls, string $method): int
    {
        return count(array_filter($calls, static fn (array $call): bool => $call['method'] === $method));
    }

    public function testCreatesOnceWithDocumentedFieldsAndVerifiesTitleAndMembers(): void
    {
        $this->chats = [['id' => 9700, 'name' => 'ИИ-новости компании', 'type' => 'chat'], ['id' => 9701, 'name' => 'ИИ-новости', 'type' => 'private']];
        [$status, $json, $calls] = $this->invoke(['chats:add', '--title', '  ИИ-новости ', '--description', 'Новости про ИИ', '--user-id', '12,34', '--user-id', '12']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(['im.search.chat.list', 'im.chat.add', 'im.dialog.get', 'im.dialog.users.list'], $this->methods($calls));
        self::assertSame(['TITLE' => 'ИИ-новости', 'TYPE' => 'CHAT', 'DESCRIPTION' => 'Новости про ИИ', 'USERS' => [12, 34]], $calls[1]['parameters']);
        self::assertSame('write', $calls[1]['effect']);
        self::assertSame(['DIALOG_ID' => 'chat9901'], $calls[2]['parameters']);
        self::assertSame(['action' => 'create', 'target' => 'chat9901', 'chatId' => 9901, 'confirmed' => true, 'readback' => 'verified'], $json['data']['operation']);
        self::assertSame(['target' => 'chat9901', 'chatId' => 9901, 'title' => 'ИИ-новости', 'type' => 'CHAT', 'description' => 'Новости про ИИ', 'userIds' => [12, 34]], $json['data']['chat']);
        self::assertSame(['requested' => [12, 34], 'present' => [12, 34], 'missing' => [], 'complete' => true], $json['data']['readback']['members']);
        self::assertSame(['status' => 'none', 'scanned' => 2], $json['data']['duplicateCheck']);
        self::assertFalse($json['meta']['outcomeUnknown']);
    }

    public function testMinimalOpenChatOmitsOptionalFields(): void
    {
        [$status, $json, $calls] = $this->invoke(['chats:add', '--title', 'ИИ-новости', '--type', 'OPEN']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(['TITLE' => 'ИИ-новости', 'TYPE' => 'OPEN'], $calls[1]['parameters']);
        self::assertSame(['requested' => [], 'present' => [], 'missing' => [], 'complete' => true], $json['data']['readback']['members']);
    }

    public function testDryRunChecksDuplicatesAndNeverWrites(): void
    {
        [$status, $json, $calls] = $this->invoke(['chats:add', '--title', 'ИИ-новости', '--user-id', '12', '--dry-run']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(['im.search.chat.list'], $this->methods($calls));
        self::assertSame(['im.chat.add', 'im.dialog.get', 'im.dialog.users.list'], array_column($json['data']['plan']['steps'], 'method'));
        self::assertSame(['title' => 'ИИ-новости', 'type' => 'CHAT', 'description' => null, 'userIds' => [12], 'duplicateCheck' => 'none'], $json['data']['plan']['steps'][0]['fields']);
        self::assertSame('new CHAT chat "ИИ-новости"', $json['data']['plan']['target']);
    }

    public function testExistingTitleOrIncompleteSearchBlocksCreation(): void
    {
        $this->chats = [['id' => 9700, 'name' => 'ИИ-новости', 'type' => 'chat'], ['id' => 9800, 'name' => 'ИИ-новости', 'type' => 'open']];
        [$status, $json, $calls] = $this->invoke(['chats:add', '--title', 'ИИ-новости']);
        self::assertSame([4, 'chat-exists'], [$status, $json['error']['code']], json_encode($json));
        self::assertSame(['chat9700', 'chat9800'], array_column($json['error']['details']['candidates'], 'target'));
        self::assertNotContains('im.chat.add', $this->methods($calls));

        $portal = fn (string $method, int $version, array $params): ApiResponse => $method === 'im.search.chat.list'
            ? new ApiResponse([['id' => 9000 + $params['OFFSET'], 'name' => 'ИИ-новости ' . $params['OFFSET'], 'type' => 'chat']], $params['OFFSET'] + 1)
            : $this->portal($method, $version, $params);
        [$status, $json, $calls] = $this->invoke(['chats:add', '--title', 'ИИ-новости', '--max-scan', '2'], $portal);
        self::assertSame([4, 'search-incomplete', 'scan-budget-exhausted'], [$status, $json['error']['code'], $json['error']['details']['source']['reason']], json_encode($json));
        self::assertNotContains('im.chat.add', $this->methods($calls));

        $portal = fn (string $method, int $version, array $params): ApiResponse => $method === 'im.search.chat.list'
            ? throw new Failure('transport-error', 'Request failed.')
            : $this->portal($method, $version, $params);
        [$status, $json, $calls] = $this->invoke(['chats:add', '--title', 'ИИ-новости'], $portal);
        self::assertSame([4, 'search-incomplete', 'page-error'], [$status, $json['error']['code'], $json['error']['details']['source']['reason']], json_encode($json));
        self::assertNotContains('im.chat.add', $this->methods($calls));
    }

    public function testAllowDuplicateTitleSkipsTheSearch(): void
    {
        $this->chats = [['id' => 9700, 'name' => 'ИИ-новости', 'type' => 'chat']];
        [$status, $json, $calls] = $this->invoke(['chats:add', '--title', 'ИИ-новости', '--allow-duplicate-title']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(['im.chat.add', 'im.dialog.get', 'im.dialog.users.list'], $this->methods($calls));
        self::assertSame(['status' => 'skipped', 'scanned' => 0], $json['data']['duplicateCheck']);
    }

    public function testLostResponseIsUnknownAndNeverRetried(): void
    {
        $this->add = static fn (): ApiResponse => new ApiResponse([]);
        [$status, $json, $calls] = $this->invoke(['chats:add', '--title', 'ИИ-новости']);
        self::assertSame([1, 'api-error', true], [$status, $json['error']['code'], $json['meta']['outcomeUnknown']], json_encode($json));
        self::assertStringContainsString('chats:find --name', $json['error']['message']);
        self::assertSame(1, $this->callCount($calls, 'im.chat.add'));
        self::assertNotContains('im.dialog.get', $this->methods($calls));

        $this->add = static fn (): never => throw new Failure('transport-error', 'Request failed; inspect the resource before retrying a write.', 1, [], true);
        [$status, $json, $calls] = $this->invoke(['chats:add', '--title', 'ИИ-новости']);
        self::assertSame([1, 'transport-error', true], [$status, $json['error']['code'], $json['meta']['outcomeUnknown']], json_encode($json));
        self::assertSame(1, $this->callCount($calls, 'im.chat.add'));

        $this->add = static fn (): never => throw new Failure('api-error', 'Bitrix24 rejected the request.', 1, ['apiErrorCode' => 'ACCESS_DENIED']);
        [$status, $json] = $this->invoke(['chats:add', '--title', 'ИИ-новости']);
        self::assertSame([1, 'chat-create-denied', false], [$status, $json['error']['code'], $json['meta']['outcomeUnknown']], json_encode($json));
    }

    public function testUnverifiedReadbackKeepsTheChatIdAsPartial(): void
    {
        $this->members = [1, 12];
        [$status, $json, $calls] = $this->invoke(['chats:add', '--title', 'ИИ-новости', '--user-id', '12,34']);
        self::assertSame([3, 'partial-result', 'members-missing'], [$status, $json['error']['code'], $json['data']['operation']['readback']], json_encode($json));
        self::assertSame([9901, [34]], [$json['data']['operation']['chatId'], $json['data']['readback']['members']['missing']]);
        self::assertSame(1, $this->callCount($calls, 'im.chat.add'));

        $this->members = [1];
        $this->dialog = static fn (): ApiResponse => new ApiResponse(['id' => 9901, 'dialog_id' => 'chat9901', 'name' => 'Чат №5', 'type' => 'chat']);
        [$status, $json] = $this->invoke(['chats:add', '--title', 'ИИ-новости']);
        self::assertSame([3, 'title-mismatch'], [$status, $json['data']['operation']['readback']], json_encode($json));

        $this->dialog = static fn (): ApiResponse => new ApiResponse(['id' => 9901, 'dialog_id' => 'chat9901', 'name' => 'R&amp;D новости', 'type' => 'chat']);
        [$status, $json] = $this->invoke(['chats:add', '--title', 'R&D новости']);
        self::assertSame([0, 'verified'], [$status, $json['data']['operation']['readback']], json_encode($json));

        $this->dialog = static fn (): never => throw new Failure('transport-error', 'Request failed.');
        [$status, $json, $calls] = $this->invoke(['chats:add', '--title', 'ИИ-новости']);
        self::assertSame([3, 'failed', 'transport-error'], [$status, $json['data']['readback']['status'], $json['data']['readback']['error']], json_encode($json));
        self::assertSame(1, $this->callCount($calls, 'im.chat.add'));
    }

    public function testPlainOutputShowsTheCreatedChat(): void
    {
        $fakeApiTransport = new FakeApiTransport($this->portal(...));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, new SpyConnectionResolver());
        $result = ConsoleHarness::run($app, ['--plain', 'chats:add', '--title', 'ИИ-новости']);
        self::assertSame(0, $result->status, $result->stderr);
        self::assertSame("chat9901\t9901\tИИ-новости\tCHAT\ttrue\tverified", trim($result->stdout));
    }

    public function testUsageAndPolicyFailBeforeApi(): void
    {
        foreach ([
            [],
            ['--title', 'И'],
            ['--title', '   '],
            ['--title', str_repeat('я', 256)],
            ['--title', 'ИИ-новости', '--type', 'chat'],
            ['--title', 'ИИ-новости', '--type', 'CHANNEL'],
            ['--title', 'ИИ-новости', '--user-id', '0'],
            ['--title', 'ИИ-новости', '--user-id', '12,abc'],
            ['--title', 'ИИ-новости', '--user-id', implode(',', range(1, 501))],
            ['--title', 'ИИ-новости', '--description', ' '],
            ['--title', 'ИИ-новости', '--allow-duplicate-title', '--max-scan', '5'],
            ['--title', 'ИИ-новости', '--max-scan', '0'],
        ] as $argv) {
            [$status, $json, $calls] = $this->invoke(['chats:add', ...$argv]);
            self::assertSame([2, 'usage-error', []], [$status, $json['error']['code'], $calls], json_encode($argv));
        }

        [$status, $json, $calls] = $this->invoke(['--api-policy', 'strict-rest3', 'chats:add', '--title', 'ИИ-новости']);
        self::assertSame([4, 'policy-denied', []], [$status, $json['error']['code'], $calls]);
    }
}
