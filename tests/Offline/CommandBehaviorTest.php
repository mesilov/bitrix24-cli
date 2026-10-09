<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Offline;

use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\FixturePortal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommandBehaviorTest extends TestCase
{
    public static function invalidInput(): iterable
    {
        foreach ([
            ['task:show'], ['task:show', '0'], ['task:show', '1.5'], ['task:show', '999999999999999999999'],
            ['task:show', '123', '--typo'], ['task:shwo', '123'], ['task:c', '123'],
            ['task:add', '--title', ' '], ['task:update', '123'],
            ['task:update', '123', '--fields', '[]'], ['task:update', '123', '--fields', '42'],
            ['task:update', '123', '--fields', '{"status":"completed"}'],
            ['task:update', '123', '--fields', '{"title":"x"}', '--title', 'y'],
            ['task:update', '123', '--description', 'x', '--description-file', '-'],
            ['task:deadline:set', '123', '--at', '2026-12-01T12:00:00'],
            ['task:deadline:set', '123', '--at', '2026-02-30T12:00:00Z'],
            ['task:find', '--title', ''], ['task:find', '--title', 'x', '--limit', '1', '--all'],
            ['task:list', '--where', '{"responsibleId":2}', '--responsible', '2'],
            ['task:list', '--select', 'unknown'], ['task:list', '--order', 'chat:asc'],
            ['task:chat:list', '123', '--before', '1', '--after', '2'],
            ['task:chat:send', '123', '--text', ''], ['task:time:add', '123', '--seconds', '0'],
            ['task:time:list', '123', '--params', '{"filter":{"TASK_ID":999}}'],
            ['task:time:list', '123', '--params', '{"select":[["ID"]]}'],
            ['task:time:list', '123', '--params', '{"filter":{"SECONDS":10}}'],
            ['task:history:list', '123', '--params', '{"filter":{"USER_ID":1}}'],
            ['task:participants:set', '123'],
            ['task:participants:set', '123', '--auditor', '2', '--clear-auditors'],
            ['task:file:attach', '123'], ['task:delete', '123'], ['task:delete', '123', '-n'],
            ['task:show', '123', '--json', '--plain'], ['task:show', '123', '--timeout', '0'],
            ['task:show', '123', '--max-scan', '1'],
        ] as $argv) {
            yield implode(' ', $argv) => [$argv];
        }
    }

    #[DataProvider('invalidInput')]
    public function testInvalidInputNeverCallsApi(array $argv): void
    {
        $fakeApiTransport = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected API call.'));
        $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport), ['--json', ...$argv]);
        self::assertSame(2, $run->status, $run->stdout . $run->stderr);
        self::assertSame('usage-error', $run->json()['error']['code']);
        self::assertSame([], $fakeApiTransport->calls);
    }

    public function testStrictPolicyRejectsEveryLegacyPlanBeforeReads(): void
    {
        foreach (CommandContractsTest::cases() as [$argv, $method, $version]) {
            if ($version !== 1) {
                continue;
            }

            $api = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected API call.'));
            $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), ['--json', '--api-policy', 'strict-rest3', ...$argv]);
            self::assertSame(4, $run->status, implode(' ', $argv) . $run->stdout);
            self::assertSame('policy-denied', $run->json()['error']['code']);
            self::assertSame([], $api->calls);
        }
    }

    public function testSparsePatchAndStdinKeepEmptyDescription(): void
    {
        $fixturePortal = new FixturePortal();
        $fakeApiTransport = new FakeApiTransport($fixturePortal->respond(...));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport);
        $run = ConsoleHarness::run($app, ['task:update', '123', '--description-file', '-', '--json'], '');
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame(['id' => 123, 'fields' => ['description' => '']], $fakeApiTransport->calls[0]['parameters']);
        $run = ConsoleHarness::run($app, ['--json', 'task:update', '123', '--title', 'x']);
        self::assertSame(0, $run->status);
        self::assertSame(['id' => 123, 'fields' => ['title' => 'x']], $fakeApiTransport->calls[1]['parameters']);
    }

    public function testAdvancedPatchRequiresEditableMetadataAndDryRunDoesNotWrite(): void
    {
        $fixturePortal = new FixturePortal();
        $fakeApiTransport = new FakeApiTransport($fixturePortal->respond(...));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport);
        $run = ConsoleHarness::run($app, ['task:update', '123', '--fields', '{"title":"x"}', '--dry-run', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame(['tasks.task.field.list'], array_column($fakeApiTransport->calls, 'method'));
        self::assertTrue($run->json()['meta']['dryRun']);
        $run = ConsoleHarness::run($app, ['task:update', '123', '--fields', '{"responsibleId":2}', '--json']);
        self::assertSame(2, $run->status);
        self::assertNotContains('tasks.task.update', array_column($fakeApiTransport->calls, 'method'));
    }

    public function testStdinMayNotFeedBothConfigAndFields(): void
    {
        $fakeApiTransport = new FakeApiTransport(static fn () => throw new \LogicException());
        $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport), ['--config', '-', '--json', 'task:update', '123', '--fields-file', '-'], '{}');
        self::assertSame(2, $run->status, $run->stdout);
        self::assertSame([], $fakeApiTransport->calls);
    }

    public function testAdvancedCreateUsesWritableSchemaThenExplicitFields(): void
    {
        $fields = ['title' => 'fixture', 'creatorId' => 1, 'responsibleId' => 2];
        $fixturePortal = new FixturePortal();
        $fakeApiTransport = new FakeApiTransport(static function ($method, int $version, array $params) use ($fields, $fixturePortal): \Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse {
            if ($method === 'tasks.task.field.list') {
                return new \Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse(['items' => array_map(static fn ($name): array => ['name' => $name, 'type' => $name === 'title' ? 'string' : 'integer', 'editable' => true], array_keys($fields))]);
            }

            return $fixturePortal->respond($method, $version, $params);
        });
        $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport), ['--json', 'task:add', '--fields', json_encode($fields, JSON_THROW_ON_ERROR)]);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame(['tasks.task.field.list', 'tasks.task.add'], array_column($fakeApiTransport->calls, 'method'));
        self::assertSame(['fields' => $fields], $fakeApiTransport->calls[1]['parameters']);
    }

    public function testNativeOptionsSeparatorAndUnambiguousAbbreviation(): void
    {
        $fixturePortal = new FixturePortal();
        $fakeApiTransport = new FakeApiTransport($fixturePortal->respond(...));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport);
        foreach ([['-h'], ['-V'], ['task:add', '-h'], ['--json', 'task:sho', '--', '123'], ['task:show', '123', '--json', '-vv', '-n', '--no-ansi']] as $argv) {
            $run = ConsoleHarness::run($app, $argv);
            self::assertSame(0, $run->status, $run->stdout . $run->stderr);
            self::assertStringNotContainsString("\033[", $run->stdout);
        }

        $run = ConsoleHarness::run($app, ['task:show', '123', '--', '--json']);
        self::assertSame(2, $run->status);
        self::assertSame('', $run->stdout);
    }

    public function testQuietAndSilentDoNotEmitJsonOrSuccess(): void
    {
        $fixturePortal = new FixturePortal();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), new FakeApiTransport($fixturePortal->respond(...)));
        foreach (['-q', '--silent'] as $flag) {
            $run = ConsoleHarness::run($app, [$flag, '--json', 'task:show', '123']);
            self::assertSame(0, $run->status);
            self::assertSame('', $run->stdout);
            $run = ConsoleHarness::run($app, [$flag, '--json', 'task:show', '0']);
            self::assertSame(2, $run->status);
            self::assertSame('', $run->stdout);
            if ($flag === '--silent') {
                self::assertSame('', $run->stderr);
            } else {
                self::assertStringContainsString('usage-error', $run->stderr);
            }
        }
    }

    public function testPlainEscapesControlCharactersAndHumanPreservesMarkupLiterally(): void
    {
        $fixturePortal = new FixturePortal();
        $fixturePortal->task['title'] = "<error>bad</error>\tline\nnext\\";
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), new FakeApiTransport($fixturePortal->respond(...)));
        $run = ConsoleHarness::run($app, ['task:find', '--title', 'bad', '--plain']);
        self::assertSame("123\t<error>bad</error>\\tline\\nnext\\\\\n", $run->stdout);
        self::assertSame('', $run->stderr);
        $run = ConsoleHarness::run($app, ['task:show', '123']);
        self::assertStringContainsString('<error>bad</error>', $run->stdout);
    }
}
