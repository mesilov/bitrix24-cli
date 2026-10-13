<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Task\TaskQuery;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MyTaskCommandTest extends TestCase
{
    private static function row(int $id, string $status = 'pending', ?string $deadline = null, int $user = 2): array
    {
        return ['id' => $id, 'title' => 'Договор', 'responsibleId' => $user, 'groupId' => 42, 'status' => $status, 'deadline' => $deadline];
    }

    private function invoke(array $options, \Closure $response, string $mode = '--json'): array
    {
        $fakeApiTransport = new FakeApiTransport($response);
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        return [ConsoleHarness::run($app, [$mode, 'task:my', ...$options]), $fakeApiTransport, $spyConnectionResolver];
    }

    public static function invalidInputs(): iterable
    {
        yield 'missing user' => [[]];
        yield 'missing user before missing env' => [['--env-file', '/missing/b24cli.env']];
        yield 'invalid user before missing profile' => [['--user', '0', '--profile', 'absent']];
        foreach (['0', '-1', '1.5', 'x', '', '999999999999999999999999999999'] as $value) {
            yield 'user ' . $value => [['--user', $value]];
            yield 'budget ' . $value => [['--user', '2', '--max-scan', $value]];
        }

        foreach (['2026-10-14', '2026-10-14T00:00:00', '2026-02-30T00:00:00Z', 'bad'] as $date) {
            yield 'date ' . $date => [['--user', '2', '--due-before', $date]];
        }
    }

    #[DataProvider('invalidInputs')]
    public function testInvalidInputFailsBeforeConnectionAndApi(array $options): void
    {
        [$run, $api, $resolver] = $this->invoke($options, static fn () => throw new \LogicException('Unexpected API.'));
        self::assertSame(2, $run->status, $run->stdout . $run->stderr);
        self::assertSame('usage-error', $run->json()['error']['code']);
        self::assertSame([], $api->calls);
        self::assertSame(0, $resolver->reads);
    }

    public function testOpenStatesAndExplicitUserPreserveReadOnlyRest3(): void
    {
        $states = ['pending', 'in_progress', 'supposedly_completed', 'deferred', 'completed', 'declined'];
        $rows = array_map(static fn (int $id, string $status): array => self::row($id, $status), range(1, 6), $states);
        foreach ([false, true] as $open) {
            [$run, $api, $resolver] = $this->invoke(['--user', '2', '--api-policy', 'strict-rest3', ...($open ? ['--open'] : [])], static function ($method, $version, array $params) use ($rows): ApiResponse {
                self::assertSame('tasks.task.list', $method);
                self::assertSame(3, $version);
                self::assertSame([], $params['filter']);
                self::assertContains('responsibleId', $params['select']);
                self::assertContains('status', $params['select']);
                self::assertEquals((object) ['id' => 'DESC'], $params['order']);
                return new ApiResponse(['items' => [...$rows, self::row(7, user: 1)]]);
            });
            self::assertSame(0, $run->status, $run->stdout);
            self::assertSame($open ? [1, 2, 3, 4] : range(1, 6), array_column($run->json()['data']['items'], 'id'));
            self::assertTrue($run->json()['meta']['complete']);
            self::assertArrayNotHasKey('roleFilter', $run->json()['meta']);
            self::assertSame(0, $resolver->reads, 'Executor selection must not resolve the caller identity.');
            self::assertCount(1, $api->calls);
            self::assertSame('read', $api->calls[0]['effect']);
        }

        self::assertFalse((new TaskQuery())->openOnly);
    }

    public function testDeadlineBoundaryTimezoneNullAndAndCombination(): void
    {
        $rows = [
            self::row(1, deadline: '2026-10-14T05:59:59+06:00'),
            self::row(2, deadline: '2026-10-14T06:00:00+06:00'),
            self::row(3, deadline: '2026-10-14T00:00:01Z'),
            self::row(4),
            self::row(5, 'completed', '2026-10-13T00:00:00Z'),
            self::row(6, deadline: '2026-10-13T00:00:00Z', user: 1),
        ];
        foreach ([false, true] as $open) {
            [$run] = $this->invoke(['--user', '2', '--due-before', '2026-10-14T00:00:00Z', ...($open ? ['--open'] : [])], static fn (): ApiResponse => new ApiResponse(['items' => $rows]));
            self::assertSame(0, $run->status, $run->stdout);
            self::assertSame($open ? [1] : [1, 5], array_column($run->json()['data']['items'], 'id'));
            self::assertTrue($run->json()['meta']['complete']);
        }
    }

    public function testFractionalDeadlineUsesTheStrictInstantBoundary(): void
    {
        [$run] = $this->invoke(['--user', '2', '--due-before', '2026-10-14T00:00:00.500Z'], static fn (): ApiResponse => new ApiResponse(['items' => [
            self::row(1, deadline: '2026-10-14T00:00:00.499Z'),
            self::row(2, deadline: '2026-10-14T00:00:00.500Z'),
            self::row(3, deadline: '2026-10-14T00:00:00.501Z'),
        ]]));
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([1], array_column($run->json()['data']['items'], 'id'));
    }

    public function testUnknownOrMissingPredicateDataIsPartial(): void
    {
        foreach ([['status', null], ['status', 'unknown'], ['status', false], ['status', []], ['responsibleId', null], ['responsibleId', false], ['responsibleId', true], ['responsibleId', 2.0], ['deadline', 'invalid'], ['deadline', []]] as [$field, $value]) {
            foreach ([true, false] as $present) {
                $bad = [...self::row(1), $field => $value];
                if (!$present) {
                    unset($bad[$field]);
                }

                [$run] = $this->invoke(['--user', '2', '--open', '--due-before', '2026-10-14T00:00:00Z'], static fn (): ApiResponse => new ApiResponse(['items' => [$bad, self::row(2, deadline: '2026-10-13T00:00:00Z')]]));
                self::assertSame(3, $run->status, $run->stdout);
                self::assertSame([2], array_column($run->json()['data']['items'], 'id'));
                self::assertFalse($run->json()['meta']['complete']);
                self::assertSame('missing-predicate-field', $run->json()['meta']['reason']);
            }
        }
    }

    public function testOutputLimitDoesNotStopScanningAndBudgetDoes(): void
    {
        $response = static function ($method, $version, array $params): ApiResponse {
            $offset = $params['pagination']['offset'];
            $limit = $params['pagination']['limit'];
            self::assertLessThanOrEqual(50, $limit);
            return new ApiResponse(['items' => array_map(self::row(...), range($offset + 1, min($offset + $limit, 51)))]);
        };
        [$run, $api] = $this->invoke(['--user', '2'], $response);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame(51, $run->json()['meta']['scanned']);
        self::assertSame(51, $run->json()['meta']['matched']);
        self::assertSame(50, $run->json()['meta']['returned']);
        self::assertTrue($run->json()['meta']['limitApplied']);
        self::assertCount(2, $api->calls);
        [$run, $api] = $this->invoke(['--user', '2', '--max-scan', '1'], $response);
        self::assertSame(3, $run->status, $run->stdout);
        self::assertSame([1], array_column($run->json()['data']['items'], 'id'));
        self::assertSame(1, $run->json()['meta']['scanned']);
        self::assertSame('scan-budget-exhausted', $run->json()['meta']['reason']);
        self::assertFalse($run->json()['meta']['complete']);
        self::assertCount(1, $api->calls);
    }

    public function testOutputModesMatchListAndOfflineDiscoveryDoesNotReadConnection(): void
    {
        foreach (['--json', '--plain', '--no-ansi'] as $mode) {
            [$run] = $this->invoke(['--user', '2'], static fn (): ApiResponse => new ApiResponse(['items' => [self::row(1)]]), $mode);
            self::assertSame(0, $run->status, $run->stdout);
            if ($mode === '--json') {
                self::assertSame([self::row(1)], $run->json()['data']['items']);
            } elseif ($mode === '--plain') {
                self::assertSame("1\tДоговор\t2\t42\tpending\t\n", $run->stdout);
            } else {
                self::assertStringContainsString('Договор', $run->stdout);
            }
        }

        $fakeApiTransport = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected API.'));
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        foreach ([['task:my', '--help'], ['help', 'task:my'], ['list', 'task'], ['completion', 'bash']] as $argv) {
            $run = ConsoleHarness::run($app, $argv);
            self::assertSame(0, $run->status, $run->stdout . $run->stderr);
            if ($argv[0] !== 'completion') {
                self::assertStringContainsString('task:my', $run->stdout);
            }
        }

        $run = ConsoleHarness::run($app, ['_complete', '--shell', 'bash', '--input', 'b24cli', '--input', 'task:my', '--input=--', '--current', '2']);
        self::assertSame(0, $run->status);
        foreach (['--user', '--open', '--due-before', '--max-scan'] as $option) {
            self::assertStringContainsString($option, $run->stdout);
        }

        self::assertSame(0, $spyConnectionResolver->reads);
        self::assertSame([], $fakeApiTransport->calls);
    }
}
