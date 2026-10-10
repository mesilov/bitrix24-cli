<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Offline;

use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Bootstrap\CommandCatalog;
use Bitrix24\CLI\Infrastructure\Connection\ConnectionResolver;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\FixturePortal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;

final class CommandContractsTest extends TestCase
{
    public static function cases(): iterable
    {
        yield 'add' => [['task:add', '--title', 'New task', '--creator', '1', '--responsible', '2'], 'tasks.task.add', 3, ['fields' => ['title' => 'New task', 'creatorId' => 1, 'responsibleId' => 2]]];
        yield 'show' => [['task:show', '123'], 'tasks.task.get', 3, null];
        yield 'list' => [['task:list', '--responsible', '2'], 'tasks.task.list', 3, null];
        yield 'find' => [['task:find', '--title', 'ДОГОВОР'], 'tasks.task.list', 3, null];
        yield 'update' => [['task:update', '123', '--title', 'Changed'], 'tasks.task.update', 3, ['id' => 123, 'fields' => ['title' => 'Changed']]];
        yield 'delete' => [['task:delete', '123', '--force'], 'tasks.task.delete', 3, ['id' => 123]];
        yield 'fields' => [['task:fields:list'], 'tasks.task.field.list', 3, []];
        yield 'access' => [['task:access:show', '123'], 'tasks.task.access.get', 3, ['id' => 123]];
        yield 'assign' => [['task:assign', '123', '--responsible', '3'], 'tasks.task.update', 3, ['id' => 123, 'fields' => ['responsibleId' => 3]]];
        yield 'deadline' => [['task:deadline:set', '123', '--at', '2026-12-01T12:00:00+06:00'], 'tasks.task.update', 3, ['id' => 123, 'fields' => ['deadline' => '2026-12-01T12:00:00+06:00']]];
        yield 'chat list' => [['task:chat:list', '123'], 'im.dialog.messages.get', 1, ['DIALOG_ID' => 'chat456', 'LIMIT' => 20]];
        yield 'chat send' => [['task:chat:send', '123', '--text', 'Hello'], 'tasks.task.chat.message.send', 3, ['fields' => ['taskId' => 123, 'text' => 'Hello']]];
        yield 'chat update' => [['task:chat:update', '123', '--message', '900', '--text', 'Changed'], 'im.message.update', 1, ['MESSAGE_ID' => 900, 'MESSAGE' => 'Changed']];
        yield 'chat delete' => [['task:chat:delete', '123', '--message', '900', '--force'], 'im.message.delete', 1, ['MESSAGE_ID' => 900]];
        yield 'file attach' => [['task:file:attach', '123', '--file-id', '50'], 'tasks.task.file.attach', 3, ['taskId' => 123, 'fileIds' => [50]]];
        yield 'time context' => [['task:time:show', '123'], 'tasks.task.get', 3, ['id' => 123, 'select' => ['id', 'elapsedTime']]];
        yield 'time add' => [['task:time:add', '123', '--seconds', '60'], 'task.elapseditem.add', 1, ['TASKID' => 123, 'ARFIELDS' => ['SECONDS' => 60]]];
        yield 'time list' => [['task:time:list', '123'], 'task.elapseditem.getlist', 1, null];
        yield 'time update' => [['task:time:update', '123', '--entry', '30', '--seconds', '120'], 'task.elapseditem.update', 1, ['TASKID' => 123, 'ITEMID' => 30, 'ARFIELDS' => ['SECONDS' => 120]]];
        yield 'time delete' => [['task:time:delete', '123', '--entry', '30', '--force'], 'task.elapseditem.delete', 1, ['TASKID' => 123, 'ITEMID' => 30]];
        yield 'checklist add' => [['task:checklist:add', '123', '--title', 'Root'], 'task.checklistitem.add', 1, ['TASKID' => 123, 'FIELDS' => ['TITLE' => 'Root', 'PARENT_ID' => 0, 'IS_COMPLETE' => 'N']]];
        yield 'checklist list' => [['task:checklist:list', '123'], 'task.checklistitem.getlist', 1, null];
        yield 'item add' => [['task:checklist:item:add', '123', '--checklist', '10', '--parent', '11', '--title', 'Nested'], 'task.checklistitem.add', 1, ['TASKID' => 123, 'FIELDS' => ['TITLE' => 'Nested', 'PARENT_ID' => 11, 'IS_COMPLETE' => 'N']]];
        yield 'item list' => [['task:checklist:item:list', '123', '--checklist', '10'], 'task.checklistitem.getlist', 1, null];
        yield 'item update' => [['task:checklist:item:update', '123', '--item', '11', '--title', 'Changed'], 'task.checklistitem.update', 1, ['TASKID' => 123, 'ITEMID' => 11, 'FIELDS' => ['TITLE' => 'Changed']]];
        yield 'item complete' => [['task:checklist:item:complete', '123', '--item', '11'], 'task.checklistitem.complete', 1, ['TASKID' => 123, 'ITEMID' => 11]];
        yield 'item renew' => [['task:checklist:item:renew', '123', '--item', '11'], 'task.checklistitem.renew', 1, ['TASKID' => 123, 'ITEMID' => 11]];
        yield 'item delete' => [['task:checklist:item:delete', '123', '--item', '11', '--force'], 'task.checklistitem.delete', 1, ['TASKID' => 123, 'ITEMID' => 11]];
        yield 'participants' => [['task:participants:set', '123', '--clear-auditors'], 'tasks.task.update', 1, ['taskId' => 123, 'fields' => ['AUDITORS' => []]]];
        yield 'history' => [['task:history:list', '123'], 'tasks.task.history.list', 1, ['taskId' => 123, 'start' => 0]];
    }

    #[DataProvider('cases')]
    public function testCommandRoutesThroughContainerAndReturnsOneEnvelope(array $argv, string $method, int $version, ?array $parameters): void
    {
        $fixturePortal = new FixturePortal();
        $fakeApiTransport = new FakeApiTransport($fixturePortal->respond(...));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport);
        $result = ConsoleHarness::run($app, ['--json', ...$argv]);
        self::assertSame(0, $result->status, $result->stdout . $result->stderr);
        $json = $result->json();
        self::assertNull($json['error']);
        self::assertSame(1, $json['meta']['schemaVersion']);
        $calls = array_values(array_filter($fakeApiTransport->calls, static fn (array $call): bool => $call['method'] === $method && $call['version'] === $version));
        self::assertNotEmpty($calls);
        if ($parameters !== null) {
            self::assertEquals($parameters, $calls[array_key_last($calls)]['parameters']);
        }

        if ($argv[0] === 'task:find') {
            self::assertSame([['id' => 123, 'title' => 'Согласовать договор']], $json['data']['items']);
        }

        if ($argv[0] === 'task:chat:send') {
            self::assertNull($json['data']['operation']['resourceId']);
            self::assertTrue($json['data']['operation']['confirmed']);
        }
    }

    public function testInventoryAndHelpReadNoCredentials(): void
    {
        $resolver = new class () implements ConnectionResolver {
            public int $reads = 0;
            public function webhook(): string
            {
                $this->reads++;
                throw new \LogicException('Credential read on offline surface.');
            }
        };
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), null, $resolver);
        $registered = array_keys($app->all('task'));
        sort($registered);
        $expected = array_keys(CommandCatalog::COMMANDS);
        sort($expected);
        self::assertSame($expected, $registered);
        foreach ([['list', 'task'], ['task:add', '--help'], ['--version'], ['completion', 'bash']] as $argv) {
            $result = ConsoleHarness::run($app, $argv);
            self::assertSame(0, $result->status, $result->stdout . $result->stderr);
        }

        self::assertSame(0, $resolver->reads);
    }

    public static function plainCases(): iterable
    {
        foreach (self::cases() as $name => $case) {
            yield $name => [$case[0]];
        }
    }

    #[DataProvider('plainCases')]
    public function testPlainProfileUsesDocumentedHeaderlessColumns(array $argv): void
    {
        $fixturePortal = new FixturePortal();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), new FakeApiTransport($fixturePortal->respond(...)));
        $run = ConsoleHarness::run($app, ['--plain', ...$argv]);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        $columns = match ($argv[0]) {
            'task:show', 'task:access:show', 'task:time:show', 'task:find', 'task:checklist:list' => 2,
            'task:list' => 6,
            'task:fields:list', 'task:time:list' => 5,
            'task:history:list' => 6,
            default => 4,
        };
        foreach (explode("\n", rtrim($run->stdout, "\n")) as $line) {
            self::assertCount($columns, explode("\t", $line));
        }

        self::assertStringNotContainsString("\033[", $run->stdout);
    }
}
