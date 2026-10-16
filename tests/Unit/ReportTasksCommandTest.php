<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Application\Connection\ScopeManifest;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\{ConsoleHarness, FakeApiTransport, SpyConnectionResolver};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReportTasksCommandTest extends TestCase
{
    private function invoke(array $options, ?\Closure $respond = null, string $format = '--json'): array
    {
        $fakeApiTransport = new FakeApiTransport($respond ?? static fn () => new ApiResponse(['tasks' => []]));
        $spyConnectionResolver = new SpyConnectionResolver();
        $clock = new class () implements Clock {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-10-20T12:00:00Z');
            }
        };
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver, clock: $clock);
        return [ConsoleHarness::run($app, [$format, 'task:report', ...$options]), $fakeApiTransport, $spyConnectionResolver];
    }

    private static function raw(int $id, string $status): array
    {
        return ['id' => (string) $id, 'title' => "Task\t<error>" . $id, 'groupId' => '42', 'responsibleId' => '14', 'status' => $status, 'createdDate' => '2026-10-14T12:00:00Z', 'closedDate' => $status === '5' ? '2026-10-15T12:00:00Z' : null];
    }

    public function testNativePayloadWholeProjectAndOptionalResponsible(): void
    {
        foreach ([[], ['--responsible', '14']] as $responsible) {
            [$run, $api] = $this->invoke(['--group-id', '42', '--timezone', 'UTC', ...$responsible], static function (string $method, int $version, array $params, string $effect) use ($responsible): ApiResponse {
                self::assertSame('tasks.task.list', $method);
                self::assertSame(1, $version);
                self::assertSame('read', $effect);
                self::assertSame(['ID' => 'ASC'], (array) $params['order']);
                self::assertSame(0, $params['start']);
                $filter = (array) $params['filter'];
                self::assertSame(42, $filter['GROUP_ID']);
                self::assertSame($responsible === [] ? null : 14, $filter['RESPONSIBLE_ID'] ?? null);
                self::assertArrayNotHasKey('STATUS', $filter);
                self::assertArrayNotHasKey('responsibleId', $filter);
                self::assertContains('CREATED_DATE', $params['select']);
                self::assertContains('CLOSED_DATE', $params['select']);
                if (!isset($filter['REAL_STATUS'])) {
                    self::assertSame('2026-10-12T00:00:00+00:00', $filter['>=CREATED_DATE']);
                    self::assertStringStartsWith('2026-10-19T00:00:00', $filter['<CREATED_DATE']);
                }

                return new ApiResponse(['tasks' => match ($filter['REAL_STATUS'] ?? null) {
                    5 => [self::raw(1, '5')], 4 => [self::raw(2, '4')], 3 => [self::raw(3, '3')],
                    default => [self::raw(1, '5'), self::raw(3, '3')],
                }]);
            });
            self::assertSame(0, $run->status, $run->stdout);
            self::assertSame([1, 1, 2, 1], array_column($run->json()['data'], 'count'));
            self::assertCount(4, $api->calls);
            self::assertSame('completed', $run->json()['data']['closedTasks']['items'][0]['status']);
            self::assertArrayHasKey('closedDate', $run->json()['data']['closedTasks']['items'][0]);
        }
    }

    public static function paginationCases(): iterable
    {
        // Each page: returned rows, next offset, total (optional in REST1).
        yield 'missing next with remaining rows' => [[50, null, 51], [0, null, 51], 3, 50];
        yield 'total below returned rows' => [[1, null, 0], [0, null, 0], 3, 1];
        yield 'empty terminal page with positive total' => [[0, null, 1], [0, null, 1], 3, 0];
        yield 'next after advertised end' => [[50, 50, 50], [0, null, 50], 3, 50];
        yield 'missing next on later page' => [[50, 50, 52], [1, null, 52], 3, 51];
        yield 'consistent multiple pages' => [[50, 50, 51], [1, null, 51], 0, 51];
        yield 'optional total absent' => [[50, null, null], [0, null, null], 0, 50];
        yield 'consistent empty selection' => [[0, null, 0], [0, null, 0], 0, 0];
    }

    #[DataProvider('paginationCases')]
    public function testRestPaginationCompleteness(array $first, array $second, int $exit, int $observed): void
    {
        [$run] = $this->invoke(['--group-id', '42', '--timezone', 'UTC'], static function (string $method, int $version, array $params) use ($first, $second): ApiResponse {
            if ((((array) $params['filter'])['REAL_STATUS'] ?? null) !== 3) {
                return new ApiResponse(['tasks' => []], total: 0);
            }

            $offset = $params['start'];
            [$size, $next, $total] = $offset === 0 ? $first : $second;
            $rows = array_map(static fn (int $index): array => self::raw($offset + $index + 1, '3'), $size === 0 ? [] : range(0, $size - 1));
            return new ApiResponse(['tasks' => $rows], $next, $total);
        });
        self::assertSame($exit, $run->status, $run->stdout);
        $section = $run->json()['data']['inProgressTasks'];
        self::assertSame($observed, $section['observedCount']);
        self::assertSame($exit === 0 ? $observed : null, $section['count']);
        self::assertSame($exit === 0, $section['complete']);
        self::assertSame($exit === 0 ? [] : ['page-incomplete'], $section['reasons']);
        self::assertSame([0, 0, 0], array_slice(array_column($run->json()['data'], 'count'), 0, 3));
    }

    public function testUsageAndStrictPolicyBeforeCredentialsOrApi(): void
    {
        $invalid = [
            [], ['--group-id', '0'], ['--group-id', '42', '--responsible', '-1'],
            ['--group-id', '42', '--week', '2026-02-30'], ['--group-id', '42', '--week', '2027-01-01'],
            ['--group-id', '42', '--timezone', 'invalid'], ['--group-id', '42', '--max-scan', '0'],
            ['--group-id', '42', '--week', 'last-week', '--week', 'this-week'],
            ['--group-id', '42', '--group-id=43'], ['--group-id', '42', '--responsible', '14', '--responsible=15'],
            ['--group-id', '42', '--max-scan=1', '--max-scan=2'], ['--group-id', '42', '--timezone=UTC', '--timezone=Europe/Berlin'],
        ];
        foreach ($invalid as $options) {
            [$run, $api, $resolver] = $this->invoke($options, static fn () => throw new \LogicException('Unexpected API'));
            self::assertSame(2, $run->status, $run->stdout);
            self::assertSame('usage-error', $run->json()['error']['code']);
            self::assertSame([], $api->calls);
            self::assertSame(0, $resolver->reads);
        }

        [$run, $api, $resolver] = $this->invoke(['--group-id', '42', '--api-policy', 'strict-rest3']);
        self::assertSame(4, $run->status);
        self::assertSame('policy-denied', $run->json()['error']['code']);
        self::assertSame([], $api->calls);
        self::assertSame(0, $resolver->reads);
        $scopeManifest = new ScopeManifest();
        self::assertSame([['task', 'tasks']], $scopeManifest->required([new Route('tasks.task.list', 1)]));
        self::assertSame(['task'], $scopeManifest->affectedCommands([])['task:report']);
        self::assertArrayNotHasKey('task:report', $scopeManifest->affectedCommands(['task']));
    }

    public function testOutputFormatsRetainPartialAndEscapeRows(): void
    {
        $respond = static fn () => new ApiResponse(['tasks' => [self::raw(1, '5')]]);
        foreach (['--json', '--plain', '--no-ansi'] as $format) {
            [$run] = $this->invoke(['--group-id', '42', '--timezone', 'UTC', '--max-scan', '1'], $respond, $format);
            self::assertSame(3, $run->status, $run->stdout);
            if ($format === '--json') {
                self::assertSame(1, $run->json()['data']['closedTasks']['count']);
                self::assertNull($run->json()['data']['newTasks']['count']);
                self::assertSame('partial-result', $run->json()['error']['code']);
            } elseif ($format === '--plain') {
                self::assertStringContainsString('Task\\t<error>1', $run->stdout);
                self::assertStringContainsString("newTasks\tsummary\t", $run->stdout);
                self::assertStringContainsString("\tnull\t0\tfalse\t", $run->stdout);
                self::assertStringContainsString('section', $run->stderr);
                self::assertStringContainsString('Контроль/работа — текущие', $run->stderr);
            } else {
                self::assertStringContainsString('неизвестно, найдено 0', $run->stdout);
                self::assertStringContainsString('Task', $run->stdout);
                self::assertStringContainsString('Новые за неделю:', $run->stdout);
            }
        }
    }

    public function testCompleteZeroAndMissingDateHaveDifferentMeaning(): void
    {
        [$run] = $this->invoke(['--group-id', '42', '--timezone', 'UTC']);
        self::assertSame(0, $run->status);
        self::assertSame([0, 0, 0, 0], array_column($run->json()['data'], 'count'));
        [$run] = $this->invoke(['--group-id', '42', '--timezone', 'UTC'], static fn (string $method, int $version, array $params): ApiResponse => new ApiResponse(['tasks' => ((array) $params['filter'])['REAL_STATUS'] ?? null ? [] : [[...self::raw(1, '3'), 'createdDate' => null]]]));
        self::assertSame(3, $run->status);
        self::assertNull($run->json()['data']['newTasks']['count']);
    }
}
