<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Application\Task\LifecycleAction;
use Bitrix24\CLI\Application\Task\Request\TaskLifecycleRequest;
use Bitrix24\CLI\Application\Task\TaskLifecycleService;
use Bitrix24\CLI\Application\Task\TaskStatus;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest1TaskLifecycleAdapter;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest3TaskAdapter;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\TestCase;

final class TaskLifecycleTest extends TestCase
{
    private function task(array $patch = []): array
    {
        return [...['id' => 123, 'status' => 'supposedly_completed', 'needsControl' => true, 'requireResult' => false, 'containsResults' => false, 'autocompleteSubTasks' => false], ...$patch];
    }

    private function transport(array $pre = [], array $post = [], array $rights = [], ?\Closure $mutation = null, ?\Closure $readback = null, ?\Closure $results = null): FakeApiTransport
    {
        $reads = 0;
        return new FakeApiTransport(function (string $method) use (&$reads, $pre, $post, $rights, $mutation, $readback, $results): ApiResponse {
            if ($method === 'tasks.task.get') {
                $reads++;
                return $reads === 1 ? new ApiResponse(['item' => $this->task($pre)]) : ($readback instanceof \Closure ? $readback() : new ApiResponse(['item' => $this->task($post)]));
            }

            if ($method === 'tasks.task.access.get') {
                return new ApiResponse([...array_fill_keys(array_column(LifecycleAction::cases(), 'value'), true), ...$rights]);
            }

            if ($method === 'tasks.task.result.list') {
                return $results instanceof \Closure ? $results() : new ApiResponse(['items' => [['id' => 9, 'taskId' => 123, 'status' => 'open']]]);
            }

            return $mutation instanceof \Closure ? $mutation() : new ApiResponse(['task' => ['id' => '123']]);
        });
    }

    private function invoke(FakeApiTransport $api, array $argv): \Bitrix24\CLI\Tests\Support\ConsoleRun
    {
        return ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), $argv);
    }

    public function testEachActionHasExactRoutePayloadAndActualPostState(): void
    {
        foreach (LifecycleAction::cases() as $action) {
            $api = $this->transport(post: ['status' => 'pending']);
            $run = $this->invoke($api, ['--json', 'task:' . $action->value, '123']);
            self::assertSame(0, $run->status, $run->stdout);
            self::assertSame(['tasks.task.get', 'tasks.task.access.get', $action->method(), 'tasks.task.get'], array_column($api->calls, 'method'));
            self::assertSame(['method' => $action->method(), 'version' => 1, 'parameters' => ['taskId' => 123], 'effect' => 'write'], $api->calls[2]);
            self::assertSame('pending', $run->json()['data']['task']['status']);
            self::assertTrue($run->json()['data']['operation']['confirmed']);
        }
    }

    public function testOfflineHelpStrictPolicyAndInvalidInputNeverReadCredentials(): void
    {
        foreach (LifecycleAction::cases() as $action) {
            foreach ([['--help'], ['123', '--api-policy', 'strict-rest3'], ['123', '--api-policy', 'strict-rest3', '--dry-run'], [], ['0'], ['-1'], ['wrong'], ['999999999999999999999'], ['123', '--typo']] as $options) {
                $api = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected API call.'));
                $resolver = new SpyConnectionResolver();
                $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $api, $resolver);
                $run = ConsoleHarness::run($app, ['--json', 'task:' . $action->value, ...$options]);
                self::assertSame(in_array('--help', $options, true) ? 0 : (in_array('strict-rest3', $options, true) ? 4 : 2), $run->status, $run->stdout);
                self::assertSame([], $api->calls);
                self::assertSame(0, $resolver->reads);
            }
        }
    }

    public function testPermissionAndSemanticGatesNeverMutate(): void
    {
        foreach ([['complete' => false, 'edit' => true], ['complete' => null]] as $rights) {
            $api = $this->transport(rights: $rights);
            $run = $this->invoke($api, ['--json', 'task:complete', '123']);
            self::assertSame($rights['complete'] === false ? 1 : 4, $run->status);
            self::assertNotContains('write', array_column($api->calls, 'effect'));
        }

        foreach ([['needsControl' => null], ['requireResult' => 'N'], ['status' => '5'], ['autocompleteSubTasks' => null], ['needsControl' => false], ['status' => 'completed']] as $pre) {
            $api = $this->transport(pre: $pre);
            self::assertSame(4, $this->invoke($api, ['--json', 'task:approve', '123'])->status);
            self::assertNotContains('write', array_column($api->calls, 'effect'));
        }
    }

    public function testNativeResultRequiredGateAndBoundedRequest(): void
    {
        foreach ([[], [['id' => 9, 'taskId' => 999, 'status' => 'open']], [['id' => 0, 'taskId' => 123, 'status' => 'open']], [['id' => 9, 'taskId' => 123, 'status' => 'unknown']]] as $items) {
            $api = $this->transport(pre: ['requireResult' => true, 'containsResults' => true], results: static fn () => new ApiResponse(['items' => $items]));
            $run = $this->invoke($api, ['--json', 'task:complete', '123']);
            self::assertSame(4, $run->status);
            self::assertSame('required-result-unverified', $run->json()['error']['code']);
            self::assertNotContains('write', array_column($api->calls, 'effect'));
        }

        foreach (['open', 'closed'] as $status) {
            $api = $this->transport(pre: ['requireResult' => true], results: static fn () => new ApiResponse(['items' => [['id' => 9, 'taskId' => 123, 'status' => $status]]]));
            self::assertSame(0, $this->invoke($api, ['--json', 'task:complete', '123'])->status);
            self::assertEquals(['filter' => [['taskId', 123]], 'select' => ['id', 'taskId', 'status'], 'order' => (object) ['id' => 'ASC'], 'pagination' => ['offset' => 0, 'limit' => 1]], $api->calls[2]['parameters']);
        }

        $api = $this->transport(pre: ['requireResult' => true], results: static fn () => throw new Failure('permission-denied', 'Unavailable.'));
        self::assertSame(4, $this->invoke($api, ['--json', 'task:complete', '123'])->status);
    }

    public function testConfirmationAndFailedReadbackAreDistinctFromUnknownWrite(): void
    {
        foreach ([static fn () => throw new Failure('transport-error', 'Read failed.'), static fn () => new ApiResponse(['item' => ['id' => 999]]), static fn () => new ApiResponse(['item' => ['id' => 123, 'needsControl' => true]])] as $readback) {
            $run = $this->invoke($this->transport(readback: $readback), ['--json', 'task:complete', '123']);
            self::assertSame(3, $run->status);
            self::assertTrue($run->json()['data']['operation']['confirmed']);
            self::assertNull($run->json()['data']['task']['status']);
            self::assertFalse($run->json()['meta']['outcomeUnknown']);
        }

        foreach ([[], ['task' => ['id' => 999]], ['task' => 'invalid'], ['task' => ['id' => '999999999999999999999999']], [true], [null]] as $ack) {
            $api = $this->transport(mutation: static fn () => new ApiResponse($ack));
            $run = $this->invoke($api, ['--json', 'task:complete', '123']);
            self::assertSame(1, $run->status);
            self::assertTrue($run->json()['meta']['outcomeUnknown']);
            self::assertCount(3, $api->calls);
        }

        $api = $this->transport(mutation: static fn () => throw new Failure('transport-error', 'Sent write lost.', 1, [], true));
        self::assertTrue($this->invoke($api, ['--json', 'task:complete', '123'])->json()['meta']['outcomeUnknown']);
        self::assertCount(3, $api->calls);
    }

    public function testServerRaceRefusalNeverFallsBackOrRetries(): void
    {
        $api = $this->transport(mutation: static fn () => throw new Failure('permission-denied', 'Permission changed after preflight.'));
        $run = $this->invoke($api, ['--json', 'task:complete', '123']);
        self::assertSame(1, $run->status);
        self::assertFalse($run->json()['meta']['outcomeUnknown']);
        self::assertSame(['tasks.task.get', 'tasks.task.access.get', 'tasks.task.complete'], array_column($api->calls, 'method'));
    }

    public function testDryRunAndOutputKeepReviewSeparateFromCompletion(): void
    {
        $api = $this->transport(pre: ['autocompleteSubTasks' => true]);
        $run = $this->invoke($api, ['--json', 'task:complete', '123', '--dry-run']);
        self::assertSame(0, $run->status);
        self::assertCount(2, $api->calls);
        self::assertTrue($run->json()['data']['plan']['steps'][0]['fields']['autocompleteSubTasks']);
        foreach (['--json', '--plain', 'human'] as $mode) {
            $run = $this->invoke($this->transport(), [...($mode === 'human' ? [] : [$mode]), 'task:complete', '123']);
            self::assertSame(0, $run->status);
            self::assertStringContainsString('supposedly_completed', $run->stdout);
            if ($mode === '--plain') {
                self::assertSame("123\tcomplete\ttrue\tsupposedly_completed\ttrue\tfalse\n", $run->stdout);
            }
        }
    }

    public function testCancellationAfterAcknowledgementRetainsConfirmationWithoutReadback(): void
    {
        $runtimeState = new RuntimeState();
        $api = $this->transport(mutation: static function () use ($runtimeState): ApiResponse {
            $runtimeState->cancelled = true;
            return new ApiResponse(['task' => ['id' => 123]]);
        });
        $taskLifecycleService = new TaskLifecycleService(new Rest3TaskAdapter($api), new Rest1TaskLifecycleAdapter($api), $runtimeState);
        $prepared = $taskLifecycleService->prepare(new TaskLifecycleRequest(123, LifecycleAction::Complete));
        $result = ($prepared->execute)();
        self::assertSame(130, $result->exitStatus);
        self::assertTrue($result->data['operation']['confirmed']);
        self::assertFalse($result->meta['outcomeUnknown']);
        self::assertCount(3, $api->calls);
    }

    public function testListUsesLogicalStatusesWithAndPredicates(): void
    {
        foreach (TaskStatus::cases() as $status) {
            $api = new FakeApiTransport(static fn () => new ApiResponse(['items' => [
                ['id' => 1, 'title' => 'Match', 'responsibleId' => 42, 'groupId' => 7, 'status' => $status->value],
                ['id' => 2, 'title' => 'Wrong user', 'responsibleId' => 99, 'groupId' => 7, 'status' => $status->value],
                ['id' => 3, 'title' => 'Wrong group', 'responsibleId' => 42, 'groupId' => 8, 'status' => $status->value],
            ]]));
            $run = $this->invoke($api, ['--json', 'task:list', '--responsible', '42', '--group-id', '7', '--status', $status->value]);
            self::assertSame([1], array_column($run->json()['data']['items'], 'id'));
            self::assertSame([], $api->calls[0]['parameters']['filter']);
        }

        foreach (['', '5', 'unknown', 'in-progress', 5, ['completed']] as $status) {
            foreach ([['--where', json_encode(['status' => $status], JSON_THROW_ON_ERROR)], ...is_string($status) ? [['--status', $status]] : []] as $options) {
                $api = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected API call.'));
                self::assertSame(2, $this->invoke($api, ['--json', 'task:list', ...$options])->status);
                self::assertSame([], $api->calls);
            }
        }
    }

    public function testRealSigintPreservesKnownAndUnknownMutationOutcomes(): void
    {
        foreach (['ack', 'lost'] as $mode) {
            $process = proc_open([PHP_BINARY, __DIR__ . '/../Support/lifecycle-signal-worker.php', $mode], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            try {
                stream_set_timeout($pipes[2], 10);
                self::assertSame("READY\n", fgets($pipes[2]));
                self::assertTrue(proc_terminate($process, SIGINT));
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }

                self::assertSame(130, proc_close($process), $stdout . $stderr);
                $process = null;
                $json = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
                self::assertSame('interrupted', $json['error']['code']);
                self::assertSame($mode === 'lost', $json['meta']['outcomeUnknown']);
                if ($mode === 'ack') {
                    self::assertTrue($json['data']['operation']['confirmed']);
                }

                self::assertStringNotContainsString('UNEXPECTED_POST_READ', $stderr);
            } finally {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }
        }
    }

    public function testGroupCompanionComposesWithRolesAndFindWithoutReadingExcludedRows(): void
    {
        foreach (['task:list', 'task:find'] as $command) {
            foreach (['responsible', 'auditor'] as $role) {
                $api = new FakeApiTransport(static function (string $method, int $version, array $params): ApiResponse {
                    if ($method === 'tasks.task.list') {
                        return new ApiResponse(['items' => [
                            ['id' => 1, 'title' => 'Match', 'responsibleId' => 1, 'groupId' => 8, 'status' => 'pending'],
                            ['id' => 2, 'title' => 'Match', 'responsibleId' => 1, 'groupId' => null, 'status' => 'pending'],
                            ['id' => 2, 'title' => 'Match', 'responsibleId' => 1, 'groupId' => null, 'status' => 'pending'],
                            ['id' => 3, 'title' => 'Match', 'responsibleId' => 9, 'groupId' => null, 'status' => 'pending'],
                        ]]);
                    }

                    if ($version === 1) {
                        self::assertSame(2, $params['taskId']);
                        return new ApiResponse(['task' => ['id' => '2', 'auditors' => ['1']]]);
                    }

                    self::assertSame(2, $params['id']);
                    return new ApiResponse(['item' => ['id' => 2, 'group' => ['id' => 7]]]);
                });
                $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api, new SpyConnectionResolver()), ['--json', $command, ...($command === 'task:find' ? ['--title', 'Match'] : []), '--role', $role, '--responsible', '1', '--group-id', '7', '--status', 'pending']);
                self::assertSame(0, $run->status, $run->stdout);
                self::assertSame([2], array_column($run->json()['data']['items'], 'id'));
                self::assertSame($role === 'auditor' ? [3, 1, 3] : [3, 3], array_column($api->calls, 'version'));
                self::assertTrue($run->json()['meta']['complete']);
            }
        }
    }

    public function testNullListGroupIsVerifiedFromCardWithoutLegacyFallback(): void
    {
        foreach ([true, false] as $verified) {
            $api = new FakeApiTransport(static function (string $method, int $version, array $params) use ($verified): ApiResponse {
                self::assertSame(3, $version);
                if ($method === 'tasks.task.get') {
                    self::assertSame(['id' => 123, 'select' => ['id', 'group.id']], $params);
                    return new ApiResponse(['item' => ['id' => 123, ...($verified ? ['group' => ['id' => 234]] : [])]]);
                }

                return new ApiResponse(['items' => [['id' => 123, 'title' => 'Group fixture', 'groupId' => null, 'responsibleId' => 14, 'status' => 'pending'], ['id' => 124, 'title' => 'Other user', 'groupId' => null, 'responsibleId' => 99, 'status' => 'pending']]]);
            });
            $run = $this->invoke($api, ['--json', 'task:list', '--group-id', '234', '--responsible', '14', '--status', 'pending']);
            self::assertSame($verified ? 0 : 3, $run->status);
            self::assertSame($verified ? [123] : [], array_column($run->json()['data']['items'], 'id'));
            self::assertCount(2, $api->calls);
            if ($verified) {
                self::assertSame(234, $run->json()['data']['items'][0]['groupId']);
            } else {
                self::assertSame('group-membership-unverified', $run->json()['meta']['reason']);
            }
        }
    }
}
