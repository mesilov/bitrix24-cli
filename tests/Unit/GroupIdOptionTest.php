<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\FixturePortal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GroupIdOptionTest extends TestCase
{
    private static function commands(): array
    {
        return [
            'add' => ['task:add', '--title', 'Group task', '--creator', '1', '--responsible', '2'],
            'update' => ['task:update', '123', '--title', 'Group task'],
            'list' => ['task:list'],
            'find' => ['task:find', '--title', 'Group task'],
        ];
    }

    public function testExplicitAndOmittedGroupIdKeepSparseRest3Payloads(): void
    {
        foreach ([false, true] as $supplied) {
            foreach (array_slice(self::commands(), 0, 2) as $command => $argv) {
                $portal = new FixturePortal();
                $api = new FakeApiTransport($portal->respond(...));
                $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), ['--json', ...$argv, ...($supplied ? ['--group-id', '42'] : [])]);
                self::assertSame(0, $run->status, $run->stdout);
                self::assertCount(1, $api->calls);
                self::assertSame('tasks.task.' . ($command === 'add' ? 'add' : 'update'), $api->calls[0]['method']);
                self::assertSame(3, $api->calls[0]['version']);
                $fields = $command === 'add' ? ['title' => 'Group task', 'creatorId' => 1, 'responsibleId' => 2] : ['title' => 'Group task'];
                if ($supplied) {
                    $fields['groupId'] = 42;
                }

                if ($command === 'add') {
                    $fields['allowsTimeTracking'] = false;
                }

                self::assertSame($fields, $api->calls[0]['parameters']['fields']);
                if ($command === 'update') {
                    self::assertSame(123, $api->calls[0]['parameters']['id']);
                }
            }
        }
    }

    public function testGroupFilterStaysLocalAcrossPagesAndStructuredInput(): void
    {
        foreach ([[], ['--group-id', '42'], ['--where', '{"groupId":42}'], ['--where-file', '-']] as $filter) {
            $api = new FakeApiTransport(static function (string $method, int $version, array $params): ApiResponse {
                self::assertSame('tasks.task.list', $method);
                self::assertSame(3, $version);
                self::assertContains('groupId', $params['select']);
                self::assertSame([], $params['filter']);
                $items = $params['pagination']['offset'] === 0
                    ? array_map(static fn (int $id): array => ['id' => $id, 'title' => 'Group task', 'groupId' => 7], range(101, 150))
                    : [['id' => 151, 'title' => 'Group task', 'groupId' => 42]];
                return new ApiResponse(['items' => $items]);
            });
            $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), ['--json', 'task:list', '--all', ...$filter], '{"groupId":42}');
            self::assertSame(0, $run->status, $run->stdout);
            self::assertSame($filter === [] ? range(101, 151) : [151], array_column($run->json()['data']['items'], 'id'));
            self::assertTrue($run->json()['meta']['complete']);
            self::assertCount(2, $api->calls);
        }
    }

    public static function rejectedOptions(): iterable
    {
        foreach (self::commands() as $command => $argv) {
            foreach ([['--project', '42'], ['--project', '42', '--group-id', '42'], ['--group-id', '42', '--project', '42']] as $options) {
                yield $command . ' ' . implode(' ', $options) => [[...$argv, ...$options]];
            }

            foreach (['', 'abc', '0', '-1', '1.5', '999999999999999999999'] as $id) {
                yield $command . ' invalid ' . $id => [[...$argv, '--group-id=' . $id]];
            }

            yield $command . ' missing value' => [[...$argv, '--group-id']];
        }

        yield 'advanced flag conflict' => [['task:update', '123', '--fields', '{"groupId":42}', '--group-id', '42']];
        yield 'advanced file conflict' => [['task:add', '--fields-file', '-', '--group-id', '42']];
        yield 'structured filter conflict' => [['task:list', '--where', '{"groupId":42}', '--group-id', '42']];
    }

    #[DataProvider('rejectedOptions')]
    public function testRejectedOptionsNeverCallApi(array $argv): void
    {
        $fakeApiTransport = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected API call.'));
        $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport), ['--json', ...$argv], '{"title":"Group task","creatorId":1,"responsibleId":2,"groupId":42}');
        self::assertSame(2, $run->status, $run->stdout . $run->stderr);
        self::assertSame('usage-error', $run->json()['error']['code']);
        self::assertSame([], $fakeApiTransport->calls);
    }

    public function testAdvancedGroupIdRemainsWritableInInlineAndFileModes(): void
    {
        foreach (['add', 'update'] as $command) {
            foreach (['--fields', '--fields-file'] as $option) {
                $fields = $command === 'add' ? ['title' => 'Group task', 'creatorId' => 1, 'responsibleId' => 2, 'groupId' => 42, 'allowsTimeTracking' => false] : ['groupId' => 42];
                $portal = new FixturePortal();
                $api = new FakeApiTransport(static function (string $method, int $version, array $params) use ($fields, $portal): ApiResponse {
                    if ($method === 'tasks.task.field.list') {
                        return new ApiResponse(['items' => array_map(static fn (string $name): array => ['name' => $name, 'type' => match ($name) {
                            'title' => 'string', 'allowsTimeTracking' => 'boolean', default => 'integer'
                        }, 'editable' => true], array_keys($fields))]);
                    }

                    return $portal->respond($method, $version, $params);
                });
                $json = json_encode($fields, JSON_THROW_ON_ERROR);
                $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), ['--json', 'task:' . $command, ...($command === 'update' ? ['123'] : []), $option, $option === '--fields' ? $json : '-'], $json);
                self::assertSame(0, $run->status, $run->stdout);
                self::assertSame(['tasks.task.field.list', 'tasks.task.' . $command], array_column($api->calls, 'method'));
                self::assertSame($fields, $api->calls[1]['parameters']['fields']);
            }
        }
    }

    public function testFindCombinesTitleAndGroupWhilePreservingLimitsAndPartialResults(): void
    {
        foreach ([
            [['--all'], 0, [...range(101, 152), 154], true],
            [['--group-id', '42', '--all'], 0, [151, 152], true],
            [['--group-id', '42', '--limit', '1'], 0, [151], true],
            [['--group-id', '42', '--max-scan', '50'], 3, [], false],
            [['--group-id', '99'], 0, [], true],
        ] as [$options, $status, $ids, $complete]) {
            $api = new FakeApiTransport(static function (string $method, int $version, array $params) use ($options): ApiResponse {
                self::assertSame('tasks.task.list', $method);
                self::assertSame(3, $version);
                self::assertSame([], $params['filter']);
                self::assertSame(in_array('--group-id', $options, true), in_array('groupId', $params['select'], true));
                $items = $params['pagination']['offset'] === 0
                    ? array_map(static fn (int $id): array => ['id' => $id, 'title' => 'Договор', 'groupId' => 7], range(101, 150))
                    : [
                        ['id' => 151, 'title' => 'ДОГОВОР', 'groupId' => 42],
                        ['id' => 152, 'title' => 'Новый договор', 'groupId' => 42],
                        ['id' => 153, 'title' => 'Другой документ', 'groupId' => 42],
                        ['id' => 154, 'title' => 'Договор', 'groupId' => 7],
                    ];
                return new ApiResponse(['items' => $items]);
            });
            $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), ['--json', 'task:find', '--title', 'договор', ...$options]);
            self::assertSame($status, $run->status, $run->stdout . $run->stderr);
            $json = $run->json();
            self::assertSame($ids, array_column($json['data']['items'], 'id'));
            self::assertSame($complete, $json['meta']['complete']);
            self::assertSame('tasks-visible-to-connection', $json['meta']['scope']);
            self::assertSame($complete ? 54 : 50, $json['meta']['scanned']);
            foreach ($json['data']['items'] as $item) {
                self::assertSame(['id', 'title'], array_keys($item));
            }

            if (!$complete) {
                self::assertSame('scan-budget-exhausted', $json['meta']['reason']);
                self::assertSame('partial-result', $json['error']['code']);
            }
        }
    }

    public function testHelpAndCompletionExposeOnlyCanonicalOption(): void
    {
        $fakeApiTransport = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected API call.'));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport);
        foreach (self::commands() as $argv) {
            $command = $app->find($argv[0]);
            self::assertTrue($command->getDefinition()->hasOption('group-id'));
            self::assertFalse($command->getDefinition()->hasOption('project'));
            $help = ConsoleHarness::run($app, [$argv[0], '--help']);
            self::assertSame(0, $help->status, $help->stderr);
            self::assertStringContainsString('--group-id', $help->stdout);
            self::assertStringNotContainsString('--project', $help->stdout);
            $completion = ConsoleHarness::run($app, ['_complete', '--shell', 'bash', '--input', 'b24cli', '--input', $argv[0], '--input=--', '--current', '2']);
            self::assertSame(0, $completion->status, $completion->stdout . $completion->stderr);
            self::assertStringContainsString('--group-id', $completion->stdout);
            self::assertStringNotContainsString('--project', $completion->stdout);
        }

        self::assertSame([], $fakeApiTransport->calls);
    }

    public function testRemovedOptionInRealProcessesFailsBeforeApiOrCredentials(): void
    {
        foreach (self::commands() as $argv) {
            foreach ([['--project', '42'], ['--project', '42', '--group-id', '42'], ['--group-id', '42', '--project', '42']] as $options) {
                foreach (['tests/Support/group-option-worker.php', 'bin/b24cli', 'bin/console'] as $entrypoint) {
                    $env = [...getenv(), 'B24CLI_ENV_FILE' => '/does/not/exist'];
                    unset($env['BITRIX24_WEBHOOK'], $env['BITRIX24_PHP_SDK_PLAYGROUND_WEBHOOK']);
                    $process = proc_open([PHP_BINARY, $entrypoint, '--json', ...$argv, ...$options], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2), $env);
                    self::assertIsResource($process);
                    $stdout = stream_get_contents($pipes[1]);
                    $stderr = stream_get_contents($pipes[2]);
                    foreach ($pipes as $pipe) {
                        fclose($pipe);
                    }

                    self::assertSame(2, proc_close($process), $stdout . $stderr);
                    self::assertSame('usage-error', json_decode($stdout, true, 512, JSON_THROW_ON_ERROR)['error']['code']);
                    self::assertStringNotContainsString('API_CALL', $stderr);
                }
            }
        }
    }
}
