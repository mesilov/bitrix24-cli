<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\FixturePortal;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\TestCase;

final class TaskResultsTest extends TestCase
{
    public function testResultCommandsWorkWithStrictRest3AndNeverChangeTaskOrChat(): void
    {
        foreach ([
            ['task:result:add', '123', '--text', 'Done'],
            ['task:result:list', '123'],
            ['task:result:update', '123', '--result', '17', '--text', 'Fixed'],
            ['task:result:delete', '123', '--result', '17', '--force'],
        ] as $argv) {
            $portal = new FixturePortal();
            $api = new FakeApiTransport($portal->respond(...));
            $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $api);
            $run = ConsoleHarness::run($app, ['--json', '--api-policy', 'strict-rest3', ...$argv]);
            self::assertSame(0, $run->status, $run->stdout);
            foreach ($api->calls as $call) {
                self::assertSame(3, $call['version']);
                self::assertStringStartsWith('tasks.task.result.', $call['method']);
            }

            if ($argv[0] !== 'task:result:list') {
                self::assertSame(17, $run->json()['data']['operation']['resourceId']);
                self::assertSame(123, $run->json()['data']['operation']['taskId']);
                self::assertTrue($run->json()['data']['operation']['confirmed']);
            } else {
                self::assertSame([$portal->result], $run->json()['data']['items']);
            }
        }
    }

    public function testInvalidInputAndUnconfirmedDeletionDoNotReadCredentialsOrCallApi(): void
    {
        foreach ([
            ['task:result:add', '0', '--text', 'x'], ['task:result:add', '123'],
            ['task:result:add', '123', '--text', ' '], ['task:result:add', '123', '--text', 'x', '--text-file', '-'],
            ['task:result:add', '123', '--fields', '{"taskId":999,"text":"x"}'],
            ['task:result:update', '123', '--text', 'x'], ['task:result:update', '123', '--result', '-1', '--text', 'x'],
            ['task:result:delete', '123', '--result', '0', '--force'], ['task:result:delete', '123', '--result', '17'],
            ['task:result:list', '123', '--limit', '2', '--all'], ['task:result:list', '123', '--max-scan', '0'],
            ['task:result:list', '123', '--limit', '99999999999999999999'],
        ] as $argv) {
            $api = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected API.'));
            $resolver = new SpyConnectionResolver();
            $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $api, $resolver);
            $run = ConsoleHarness::run($app, ['--json', ...$argv]);
            self::assertSame(2, $run->status, $run->stdout);
            self::assertSame([], $api->calls);
            self::assertSame(0, $resolver->reads);
        }
    }

    public function testHelpForEveryResultCommandIsOffline(): void
    {
        $spyConnectionResolver = new SpyConnectionResolver();
        $fakeApiTransport = new FakeApiTransport(static fn () => throw new \LogicException());
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        foreach (['add', 'list', 'update', 'delete'] as $action) {
            self::assertSame(0, ConsoleHarness::run($app, ['task:result:' . $action, '--help'])->status);
        }

        self::assertSame(0, $spyConnectionResolver->reads);
        self::assertSame([], $fakeApiTransport->calls);
    }

    public function testTextFromFileAndStdinIsPreserved(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'b24cli-result-');
        $text = "Готово\nhttps://example.test/report\n";
        file_put_contents($path, $text);
        try {
            foreach (['add', 'update'] as $action) {
                foreach ([$path, '-'] as $source) {
                    $portal = new FixturePortal();
                    $api = new FakeApiTransport($portal->respond(...));
                    $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $api);
                    $argv = ['--json', 'task:result:' . $action, '123', '--text-file', $source];
                    if ($action === 'update') {
                        $argv[] = '--result';
                        $argv[] = '17';
                    }

                    $run = ConsoleHarness::run($app, $argv, $text);
                    self::assertSame(0, $run->status, $run->stdout);
                    self::assertSame($text, $api->calls[array_key_last($api->calls)]['parameters']['fields']['text']);
                }
            }
        } finally {
            unlink($path);
        }
    }

    public function testDryRunChecksBindingAndNeverWrites(): void
    {
        foreach ([
            ['task:result:add', '123', '--text', 'Done'],
            ['task:result:update', '123', '--result', '17', '--text', 'Fixed'],
            ['task:result:delete', '123', '--result', '17'],
        ] as $argv) {
            $portal = new FixturePortal();
            $api = new FakeApiTransport($portal->respond(...));
            $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $api);
            $run = ConsoleHarness::run($app, ['--json', ...$argv, '--dry-run']);
            self::assertSame(0, $run->status, $run->stdout);
            self::assertTrue($run->json()['meta']['dryRun']);
            self::assertCount(1, $run->json()['data']['plan']['steps']);
            self::assertSame($argv[0] === 'task:result:add' ? [] : ['tasks.task.result.list'], array_column($api->calls, 'method'));
        }
    }

    public function testForeignResultCannotBeUpdatedOrDeletedEvenWithForceOrDryRun(): void
    {
        foreach (['update', 'delete'] as $action) {
            foreach ([false, true] as $dryRun) {
                $api = new FakeApiTransport(static fn (): ApiResponse => new ApiResponse(['items' => []]));
                $argv = ['--json', 'task:result:' . $action, '123', '--result', '999'];
                array_push($argv, ...($action === 'update' ? ['--text', 'x'] : ['--force']));
                if ($dryRun) {
                    $argv[] = '--dry-run';
                }

                $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), $argv);
                self::assertSame(4, $run->status, $run->stdout);
                self::assertSame('resource-binding-unverified', $run->json()['error']['code']);
                self::assertCount(1, $api->calls);
                self::assertSame([['taskId', 123], ['id', 999]], $api->calls[0]['parameters']['filter']);
            }
        }
    }

    public function testIncorrectPreflightDoesNotWrite(): void
    {
        $fixturePortal = new FixturePortal();
        foreach ([[...$fixturePortal->result, 'id' => 18], [...$fixturePortal->result, 'taskId' => 999], ['id' => 17]] as $item) {
            $api = new FakeApiTransport(static fn (): ApiResponse => new ApiResponse(['items' => [$item]]));
            $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), ['--json', 'task:result:delete', '123', '--result', '17', '--force']);
            self::assertContains($run->status, [1, 4]);
            self::assertFalse($run->json()['meta']['outcomeUnknown']);
            self::assertSame(['read'], array_column($api->calls, 'effect'));
        }
    }

    public function testMalformedMutationResponsesAreUnknownAndNotRetried(): void
    {
        $fixturePortal = new FixturePortal();
        foreach ([
            ['add', ['item' => ['id' => 17]]],
            ['add', ['item' => [...$fixturePortal->result, 'taskId' => 999]]],
            ['update', ['item' => [...$fixturePortal->result, 'id' => 18]]],
            ['update', ['item' => [...$fixturePortal->result, 'status' => 0]]],
            ['delete', ['result' => false]], ['delete', ['result' => 1]], ['delete', [true]],
        ] as [$action, $response]) {
            $api = new FakeApiTransport(static fn (string $method): ApiResponse => $method === 'tasks.task.result.list' ? new ApiResponse(['items' => [$fixturePortal->result]]) : new ApiResponse($response));
            $argv = ['--json', 'task:result:' . $action, '123'];
            if ($action !== 'add') {
                $argv[] = '--result';
                $argv[] = '17';
            }

            array_push($argv, ...($action === 'delete' ? ['--force'] : ['--text', 'x']));
            $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), $argv);
            self::assertSame(1, $run->status, $run->stdout);
            self::assertSame('api-error', $run->json()['error']['code']);
            self::assertTrue($run->json()['meta']['outcomeUnknown']);
            self::assertCount(1, array_filter($api->calls, static fn (array $call): bool => $call['effect'] !== 'read'));
        }
    }

    private static function rows(int $from, int $to): array
    {
        $fixturePortal = new FixturePortal();
        return array_map(static fn (int $id): array => [...$fixturePortal->result, 'id' => $id], range($from, $to));
    }

    public function testListWalksPagesDeduplicatesAndAppliesOutputLimitAfterScanning(): void
    {
        foreach ([['--all'], ['--limit', '2']] as $options) {
            $api = new FakeApiTransport(static fn (string $method, int $version, array $params): ApiResponse => new ApiResponse(['items' => $params['pagination']['offset'] === 0 ? self::rows(1, 50) : self::rows(50, 52)]));
            $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), ['--json', 'task:result:list', '123', ...$options]);
            self::assertSame(0, $run->status, $run->stdout);
            $json = $run->json();
            self::assertTrue($json['meta']['complete']);
            self::assertSame(53, $json['meta']['scanned']);
            self::assertSame(52, $json['meta']['matched']);
            self::assertCount($options[0] === '--all' ? 52 : 2, $json['data']['items']);
            self::assertSame([0, 50], array_map(static fn (array $call): int => $call['parameters']['pagination']['offset'], $api->calls));
        }
    }

    public function testBudgetAndRepeatedPagesReturnPartialData(): void
    {
        foreach ([['--all', '--max-scan', '50'], ['--all']] as $options) {
            $api = new FakeApiTransport(static fn (): ApiResponse => new ApiResponse(['items' => self::rows(1, 50)]));
            $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), ['--json', 'task:result:list', '123', ...$options]);
            self::assertSame(3, $run->status);
            self::assertFalse($run->json()['meta']['complete']);
            self::assertCount(50, $run->json()['data']['items']);
            self::assertSame(count($options) > 1 ? 'scan-budget-exhausted' : 'pagination-no-progress', $run->json()['meta']['reason']);
            self::assertLessThanOrEqual(2, count($api->calls));
        }
    }

    public function testFailedLaterPageRetainsDataButInterruptionStops(): void
    {
        foreach ([1, 130] as $status) {
            $api = new FakeApiTransport(static function (string $method, int $version, array $params) use ($status): ApiResponse {
                if ($params['pagination']['offset'] > 0) {
                    throw new Failure($status === 130 ? 'interrupted' : 'permission-denied', 'Unavailable.', $status);
                }

                return new ApiResponse(['items' => self::rows(1, 50)]);
            });
            $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), ['--json', 'task:result:list', '123', '--all']);
            self::assertSame($status === 130 ? 130 : 3, $run->status);
            if ($status === 1) {
                self::assertCount(50, $run->json()['data']['items']);
                self::assertSame('page-error', $run->json()['meta']['reason']);
            }
        }
    }

    public function testEmptyListAndPlainEscaping(): void
    {
        $fakeApiTransport = new FakeApiTransport(static fn (): ApiResponse => new ApiResponse(['items' => []]));
        $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport), ['--json', 'task:result:list', '123']);
        self::assertSame(0, $run->status);
        self::assertSame([], $run->json()['data']['items']);
        self::assertTrue($run->json()['meta']['complete']);

        $fixturePortal = new FixturePortal();
        $fixturePortal->result['text'] = "<error>итог</error>\tстрока\nещё\\";
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), new FakeApiTransport($fixturePortal->respond(...)));
        $run = ConsoleHarness::run($app, ['--plain', 'task:result:list', '123']);
        self::assertSame("17\t123\t2\t2026-10-13T12:00:00+06:00\topen\t<error>итог</error>\\tстрока\\nещё\\\\\n", $run->stdout);
        self::assertStringNotContainsString("\033[", $run->stdout);
    }
}
