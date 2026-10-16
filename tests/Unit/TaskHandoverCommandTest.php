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

final class TaskHandoverCommandTest extends TestCase
{
    private static function row(int $id, int $user = 1, int $group = 42, string $status = 'pending'): array
    {
        return ['id' => $id, 'title' => 'Договор ' . $id, 'responsibleId' => $user, 'groupId' => $group, 'status' => $status, 'deadline' => null, 'description' => 'keep', 'needsControl' => true];
    }

    private function invoke(array $options, \Closure $response, string $mode = '--json'): array
    {
        $fakeApiTransport = new FakeApiTransport($response);
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        return [ConsoleHarness::run($app, [$mode, 'task:handover', ...$options]), $fakeApiTransport, $spyConnectionResolver];
    }

    private function portal(array &$rows, ?\Closure $intercept = null): \Closure
    {
        return static function (string $method, int $version, array $params, string $effect) use (&$rows, $intercept): ApiResponse {
            $response = $intercept instanceof \Closure ? $intercept($method, $params, $effect) : null;
            if ($response instanceof ApiResponse) {
                return $response;
            }

            if ($method === 'sonet_group.user.get') {
                self::assertSame(1, $version);
                self::assertSame(['ID' => 42], $params);
                return new ApiResponse([['USER_ID' => '1', 'ROLE' => 'A'], ['USER_ID' => '2', 'ROLE' => 'K'], ['USER_ID' => 3, 'ROLE' => 'E']]);
            }

            self::assertSame(3, $version);
            if ($method === 'tasks.task.list') {
                return new ApiResponse(['items' => array_slice(array_values($rows), $params['pagination']['offset'], $params['pagination']['limit'])]);
            }

            if ($method === 'tasks.task.get') {
                return new ApiResponse(['item' => $rows[$params['id']]]);
            }

            self::assertSame('tasks.task.update', $method);
            self::assertSame('write', $effect);
            self::assertSame(['responsibleId'], array_keys($params['fields']));
            $rows[$params['id']]['responsibleId'] = $params['fields']['responsibleId'];
            return new ApiResponse(['result' => true]);
        };
    }

    private function writes(FakeApiTransport $api): array
    {
        return array_values(array_filter($api->calls, static fn (array $call): bool => $call['effect'] === 'write'));
    }

    public function testInvalidInputAndPolicyFailBeforeCredentialsAndApi(): void
    {
        $valid = ['--group-id', '42', '--responsible', '2'];
        $cases = [[], ['--group-id', '42'], [...$valid, '--from', '2']];
        foreach (['group-id', 'responsible', 'from', 'max-scan'] as $option) {
            foreach (['0', '-1', '1.5', 'x', '999999999999999999999999999'] as $value) {
                $values = ['--group-id' => '42', '--responsible' => '2', '--' . $option => $value];
                $args = [];
                foreach ($values as $flag => $number) {
                    $args[] = $flag;
                    $args[] = $number;
                }

                $cases[] = $args;
            }
        }

        foreach ($cases as $options) {
            [$run, $api, $resolver] = $this->invoke([...$options, '--env-file', '/missing/b24.env'], static fn () => throw new \LogicException('Unexpected API.'));
            self::assertSame(2, $run->status, $run->stdout);
            self::assertSame([], $api->calls);
            self::assertSame(0, $resolver->reads);
        }

        foreach ([false, true] as $dry) {
            [$run, $api, $resolver] = $this->invoke([...$valid, '--api-policy', 'strict-rest3', ...($dry ? ['--dry-run'] : [])], static fn () => throw new \LogicException('Unexpected API.'));
            self::assertSame(4, $run->status, $run->stdout);
            self::assertSame('policy-denied', $run->json()['error']['code']);
            self::assertSame([], $api->calls);
            self::assertSame(0, $resolver->reads);
        }
    }

    public function testSelfAndManagerSelectionChangeOnlyMatchingOpenTasks(): void
    {
        foreach ([null, 3] as $from) {
            $source = $from ?? 1;
            $rows = [1 => self::row(1, $source), 2 => self::row(2, $source, status: 'in_progress'), 3 => self::row(3, $source, status: 'supposedly_completed'), 4 => self::row(4, $source, status: 'deferred'), 5 => self::row(5, $source, status: 'completed'), 6 => self::row(6, $source, status: 'declined'), 7 => self::row(7, 2), 8 => self::row(8, $source, 99)];
            $before = $rows;
            [$run, $api, $resolver] = $this->invoke(['--group-id', '42', '--responsible', '2', ...($from === null ? [] : ['--from', (string) $from])], $this->portal($rows));
            self::assertSame(0, $run->status, $run->stdout);
            self::assertSame([1, 2, 3, 4], array_column($run->json()['data']['items'], 'taskId'));
            self::assertSame(['confirmed'], array_unique(array_column($run->json()['data']['items'], 'status')));
            self::assertSame(4, $run->json()['meta']['confirmed']);
            self::assertTrue($run->json()['meta']['complete']);
            self::assertCount(4, $this->writes($api));
            self::assertSame($from === null ? 1 : 0, $resolver->reads);
            foreach ($rows as $id => $row) {
                self::assertSame($id <= 4 ? [...$before[$id], 'responsibleId' => 2] : $before[$id], $row);
            }
        }
    }

    public function testAllPagesAreReadBeforeFirstWriteAndDryRunDoesNotWrite(): void
    {
        foreach ([false, true] as $dry) {
            $rows = [];
            foreach (range(1, 51) as $id) {
                $rows[$id] = self::row($id);
            }

            [$run, $api] = $this->invoke(['--group-id', '42', '--responsible', '2', ...($dry ? ['--dry-run'] : [])], $this->portal($rows));
            self::assertSame(0, $run->status, $run->stdout);
            self::assertCount($dry ? 0 : 51, $this->writes($api));
            $listIndexes = array_keys(array_filter($api->calls, static fn (array $call): bool => $call['method'] === 'tasks.task.list'));
            self::assertCount(2, $listIndexes);
            if ($dry) {
                $writes = array_filter($run->json()['data']['plan']['steps'], static fn (array $step): bool => $step['method'] === 'tasks.task.update');
                self::assertCount(51, $writes);
                self::assertSame(range(1, 51), array_column($writes, 'taskId'));
            } else {
                $writeIndexes = array_keys(array_filter($api->calls, static fn (array $call): bool => $call['effect'] === 'write'));
                self::assertLessThan(min($writeIndexes), max($listIndexes));
                self::assertSame(51, $run->json()['meta']['confirmed']);
            }
        }
    }

    public function testIncompleteSelectionBlocksEveryWriteIncludingDryRun(): void
    {
        foreach ([false, true] as $dry) {
            $rows = [1 => self::row(1), 2 => self::row(2)];
            [$run, $api] = $this->invoke(['--group-id', '42', '--responsible', '2', '--max-scan', '1', ...($dry ? ['--dry-run'] : [])], $this->portal($rows));
            self::assertSame(3, $run->status, $run->stdout);
            self::assertFalse($run->json()['error']['details']['selection']['complete']);
            self::assertSame([], $this->writes($api));
        }
    }

    public function testMalformedOrMissingMembershipBlocksWrites(): void
    {
        foreach ([new ApiResponse([]), new ApiResponse([['USER_ID' => '1', 'ROLE' => 'A']]), new ApiResponse([['USER_ID' => 1, 'ROLE' => 'A'], ['USER_ID' => 2, 'ROLE' => 'Z']]), new ApiResponse([['USER_ID' => 1, 'ROLE' => 'A'], ['USER_ID' => 1, 'ROLE' => 'K']]), new ApiResponse([['USER_ID' => true, 'ROLE' => 'A']]), new ApiResponse([['USER_ID' => 1, 'ROLE' => 'A']], 50), new ApiResponse([['USER_ID' => 1, 'ROLE' => 'A']], total: 2)] as $response) {
            $rows = [1 => self::row(1)];
            [$run, $api] = $this->invoke(['--group-id', '42', '--responsible', '2'], $this->portal($rows, static fn (string $method): ?ApiResponse => $method === 'sonet_group.user.get' ? $response : null));
            self::assertSame(4, $run->status, $run->stdout);
            self::assertSame('project-membership-unverified', $run->json()['error']['code']);
            self::assertSame([], $this->writes($api));
            self::assertCount(1, $api->calls);
        }
    }

    public function testConflictBeforeWriteStopsWithUnchangedTask(): void
    {
        foreach (['responsibleId' => 3, 'groupId' => 99, 'status' => 'completed'] as $field => $value) {
            $rows = [1 => self::row(1), 2 => self::row(2)];
            [$run, $api] = $this->invoke(['--group-id', '42', '--responsible', '2'], $this->portal($rows, static fn (string $method): ?ApiResponse => $method === 'tasks.task.get' ? new ApiResponse(['item' => [...self::row(1), $field => $value]]) : null));
            self::assertSame(3, $run->status, $run->stdout);
            self::assertSame(['failed', 'skipped'], array_column($run->json()['data']['items'], 'status'));
            self::assertSame([], $this->writes($api));
        }
    }

    public function testFailedOrUnknownWriteRetainsConfirmedAndDoesNotRetry(): void
    {
        foreach ([false, true] as $unknown) {
            $rows = [1 => self::row(1), 2 => self::row(2), 3 => self::row(3)];
            [$run, $api] = $this->invoke(['--group-id', '42', '--responsible', '2'], $this->portal($rows, static function (string $method, array $params) use ($unknown): ?ApiResponse {
                if ($method === 'tasks.task.update' && $params['id'] === 2) {
                    throw new Failure($unknown ? 'transport-error' : 'permission-denied', 'Test refusal or lost response.', 4, [], $unknown);
                }

                return null;
            }));
            self::assertSame(3, $run->status, $run->stdout);
            self::assertSame(['confirmed', $unknown ? 'unknown' : 'failed', 'skipped'], array_column($run->json()['data']['items'], 'status'));
            self::assertSame($unknown, $run->json()['meta']['outcomeUnknown']);
            self::assertSame(1, $run->json()['meta']['confirmed']);
            self::assertCount(2, $this->writes($api));
            self::assertSame(2, $rows[1]['responsibleId']);
            self::assertSame(1, $rows[3]['responsibleId']);
        }
    }

    public function testReadbackFailureAndCancellationPreserveAcknowledgedMutation(): void
    {
        foreach ([false, true] as $cancel) {
            $rows = [1 => self::row(1), 2 => self::row(2)];
            [$run, $api] = $this->invoke(['--group-id', '42', '--responsible', '2'], $this->portal($rows, static function (string $method) use (&$rows, $cancel): ?ApiResponse {
                if ($method === 'tasks.task.get' && $rows[1]['responsibleId'] === 2) {
                    throw new Failure($cancel ? 'interrupted' : 'permission-denied', 'Readback not available.', $cancel ? 130 : 4);
                }

                return null;
            }));
            self::assertSame($cancel ? 130 : 3, $run->status, $run->stdout);
            self::assertSame(['unverified', 'skipped'], array_column($run->json()['data']['items'], 'status'));
            self::assertCount(1, $this->writes($api));
            self::assertSame(2, $rows[1]['responsibleId']);
        }
    }

    public function testMembershipIsRecheckedBetweenAssignments(): void
    {
        $rows = [1 => self::row(1), 2 => self::row(2)];
        [$run, $api] = $this->invoke(['--group-id', '42', '--responsible', '2'], $this->portal($rows, static function (string $method) use (&$rows): ?ApiResponse {
            return $method === 'sonet_group.user.get' && $rows[1]['responsibleId'] === 2 ? new ApiResponse([]) : null;
        }));
        self::assertSame(3, $run->status, $run->stdout);
        self::assertSame(['confirmed', 'failed'], array_column($run->json()['data']['items'], 'status'));
        self::assertCount(1, $this->writes($api));
    }

    public function testEmptySelectionAndOutputModes(): void
    {
        $rows = [];
        [$run, $api] = $this->invoke(['--group-id', '42', '--responsible', '2'], $this->portal($rows));
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([], $run->json()['data']['items']);
        self::assertSame(0, $run->json()['meta']['selected']);
        self::assertSame([], $this->writes($api));
        foreach (['--plain', '--no-ansi'] as $mode) {
            $rows = [1 => self::row(1)];
            [$run] = $this->invoke(['--group-id', '42', '--responsible', '2'], $this->portal($rows), $mode);
            self::assertSame(0, $run->status, $run->stdout);
            self::assertStringContainsString('confirmed', $run->stdout);
            self::assertStringContainsString('передано 1 из 1', $run->stderr);
            if ($mode === '--plain') {
                self::assertSame("1\tДоговор 1\t1\t2\tconfirmed\t\tpending\tpending\tpending\tconfirmed\tnot_requested\tconfirmed\n", $run->stdout);
            }
        }
    }

    public function testOfflineDiscoveryDoesNotReadCredentials(): void
    {
        $fakeApiTransport = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected API.'));
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        foreach ([['task:handover', '--help'], ['help', 'task:handover'], ['list', 'task'], ['completion', 'bash']] as $argv) {
            self::assertSame(0, ConsoleHarness::run($app, $argv)->status);
        }

        self::assertSame([], $fakeApiTransport->calls);
        self::assertSame(0, $spyConnectionResolver->reads);
    }

    public function testMissingPredicateAndUnverifiedGroupNeverStartWrites(): void
    {
        foreach (['status', 'responsibleId', 'groupId'] as $field) {
            $row = self::row(1);
            unset($row[$field]);
            $rows = [1 => $row];
            $intercept = $field === 'groupId' ? static fn (string $method): ?ApiResponse => $method === 'tasks.task.get' ? new ApiResponse(['item' => ['id' => 1]]) : null : null;
            [$run, $api] = $this->invoke(['--group-id', '42', '--responsible', '2'], $this->portal($rows, $intercept));
            self::assertSame(3, $run->status, $run->stdout);
            self::assertSame([], $this->writes($api));
        }
    }

    public function testReadbackMismatchIsUnverifiedAndSelfTargetDoesNotCallApi(): void
    {
        $rows = [1 => self::row(1), 2 => self::row(2)];
        [$run, $api] = $this->invoke(['--group-id', '42', '--responsible', '2'], $this->portal($rows, static function (string $method) use (&$rows): ?ApiResponse {
            return $method === 'tasks.task.get' && $rows[1]['responsibleId'] === 2 ? new ApiResponse(['item' => self::row(1)]) : null;
        }));
        self::assertSame(3, $run->status, $run->stdout);
        self::assertSame(['unverified', 'skipped'], array_column($run->json()['data']['items'], 'status'));
        self::assertCount(1, $this->writes($api));
        [$run, $api] = $this->invoke(['--group-id', '42', '--responsible', '1'], static fn () => throw new \LogicException('Unexpected API.'));
        self::assertSame(2, $run->status, $run->stdout);
        self::assertSame([], $api->calls);
    }

    public function testMembershipScopeIsRequiredOnlyByHandover(): void
    {
        $scopeManifest = new \Bitrix24\CLI\Application\Connection\ScopeManifest();
        $affected = $scopeManifest->affectedCommands(['task', 'user', 'department', 'disk', 'im']);
        self::assertSame(['sonet_group'], $affected['task:handover']);
        self::assertArrayNotHasKey('task:assign', $affected);
        self::assertArrayNotHasKey('task:list', $affected);
    }

    public function testUnconfirmedMutationAckIsUnknownWithoutRetry(): void
    {
        $rows = [1 => self::row(1), 2 => self::row(2)];
        [$run, $api] = $this->invoke(['--group-id', '42', '--responsible', '2'], $this->portal($rows, static fn (string $method): ?ApiResponse => $method === 'tasks.task.update' ? new ApiResponse(['unexpected' => true]) : null));
        self::assertSame(3, $run->status, $run->stdout);
        self::assertSame(['unknown', 'skipped'], array_column($run->json()['data']['items'], 'status'));
        self::assertTrue($run->json()['meta']['outcomeUnknown']);
        self::assertNotNull($run->json()['data']['items'][1]['reason']);
        self::assertCount(1, $this->writes($api));
    }

    public function testBudgetBoundaryIsVerifiedWithAnEmptyTailProbe(): void
    {
        foreach ([50, 100] as $budget) {
            foreach ([false, true] as $extra) {
                $rows = [];
                foreach (range(1, $budget + ($extra ? 1 : 0)) as $id) {
                    $rows[$id] = self::row($id);
                }

                [$run, $api] = $this->invoke(['--group-id', '42', '--responsible', '2', '--max-scan', (string) $budget], $this->portal($rows));
                self::assertSame($extra ? 3 : 0, $run->status, $run->stdout);
                self::assertCount($extra ? 0 : $budget, $this->writes($api));
                $pages = array_values(array_filter($api->calls, static fn (array $call): bool => $call['method'] === 'tasks.task.list'));
                self::assertSame(['offset' => $budget, 'limit' => 1], $pages[array_key_last($pages)]['parameters']['pagination']);
                if ($extra) {
                    self::assertFalse($run->json()['error']['details']['selection']['complete']);
                    self::assertSame($budget, $run->json()['error']['details']['selection']['scanned']);
                } else {
                    self::assertSame($budget, $run->json()['meta']['confirmed']);
                }
            }
        }
    }
}
