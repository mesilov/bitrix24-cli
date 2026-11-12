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

final class ChatsFindCommandTest extends TestCase
{
    /** @var list<array{id: int, name: string, type: string, member?: bool}> */
    private array $chats = [];

    /** @var list<array{ID: string, NAME: string, PROJECT: string}> */
    private array $groups = [];

    /** @var list<array{id: int, title: string}> */
    private array $tasks = [];

    /** @var array<string, array<string, mixed>> */
    private array $dialogs = [];

    /** @var array<int, int> task ID => chat ID */
    private array $taskChats = [];

    /** @var array<string, \Closure> */
    private array $failures = [];

    private ?\Closure $chatPage = null;

    private function invoke(array $argv): array
    {
        $fakeApiTransport = new FakeApiTransport($this->portal(...));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, new SpyConnectionResolver());
        $result = ConsoleHarness::run($app, ['--json', ...$argv]);
        return [$result->status, $result->json(), $fakeApiTransport->calls];
    }

    private function portal(string $method, int $version, array $params): ApiResponse
    {
        if (isset($this->failures[$method])) {
            ($this->failures[$method])($params);
        }

        if ($method === 'im.search.chat.list' && $this->chatPage instanceof \Closure) {
            return ($this->chatPage)();
        }

        return match ($method) {
            'im.search.chat.list' => $this->slice(array_values(array_filter($this->chats, static fn (array $chat): bool => mb_stripos($chat['name'], $params['FIND']) !== false || ($chat['member'] ?? false))), $params['OFFSET'], $params['LIMIT']),
            'sonet_group.get' => $this->slice(array_values(array_filter($this->groups, static fn (array $group): bool => mb_stripos($group['NAME'], $params['FILTER']['%NAME']) !== false)), $params['start'], 50),
            'tasks.task.list' => new ApiResponse(['items' => array_slice($this->tasks, $params['pagination']['offset'], $params['pagination']['limit'])]),
            'tasks.task.get' => new ApiResponse(['item' => ['id' => $params['id'], 'chat' => ['id' => $this->taskChats[$params['id']], 'entityId' => $params['id'], 'entityType' => 'TASKS_TASK']]]),
            'im.dialog.get' => isset($this->dialogs[$params['DIALOG_ID']]) ? new ApiResponse($this->dialogs[$params['DIALOG_ID']]) : throw new Failure('api-error', 'Bitrix24 rejected the request.', 1, ['apiErrorCode' => 'ACCESS_ERROR']),
            default => throw new \LogicException('Unexpected ' . $method),
        };
    }

    private function slice(array $rows, int $offset, int $limit): ApiResponse
    {
        $page = array_map(static fn (array $row): array => array_diff_key($row, ['member' => true]), array_slice($rows, $offset, $limit));
        return new ApiResponse($page, $offset + $limit < count($rows) ? $offset + $limit : null, count($rows));
    }

    private function project(int $groupId, int $chatId): void
    {
        $this->dialogs['sg' . $groupId] = ['id' => $chatId, 'dialog_id' => 'sg' . $groupId, 'name' => 'x', 'type' => 'collab', 'entity_type' => 'SONET_GROUP', 'entity_id' => (string) $groupId];
    }

    private function task(int $taskId, string $title, int $chatId): void
    {
        $this->tasks[] = ['id' => $taskId, 'title' => $title];
        $this->taskChats[$taskId] = $chatId;
        $this->dialogs['chat' . $chatId] = ['id' => $chatId, 'dialog_id' => 'chat' . $chatId, 'name' => 'другое имя чата', 'type' => 'tasksTask', 'entity_type' => 'TASKS_TASK', 'entity_id' => (string) $taskId];
    }

    public function testSingleExactGroupChatIsSelectedAndParticipantMatchesAreHidden(): void
    {
        $this->chats = [['id' => 9636, 'name' => 'Команда', 'type' => 'chat'], ['id' => 9700, 'name' => 'Команда поддержки', 'type' => 'chat'], ['id' => 9800, 'name' => 'Совсем другое', 'type' => 'chat', 'member' => true]];
        [$status, $json, $calls] = $this->invoke(['chats:find', '--name', '  Команда ']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(['status' => 'unique', 'target' => 'chat9636', 'exactMatches' => 1], $json['data']['selection']);
        self::assertSame(['chat9636', 'chat9700'], array_column($json['data']['items'], 'target'));
        self::assertSame([true, false], array_column($json['data']['items'], 'writable'));
        self::assertSame(1, $json['meta']['sources']['chat']['participantOnly']);
        self::assertSame(['FIND' => 'Команда', 'OFFSET' => 0, 'LIMIT' => 50], $calls[0]['parameters']);
        self::assertSame(['im.search.chat.list', 'sonet_group.get', 'tasks.task.list'], array_column($calls, 'method'));
        self::assertTrue($json['meta']['complete']);
    }

    public function testProjectAndTaskAreFoundByTheirOwnTitlesAndConfirmed(): void
    {
        $this->groups = [['ID' => '234', 'NAME' => 'Запуск', 'PROJECT' => 'Y']];
        $this->project(234, 10130);
        $this->task(5288, 'Запуск сайта', 9762);
        [$status, $json] = $this->invoke(['chats:find', '--name', 'Запуск']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(['status' => 'unique', 'target' => 'sg234', 'exactMatches' => 1], $json['data']['selection']);
        self::assertSame([
            ['source' => 'project', 'target' => 'sg234', 'name' => 'Запуск', 'exact' => true, 'writable' => true, 'chatId' => 10130, 'reason' => null, 'groupId' => 234, 'project' => true],
            ['source' => 'task', 'target' => 'task:5288', 'name' => 'Запуск сайта', 'exact' => false, 'writable' => false, 'chatId' => null, 'reason' => 'not-exact', 'taskId' => 5288],
        ], $json['data']['items']);

        [$status, $json, $calls] = $this->invoke(['chats:find', '--name', 'Запуск сайта']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame('task:5288', $json['data']['selection']['target']);
        self::assertSame(9762, $json['data']['items'][0]['chatId']);
        self::assertSame(['im.search.chat.list', 'sonet_group.get', 'tasks.task.list', 'tasks.task.get', 'im.dialog.get'], array_column($calls, 'method'));
    }

    public function testExactMatchesInDifferentSourcesAreAmbiguous(): void
    {
        $this->chats = [['id' => 9636, 'name' => 'Релиз', 'type' => 'chat']];
        $this->groups = [['ID' => '234', 'NAME' => 'Релиз', 'PROJECT' => 'Y']];
        $this->project(234, 10130);
        [$status, $json] = $this->invoke(['chats:find', '--name', 'Релиз']);
        self::assertSame(0, $status);
        self::assertSame(['status' => 'ambiguous', 'target' => null, 'exactMatches' => 2], $json['data']['selection']);
    }

    public function testCaseAndNoMatchAreDistinctFromAmbiguity(): void
    {
        $this->chats = [['id' => 9636, 'name' => 'релиз', 'type' => 'chat']];
        [$status, $json] = $this->invoke(['chats:find', '--name', 'Релиз']);
        self::assertSame(0, $status);
        self::assertSame(['status' => 'none', 'target' => null, 'exactMatches' => 0], $json['data']['selection']);
        self::assertSame(['not-exact'], array_column($json['data']['items'], 'reason'));
    }

    public function testUnavailableProjectChatIsListedButNotSelected(): void
    {
        $this->groups = [['ID' => '230', 'NAME' => 'Архив', 'PROJECT' => 'N']];
        [$status, $json] = $this->invoke(['chats:find', '--name', 'Архив']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame('unavailable', $json['data']['selection']['status']);
        self::assertSame(['target' => 'sg230', 'writable' => false, 'reason' => 'chat-unavailable'], array_intersect_key($json['data']['items'][0], array_flip(['target', 'writable', 'reason'])));
    }

    public function testPagesAreWalkedAndBudgetMakesTheSearchIncomplete(): void
    {
        for ($i = 1; $i <= 120; $i++) {
            $this->chats[] = ['id' => 1000 + $i, 'name' => 'Отдел ' . $i, 'type' => 'chat'];
        }

        $this->chats[] = ['id' => 2000, 'name' => 'Отдел', 'type' => 'chat'];
        [$status, $json, $calls] = $this->invoke(['chats:find', '--name', 'Отдел', '--all']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame('chat2000', $json['data']['selection']['target']);
        self::assertSame([0, 50, 100], array_column(array_column(array_slice($calls, 0, 3), 'parameters'), 'OFFSET'));
        self::assertSame(121, $json['meta']['sources']['chat']['scanned']);
        self::assertCount(121, $json['data']['items']);

        [$status, $json, $calls] = $this->invoke(['chats:find', '--name', 'Отдел', '--max-scan', '60']);
        self::assertSame(3, $status);
        self::assertSame('partial-result', $json['error']['code']);
        self::assertSame(['status' => 'incomplete', 'target' => null, 'exactMatches' => 0], $json['data']['selection']);
        self::assertSame('scan-budget-exhausted', $json['meta']['sources']['chat']['reason']);
        self::assertSame([50, 10], array_column(array_column(array_slice($calls, 0, 2), 'parameters'), 'LIMIT'));
        self::assertSame(50, $json['meta']['returned']);
        self::assertTrue($json['meta']['limitApplied']);
    }

    public function testNoProgressAndSourceErrorsAreIncompleteNotNotFound(): void
    {
        $this->chats = [['id' => 9636, 'name' => 'Команда', 'type' => 'chat']];
        $this->failures['sonet_group.get'] = static fn (): never => throw new Failure('api-error', 'Bitrix24 rejected the request.', 1, ['apiErrorCode' => 'ERROR_CORE']);
        [$status, $json] = $this->invoke(['chats:find', '--name', 'Команда']);
        self::assertSame(3, $status, json_encode($json));
        self::assertSame('incomplete', $json['data']['selection']['status']);
        self::assertSame(['complete' => false, 'reason' => 'page-error', 'scanned' => 0, 'matched' => 0, 'error' => ['code' => 'api-error', 'message' => 'Bitrix24 rejected the request.']], $json['meta']['sources']['project']);
        self::assertTrue($json['meta']['sources']['chat']['complete']);

        $this->failures = [];
        $this->chatPage = static fn (): ApiResponse => new ApiResponse([['id' => 1, 'name' => 'Команда', 'type' => 'chat']], 0, 99);
        [$status, $json, $calls] = $this->invoke(['chats:find', '--name', 'Команда']);
        self::assertSame(3, $status, json_encode($json));
        self::assertSame('incomplete', $json['data']['selection']['status']);
        self::assertSame('pagination-no-progress', $json['meta']['sources']['chat']['reason']);
        self::assertCount(1, array_filter($calls, static fn (array $call): bool => $call['method'] === 'im.search.chat.list'));
    }

    public function testUsageAndPolicyFailBeforeApi(): void
    {
        foreach ([['--name', 'К'], [], ['--name', 'Команда', '--limit', '5', '--all'], ['--name', 'Команда', '--max-scan', '0']] as $argv) {
            [$status, $json, $calls] = $this->invoke(['chats:find', ...$argv]);
            self::assertSame([2, 'usage-error', []], [$status, $json['error']['code'], $calls], json_encode($argv));
        }

        [$status, $json, $calls] = $this->invoke(['--api-policy', 'strict-rest3', 'chats:find', '--name', 'Команда']);
        self::assertSame([4, 'policy-denied', []], [$status, $json['error']['code'], $calls]);
    }
}
