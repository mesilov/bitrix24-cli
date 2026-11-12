<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Bootstrap\CommandCatalog;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChatsResolveCommandTest extends TestCase
{
    private const PROJECT = ['id' => 10130, 'dialog_id' => 'sg234', 'name' => 'Проект', 'type' => 'collab', 'entity_type' => 'SONET_GROUP', 'entity_id' => '234', 'counter' => 3];

    private const TASK_CHAT = ['id' => 9762, 'dialog_id' => 'chat9762', 'name' => 'Чат задачи', 'type' => 'tasksTask', 'entity_type' => 'TASKS_TASK', 'entity_id' => '5288'];

    private const GROUP_CHAT = ['id' => 9636, 'dialog_id' => 'chat9636', 'name' => 'Команда', 'type' => 'chat', 'entity_type' => '', 'entity_id' => ''];

    private function invoke(array $argv, ?\Closure $respond = null): array
    {
        $fakeApiTransport = new FakeApiTransport($respond ?? $this->portal(...));
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        $result = ConsoleHarness::run($app, ['--json', ...$argv]);
        return [$result->status, $result->json(), $fakeApiTransport->calls, $spyConnectionResolver->reads];
    }

    private function portal(string $method, int $version, array $params): ApiResponse
    {
        return match ([$method, $params['DIALOG_ID'] ?? $params['id'] ?? null]) {
            ['im.dialog.get', 'sg234'] => new ApiResponse(self::PROJECT),
            ['im.dialog.get', 'chat9762'] => new ApiResponse(self::TASK_CHAT),
            ['im.dialog.get', 'chat9636'] => new ApiResponse(self::GROUP_CHAT),
            ['tasks.task.get', 5288] => new ApiResponse(['item' => ['id' => 5288, 'chat' => ['id' => 9762, 'entityId' => 5288, 'entityType' => 'TASKS_TASK']]]),
            default => throw new Failure('api-error', 'Bitrix24 rejected the request.', 1, ['apiErrorCode' => 'ACCESS_ERROR']),
        };
    }

    public function testProjectTargetsResolveToProjectChatNotChatWithSameNumber(): void
    {
        foreach (['sg234', 'group:234', 'https://portal.invalid/workgroups/group/234/', 'https://portal.invalid/online/?IM_DIALOG=sg234'] as $target) {
            [$status, $json, $calls] = $this->invoke(['chats:resolve', $target]);
            self::assertSame(0, $status, $target . json_encode($json));
            self::assertSame(['kind' => 'project', 'target' => 'sg234', 'source' => str_starts_with($target, 'https') ? 'url' : 'id', 'chatId' => 10130, 'groupId' => 234, 'taskId' => null, 'messageId' => null], $json['data']['destination']);
            self::assertSame([['method' => 'im.dialog.get', 'version' => 1, 'parameters' => ['DIALOG_ID' => 'sg234'], 'effect' => 'read']], $calls);
            self::assertArrayNotHasKey('counter', $json['data']['dialog']);
            self::assertSame('SONET_GROUP', $json['data']['dialog']['entity_type']);
        }
    }

    public function testTaskTargetVerifiesTaskChatBindingBothWays(): void
    {
        [$status, $json, $calls] = $this->invoke(['chats:resolve', 'https://portal.invalid/company/personal/user/1/tasks/task/view/5288/']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(['task', 5288, 9762, null], [$json['data']['destination']['kind'], $json['data']['destination']['taskId'], $json['data']['destination']['chatId'], $json['data']['destination']['groupId']]);
        self::assertSame([['tasks.task.get', 3], ['im.dialog.get', 1]], array_map(static fn (array $call): array => [$call['method'], $call['version']], $calls));
        self::assertSame('chat9762', $calls[1]['parameters']['DIALOG_ID']);
    }

    public static function brokenBindings(): iterable
    {
        yield 'task without chat' => [['task:5288'], ['tasks.task.get' => ['item' => ['id' => 5288, 'chat' => null]]]];
        yield 'task chat of another task' => [['task:5288'], ['im.dialog.get' => [...self::TASK_CHAT, 'entity_id' => '5289']]];
        yield 'project chat of another group' => [['sg234'], ['im.dialog.get' => [...self::PROJECT, 'entity_id' => '235']]];
        yield 'project id is not a group chat' => [['sg234'], ['im.dialog.get' => self::GROUP_CHAT]];
        yield 'chat id mismatch' => [['chat9636'], ['im.dialog.get' => [...self::GROUP_CHAT, 'id' => 9637]]];
    }

    #[DataProvider('brokenBindings')]
    public function testUnconfirmedBindingsFailWithoutFallback(array $argv, array $override): void
    {
        [$status, $json, $calls] = $this->invoke(['chats:resolve', ...$argv], fn (string $method, int $version, array $params): ApiResponse => array_key_exists($method, $override) ? new ApiResponse($override[$method]) : $this->portal($method, $version, $params));
        self::assertSame(4, $status, json_encode($json));
        self::assertContains($json['error']['code'], ['resource-binding-unverified', 'message-binding-unverified']);
        self::assertNull($json['data']);
        self::assertNotContains('im.chat.get', array_column($calls, 'method'));
    }

    public function testMessageLinkSelectsChatAndKeepsMessageIdSeparate(): void
    {
        [$status, $json, $calls] = $this->invoke(['chats:resolve', 'https://portal.invalid/online/?IM_DIALOG=chat9636&IM_MESSAGE=19436']);
        self::assertSame(0, $status);
        self::assertSame(9636, $json['data']['destination']['chatId']);
        self::assertSame(19436, $json['data']['destination']['messageId']);
        self::assertSame('chat9636', $calls[0]['parameters']['DIALOG_ID']);
    }

    public function testInaccessibleChatIsExplicitNotEmpty(): void
    {
        [$status, $json] = $this->invoke(['chats:resolve', 'chat999']);
        self::assertSame(1, $status);
        self::assertSame('chat-unavailable', $json['error']['code']);
        self::assertSame(['DIALOG_ID' => 'chat999', 'apiErrorCode' => 'ACCESS_ERROR'], $json['error']['details']);
    }

    public function testForeignPortalIsRejectedBeforeAnyRequest(): void
    {
        [$status, $json, $calls] = $this->invoke(['chats:resolve', 'https://other.example/workgroups/group/234/']);
        self::assertSame(2, $status);
        self::assertSame('foreign-portal', $json['error']['code']);
        self::assertSame(['urlHost' => 'other.example', 'connectionHost' => 'portal.invalid'], $json['error']['details']);
        self::assertSame([], $calls);
    }

    public static function invalidTargets(): iterable
    {
        yield 'missing' => [[]];
        yield 'bare number' => [['234']];
        yield 'personal dialog' => [['https://portal.invalid/online/?IM_DIALOG=15']];
        yield 'conflict' => [['https://portal.invalid/workgroups/group/234/?IM_DIALOG=chat5']];
    }

    #[DataProvider('invalidTargets')]
    public function testInvalidTargetsDoNotReadCredentialsOrCallApi(array $argv): void
    {
        [$status, $json, $calls, $reads] = $this->invoke(['chats:resolve', ...$argv]);
        self::assertSame(2, $status, json_encode($json));
        self::assertSame([], $calls);
        self::assertSame(0, $reads);
    }

    public function testStrictRest3RejectsImRoutesBeforeCredentials(): void
    {
        foreach (['sg234', 'task:5288'] as $target) {
            [$status, $json, $calls, $reads] = $this->invoke(['--api-policy', 'strict-rest3', 'chats:resolve', $target]);
            self::assertSame(4, $status);
            self::assertSame('policy-denied', $json['error']['code']);
            self::assertSame([], $calls);
            self::assertSame(0, $reads);
        }
    }

    public function testRegistrationKeepsTaskChatCommandsAndUsesChatsNamespace(): void
    {
        self::assertSame('read', CommandCatalog::COMMANDS['chats:resolve']['effect']);
        self::assertSame([['tasks.task.get', 3], ['im.dialog.get', 1]], CommandCatalog::ROUTES['chats:resolve']);
        foreach (['task:chat:list', 'task:chat:send', 'task:chat:update', 'task:chat:delete'] as $command) {
            self::assertArrayHasKey($command, CommandCatalog::COMMANDS);
        }

        self::assertSame([], array_filter(array_keys(CommandCatalog::all()), static fn (string $name): bool => str_starts_with($name, 'chat:')));
    }

    public function testPlainAndHumanOutputAndOfflineHelp(): void
    {
        $fakeApiTransport = new FakeApiTransport($this->portal(...));
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        $plain = ConsoleHarness::run($app, ['chats:resolve', 'chat9636', '--plain']);
        self::assertSame(0, $plain->status);
        self::assertStringContainsString("destination.chatId\t9636", $plain->stdout);
        self::assertStringContainsString("dialog.name\tКоманда", $plain->stdout);
        $human = ConsoleHarness::run($app, ['chats:resolve', 'sg234', '--no-ansi']);
        self::assertSame(0, $human->status);
        self::assertStringContainsString('SONET_GROUP', $human->stdout);
        $calls = count($fakeApiTransport->calls);
        $reads = $spyConnectionResolver->reads;
        foreach ([['chats:resolve', '--help'], ['list', 'chats']] as $argv) {
            self::assertSame(0, ConsoleHarness::run($app, $argv)->status);
        }

        self::assertCount($calls, $fakeApiTransport->calls);
        self::assertSame($reads, $spyConnectionResolver->reads);
    }
}
