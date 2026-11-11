<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Task\TaskListRoute;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest1TaskListAdapter;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Issue #183: group, responsible and creator predicates of task:list/task:find use the REST 1.0 server filter.
 */
final class TaskServerListFilterTest extends TestCase
{
    private function invoke(string $command, array $options, \Closure $respond, string $format = '--json', array $global = []): array
    {
        $fakeApiTransport = new FakeApiTransport($respond);
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, new SpyConnectionResolver());
        $run = ConsoleHarness::run($app, [$format, ...$global, $command, ...($command === 'task:find' ? ['--title', 'ДОГОВОР'] : []), ...$options]);
        return [$run, $fakeApiTransport];
    }

    public static function commands(): iterable
    {
        yield 'list' => ['task:list'];
        yield 'find' => ['task:find'];
    }

    private static function row(int $id, int $responsible = 1, int $group = 42, string $status = '2', int $creator = 1): array
    {
        return ['id' => (string) $id, 'title' => 'Договор поставки', 'responsibleId' => (string) $responsible, 'createdBy' => (string) $creator, 'groupId' => (string) $group, 'status' => $status, 'deadline' => ''];
    }

    public function testRest1FieldListsMatchTheAdapter(): void
    {
        $reflectionClass = new \ReflectionClass(Rest1TaskListAdapter::class);
        self::assertSame(array_keys($reflectionClass->getConstant('FIELDS')), TaskListRoute::REST1_FIELDS);
        self::assertSame($reflectionClass->getConstant('SORT_FIELDS'), TaskListRoute::REST1_SORT_FIELDS);
    }

    #[DataProvider('commands')]
    public function testRoleAndGroupGoToTheServerFilter(string $command): void
    {
        [$run, $api] = $this->invoke($command, ['--role', 'responsible', '--group-id', '1096', '--all'], static function ($method, $version, array $params): ApiResponse {
            self::assertSame(['tasks.task.list', 1], [$method, $version]);
            self::assertSame(['GROUP_ID' => 1096, 'RESPONSIBLE_ID' => 1], (array) $params['filter']);
            self::assertSame(['ID' => 'DESC'], (array) $params['order']);
            return new ApiResponse(['tasks' => [self::row(95290, 1, 1096), self::row(95289, 1, 1096)]], null, 2);
        });
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        $json = $run->json();
        self::assertSame([95290, 95289], array_column($json['data']['items'], 'id'));
        self::assertTrue($json['meta']['complete']);
        self::assertSame(2, $json['meta']['scanned']);
        self::assertSame(['apiVersion' => '1.0', 'serverFilter' => ['groupId', 'responsibleId'], 'reason' => null], $json['meta']['listRoute']);
        self::assertSame(['role' => 'responsible', 'userId' => 1], $json['meta']['roleFilter']);
        self::assertSame(['tasks.task.list'], array_values(array_unique(array_column($api->calls, 'method'))));
        if ($command === 'task:list') {
            self::assertSame(['id', 'title', 'responsibleId', 'groupId', 'status', 'deadline'], array_keys($json['data']['items'][0]));
            self::assertSame(['id' => 95290, 'title' => 'Договор поставки', 'responsibleId' => 1, 'groupId' => 1096, 'status' => 'pending', 'deadline' => null], $json['data']['items'][0]);
        } else {
            self::assertSame(['id', 'title'], array_keys($json['data']['items'][0]));
        }
    }

    public function testLargePortalWithoutMatchesGivesACompleteEmptyResult(): void
    {
        [$run, $api] = $this->invoke('task:list', ['--role', 'responsible', '--group-id', '1096', '--all'], static fn (): ApiResponse => new ApiResponse(['tasks' => []], null, 0));
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame([], $run->json()['data']['items']);
        self::assertTrue($run->json()['meta']['complete']);
        self::assertNull($run->json()['meta']['reason']);
        self::assertCount(1, $api->calls);
    }

    public function testCreatorRoleExplicitFiltersAndPagination(): void
    {
        $rows = array_map(static fn (int $id): array => self::row($id, 7, 42, '3'), range(200, 101, -1));
        [$run, $api] = $this->invoke('task:list', ['--role', 'creator', '--responsible', '7', '--status', 'in_progress', '--id', '150', '--id', '120', '--limit', '1'], static function ($method, $version, array $params) use ($rows): ApiResponse {
            self::assertSame(['ID' => [150, 120], 'RESPONSIBLE_ID' => 7, 'CREATED_BY' => 1], (array) $params['filter']);
            $start = $params['start'];
            return new ApiResponse(['tasks' => array_slice($rows, $start, 50)], $start === 0 ? 50 : null, 100);
        });
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        $json = $run->json();
        self::assertSame([200], array_column($json['data']['items'], 'id'));
        self::assertSame(100, $json['meta']['matched']);
        self::assertTrue($json['meta']['limitApplied']);
        self::assertSame(['id', 'responsibleId', 'creatorId'], $json['meta']['listRoute']['serverFilter']);
        self::assertSame([0, 50], array_column(array_column($api->calls, 'parameters'), 'start'));
        self::assertArrayNotHasKey('creatorId', $json['data']['items'][0]);
    }

    public function testContradictoryRoleAndResponsibleEndInACompleteEmptyResult(): void
    {
        [$run] = $this->invoke('task:list', ['--role', 'responsible', '--responsible', '9'], static function ($method, $version, array $params): ApiResponse {
            self::assertSame(['RESPONSIBLE_ID' => 9], (array) $params['filter']);
            return new ApiResponse(['tasks' => [self::row(5, 9)]], null, 1);
        });
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame([], $run->json()['data']['items']);
        self::assertTrue($run->json()['meta']['complete']);
        self::assertSame(1, $run->json()['meta']['scanned']);
    }

    public function testWhereFieldsAreServerFilteredToo(): void
    {
        [$run] = $this->invoke('task:list', ['--where', '{"groupId":"42","creatorId":3,"status":"pending"}'], static function ($method, $version, array $params): ApiResponse {
            self::assertSame(1, $version);
            self::assertSame(['GROUP_ID' => 42, 'CREATED_BY' => 3], (array) $params['filter']);
            return new ApiResponse(['tasks' => [self::row(5, 1, 42, '2', 3), self::row(4, 1, 42, '5', 3)]], null, 2);
        });
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame([5], array_column($run->json()['data']['items'], 'id'));
    }

    public function testNullDeadlineIsAValueForLocalEqualityOnTheServerRoute(): void
    {
        $respond = static fn ($method, $version, array $params): ApiResponse => new ApiResponse(['tasks' => [self::row(6), [...self::row(5), 'deadline' => '2026-11-19T19:00:00+06:00']]], null, 2);
        [$undated] = $this->invoke('task:list', ['--where', '{"groupId":42,"deadline":""}'], $respond);
        self::assertSame(0, $undated->status, $undated->stdout . $undated->stderr);
        self::assertSame([6], array_column($undated->json()['data']['items'], 'id'));
        self::assertTrue($undated->json()['meta']['complete']);

        [$dated] = $this->invoke('task:list', ['--where', '{"groupId":42,"deadline":"2026-11-19T19:00:00+06:00"}'], $respond);
        self::assertSame(0, $dated->status, $dated->stdout . $dated->stderr);
        self::assertSame([5], array_column($dated->json()['data']['items'], 'id'));
        self::assertTrue($dated->json()['meta']['complete']);
    }

    public function testParticipantRoleReadsMembersOnlyForGroupCandidates(): void
    {
        [$run, $api] = $this->invoke('task:list', ['--role', 'auditor', '--group-id', '42'], static function ($method, $version, array $params): ApiResponse {
            if ($method === 'tasks.task.list') {
                self::assertSame(1, $version);
                self::assertSame(['GROUP_ID' => 42], (array) $params['filter']);
                return new ApiResponse(['tasks' => [self::row(8), self::row(9)]], null, 2);
            }

            self::assertSame(['tasks.task.get', 1], [$method, $version]);
            return new ApiResponse(['task' => ['id' => (string) $params['taskId'], 'auditors' => $params['taskId'] === 9 ? ['1'] : []]]);
        });
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame([9], array_column($run->json()['data']['items'], 'id'));
        self::assertSame([8, 9], array_column(array_column(array_slice($api->calls, 1), 'parameters'), 'taskId'));
    }

    public function testMissingGroupOnServerRowIsIncompleteWithoutCompanionRead(): void
    {
        [$run, $api] = $this->invoke('task:list', ['--group-id', '42'], static fn (): ApiResponse => new ApiResponse(['tasks' => [['id' => '5', 'title' => 'Договор', 'responsibleId' => '1', 'status' => '2', 'deadline' => '']]], null, 1));
        self::assertSame(3, $run->status, $run->stdout);
        self::assertSame('missing-predicate-field', $run->json()['meta']['reason']);
        self::assertCount(1, $api->calls);
    }

    public function testInconsistentServerTotalIsPartial(): void
    {
        [$run] = $this->invoke('task:list', ['--group-id', '42'], static fn (): ApiResponse => new ApiResponse(['tasks' => [self::row(5)]], null, 3));
        self::assertSame(3, $run->status, $run->stdout);
        self::assertSame('pagination-no-progress', $run->json()['meta']['reason']);
        self::assertSame([5], array_column($run->json()['data']['items'], 'id'));
    }

    public function testServerScanBudgetCountsServerRows(): void
    {
        [$run] = $this->invoke('task:list', ['--group-id', '42', '--max-scan', '2'], static fn ($method, $version, array $params): ApiResponse => new ApiResponse(['tasks' => [self::row(5), self::row(4), self::row(3)]], 3, 4));
        self::assertSame(3, $run->status, $run->stdout);
        self::assertSame('scan-budget-exhausted', $run->json()['meta']['reason']);
        self::assertSame(2, $run->json()['meta']['scanned']);
        self::assertStringContainsString('no matching task', $run->json()['meta']['hint']);
    }

    public static function localRoutes(): iterable
    {
        yield 'strict policy' => [['--group-id', '42'], ['--api-policy', 'strict-rest3'], 'strict-rest3', []];
        yield 'rest3 only order' => [['--group-id', '42', '--order', 'responsibleId:asc'], [], 'unsupported-field:responsibleId', []];
        yield 'rest3 only select' => [['--role', 'responsible', '--select', 'creator.id'], [], 'unsupported-field:creator.id', []];
        yield 'no server predicate' => [['--status', 'pending', '--id', '5'], [], 'no-server-predicate', ['id']];
    }

    #[DataProvider('localRoutes')]
    public function testLocalRouteIsChosenBeforeRequests(array $options, array $global, string $reason, array $serverFilter): void
    {
        [$run, $api] = $this->invoke('task:list', $options, static function ($method, $version, array $params): ApiResponse {
            if ($method === 'tasks.task.get') {
                return new ApiResponse(['task' => ['id' => $params['id'], 'group' => ['id' => 42]]]);
            }

            self::assertSame(['tasks.task.list', 3], [$method, $version]);
            return new ApiResponse(['items' => [['id' => 5, 'title' => 'Договор', 'responsibleId' => 1, 'groupId' => 42, 'status' => 'pending', 'deadline' => null, 'creator' => ['id' => 1]]]]);
        }, '--json', $global);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame(['apiVersion' => '3.0', 'serverFilter' => $serverFilter, 'reason' => $reason], $run->json()['meta']['listRoute']);
        self::assertNotContains(1, array_column($api->calls, 'version'));
    }

    public function testTaskListHintDoesNotMentionDuplicates(): void
    {
        $rows = static fn ($method, $version, array $params): ApiResponse => new ApiResponse(['tasks' => [self::row(5), self::row(4)]], 2, 4);
        [$list] = $this->invoke('task:list', ['--group-id', '42', '--max-scan', '1'], $rows, '--plain');
        self::assertSame(3, $list->status);
        self::assertStringContainsString('не доказывает отсутствие подходящих задач', $list->stderr);
        self::assertStringNotContainsString('дублей', $list->stderr);

        [$find] = $this->invoke('task:find', ['--group-id', '42', '--max-scan', '1'], $rows, '--plain');
        self::assertSame(3, $find->status);
        self::assertStringContainsString('не доказывает отсутствие дублей', $find->stderr);
    }
}
