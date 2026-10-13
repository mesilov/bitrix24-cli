<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RecentTaskScanTest extends TestCase
{
    private function invoke(array $argv, array $rows): array
    {
        $fakeApiTransport = new FakeApiTransport(static function (string $method, int $version, array $params) use ($rows): ApiResponse {
            self::assertSame('tasks.task.list', $method);
            self::assertSame(3, $version);
            usort($rows, static function (array $a, array $b) use ($params): int {
                foreach ((array) $params['order'] as $field => $direction) {
                    $comparison = $a[$field] <=> $b[$field];
                    if ($comparison !== 0) {
                        return $direction === 'DESC' ? -$comparison : $comparison;
                    }
                }

                return 0;
            });
            return new ApiResponse(['items' => array_slice($rows, $params['pagination']['offset'], $params['pagination']['limit'])]);
        });
        $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport), $argv);
        return [$run, $fakeApiTransport];
    }

    public static function commands(): iterable
    {
        yield 'list' => [['task:list']];
        yield 'find' => [['task:find', '--title', 'ЁЖ[']];
    }

    #[DataProvider('commands')]
    public function testNewestGroupTaskIsReachableBeyondTenThousandTasks(array $command): void
    {
        $rows = array_map(static fn (int $id): array => ['id' => $id, 'title' => $id === 10051 ? 'Новый ёж[ договор' : 'other', 'groupId' => $id === 10051 ? 187 : 7], range(1, 10051));
        foreach ([[], ['--order', 'id:asc']] as $order) {
            [$run, $fakeApiTransport] = $this->invoke(['--json', ...$command, '--group-id', '187', '--max-scan', '10', ...$order], $rows);
            $json = $run->json();
            self::assertSame(3, $run->status, $run->stdout);
            self::assertSame($order === [] ? [10051] : [], array_column($json['data']['items'], 'id'));
            self::assertSame(['id' => $order === [] ? 'DESC' : 'ASC'], $json['meta']['order']);
            self::assertFalse($json['meta']['complete']);
            self::assertSame(10, $json['meta']['scanned']);
            self::assertSame('scan-budget-exhausted', $json['meta']['reason']);
            self::assertSame('partial-result', $json['error']['code']);
            self::assertSame('tasks-visible-to-connection', $json['meta']['scope']);
            self::assertCount(1, $fakeApiTransport->calls);
            self::assertSame([], $fakeApiTransport->calls[0]['parameters']['filter']);
            self::assertContains('groupId', $fakeApiTransport->calls[0]['parameters']['select']);
        }
    }

    #[DataProvider('commands')]
    public function testPaginationAndOutputLimitsKeepTheChosenOrder(array $command): void
    {
        $rows = array_map(static fn (int $id): array => ['id' => $id, 'title' => 'ёж['], range(1, 101));
        foreach ([[], ['--order', 'id:asc']] as $order) {
            [$run, $fakeApiTransport] = $this->invoke(['--json', ...$command, '--limit', '2', ...$order], $rows);
            $json = $run->json();
            self::assertSame(0, $run->status, $run->stdout);
            self::assertSame($order === [] ? [101, 100] : [1, 2], array_column($json['data']['items'], 'id'));
            self::assertTrue($json['meta']['complete']);
            self::assertSame(101, $json['meta']['scanned']);
            self::assertSame(101, $json['meta']['matched']);
            self::assertSame(2, $json['meta']['returned']);
            self::assertTrue($json['meta']['limitApplied']);
            self::assertCount(3, $fakeApiTransport->calls);
            self::assertSame([0, 50, 100], array_map(static fn (array $call): int => $call['parameters']['pagination']['offset'], $fakeApiTransport->calls));
            foreach ($fakeApiTransport->calls as $call) {
                self::assertSame($json['meta']['order'], (array) $call['parameters']['order']);
            }
        }
    }

    #[DataProvider('commands')]
    public function testCustomSortAndIdTieBreakerArePreserved(array $command): void
    {
        $rows = [['id' => 1, 'title' => 'ёж[ z'], ['id' => 3, 'title' => 'ёж[ a'], ['id' => 2, 'title' => 'ёж[ a']];
        foreach ([[], ['--order', 'id:desc']] as $tieBreaker) {
            [$run, $fakeApiTransport] = $this->invoke(['--json', ...$command, '--order', 'title:asc', ...$tieBreaker], $rows);
            self::assertSame(0, $run->status, $run->stdout);
            self::assertSame($tieBreaker === [] ? [2, 3, 1] : [3, 2, 1], array_column($run->json()['data']['items'], 'id'));
            self::assertSame(['title' => 'ASC', 'id' => $tieBreaker === [] ? 'ASC' : 'DESC'], (array) $fakeApiTransport->calls[0]['parameters']['order']);
        }
    }

    #[DataProvider('commands')]
    public function testInvalidSortFailsBeforeApi(array $command): void
    {
        foreach (['chat:asc', 'id:sideways', 'id', 'id:asc:desc'] as $order) {
            $fakeApiTransport = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected API call.'));
            $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport), ['--json', ...$command, '--order', $order]);
            self::assertSame(2, $run->status, $run->stdout);
            self::assertSame('usage-error', $run->json()['error']['code']);
            self::assertSame([], $fakeApiTransport->calls);
        }
    }

    #[DataProvider('commands')]
    public function testBudgetDiagnosticsForEmptyAndNonemptyResultsInEveryFormat(array $command): void
    {
        foreach (['--json', '--plain', '--no-ansi'] as $format) {
            foreach ([7, 187] as $groupId) {
                [$run] = $this->invoke([$format, ...$command, '--group-id', '187', '--all', '--max-scan', '1'], [['id' => 1, 'title' => 'ёж[', 'groupId' => $groupId], ['id' => 2, 'title' => 'ёж[', 'groupId' => $groupId]]);
                self::assertSame(3, $run->status, $run->stdout);
                foreach (['scan-budget-exhausted', 'scanned=1', 'order=id:DESC', 'Increase --max-scan', '--all only removes the output limit', 'does not prove that no duplicate exists'] as $fragment) {
                    self::assertStringContainsString($fragment, $run->stderr);
                    if ($format === '--json') {
                        self::assertStringContainsString($fragment, $run->json()['error']['message']);
                    }
                }

                if ($format === '--json') {
                    self::assertSame($groupId === 187 ? [2] : [], array_column($run->json()['data']['items'], 'id'));
                    self::assertSame(['id' => 'DESC'], $run->json()['meta']['order']);
                    self::assertStringContainsString('Increase --max-scan', $run->json()['meta']['hint']);
                } elseif ($format === '--plain') {
                    self::assertStringNotContainsString('scan-budget', $run->stdout);
                    self::assertSame($groupId === 187 ? "2\tёж[" . ($command[0] === 'task:list' ? "\t\t187\t\t" : '') . "\n" : '', $run->stdout);
                }
            }
        }
    }

    #[DataProvider('commands')]
    public function testCompleteEmptyResultIsSuccessful(array $command): void
    {
        [$run] = $this->invoke(['--json', ...$command, '--group-id', '187'], [['id' => 1, 'title' => 'other', 'groupId' => 7]]);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([], $run->json()['data']['items']);
        self::assertTrue($run->json()['meta']['complete']);
        self::assertNull($run->json()['error']);
        self::assertArrayNotHasKey('hint', $run->json()['meta']);
        self::assertSame('', $run->stderr);
    }

    #[DataProvider('commands')]
    public function testHelpAndCompletionDescribeSortWithoutApi(array $command): void
    {
        $fakeApiTransport = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected API call.'));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport);
        $help = ConsoleHarness::run($app, [$command[0], '--help']);
        self::assertSame(0, $help->status);
        foreach (['--order', 'id:desc', '--max-scan', 'meta.complete=false', '--all'] as $fragment) {
            self::assertStringContainsString($fragment, $help->stdout);
        }

        $completion = ConsoleHarness::run($app, ['_complete', '--shell', 'bash', '--input', 'b24cli', '--input', $command[0], '--input=--', '--current', '2']);
        self::assertSame(0, $completion->status);
        self::assertStringContainsString('--order', $completion->stdout);
        self::assertSame([], $fakeApiTransport->calls);
    }
}
