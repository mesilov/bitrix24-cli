<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\LifecycleAction;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\TestCase;

final class TaskHandoverSelectionStatusTest extends TestCase
{
    private function row(int $id = 1, string $status = 'pending', array $patch = []): array
    {
        return [...['id' => $id, 'title' => 'Задача ' . $id, 'responsibleId' => 1, 'groupId' => 42, 'status' => $status, 'needsControl' => true, 'requireResult' => false, 'containsResults' => false, 'autocompleteSubTasks' => false], ...$patch];
    }

    private function invoke(array $options, array &$rows, ?\Closure $intercept = null, string $mode = '--json'): array
    {
        $fakeApiTransport = new FakeApiTransport(static function (string $method, int $version, array $params, string $effect) use (&$rows, $intercept): ApiResponse {
            $response = $intercept instanceof \Closure ? $intercept($method, $params, $effect) : null;
            if ($response instanceof ApiResponse) {
                return $response;
            }

            if ($method === 'sonet_group.user.get') {
                return new ApiResponse([['USER_ID' => 1, 'ROLE' => 'A'], ['USER_ID' => 2, 'ROLE' => 'K']]);
            }

            if ($method === 'tasks.task.list') {
                return new ApiResponse(['items' => array_slice(array_values($rows), $params['pagination']['offset'], $params['pagination']['limit'])]);
            }

            if ($method === 'tasks.task.get') {
                return new ApiResponse(['item' => $rows[$params['id']]]);
            }

            if ($method === 'tasks.task.access.get') {
                return new ApiResponse(array_fill_keys(array_column(LifecycleAction::cases(), 'value'), true));
            }

            if ($method === 'tasks.task.result.list') {
                return new ApiResponse(['items' => [['id' => 77, 'taskId' => 1, 'status' => 'open']]]);
            }

            self::assertSame('write', $effect);
            if ($method === 'tasks.task.update') {
                self::assertSame(3, $version);
                self::assertSame(['responsibleId' => 2], $params['fields']);
                $rows[$params['id']]['responsibleId'] = 2;
                return new ApiResponse(['result' => true]);
            }

            self::assertSame(1, $version);
            self::assertSame(['taskId' => 1], $params);
            $rows[1]['status'] = match ($method) {
                'tasks.task.start', 'tasks.task.disapprove' => 'in_progress',
                'tasks.task.pause' => 'pending',
                'tasks.task.defer' => 'deferred',
                'tasks.task.complete' => $rows[1]['needsControl'] ? 'supposedly_completed' : 'completed',
                'tasks.task.approve' => 'completed',
                default => throw new \LogicException('Unexpected API: ' . $method),
            };
            return new ApiResponse(['task' => ['id' => 1]]);
        });
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        return [ConsoleHarness::run($app, [$mode, 'task:handover', '--group-id', '42', '--responsible', '2', ...$options]), $fakeApiTransport, $spyConnectionResolver];
    }

    private function writes(FakeApiTransport $api): array
    {
        return array_values(array_filter($api->calls, static fn (array $call): bool => $call['effect'] === 'write'));
    }

    public function testDeferredSelectionExcludesIdAndPreservesDeferredWithoutTransition(): void
    {
        foreach ([false, true] as $dry) {
            $rows = [1 => $this->row(status: 'deferred'), 92888 => $this->row(92888, 'deferred'), 3 => $this->row(3), 4 => $this->row(4, 'completed')];
            [$run, $api] = $this->invoke(['--source-status', 'deferred', '--exclude-id', '92888', '--exclude-id', '92888', '--target-status', 'deferred', ...($dry ? ['--dry-run'] : [])], $rows);
            self::assertSame(0, $run->status, $run->stdout);
            self::assertSame(1, $rows[92888]['responsibleId']);
            self::assertSame(1, $rows[3]['responsibleId']);
            if ($dry) {
                self::assertSame([], $this->writes($api));
                self::assertSame([1], array_column(array_filter($run->json()['data']['plan']['steps'], static fn (array $s): bool => $s['effect'] === 'write'), 'taskId'));
                self::assertStringContainsString('92888', $run->json()['data']['plan']['target']);
            } else {
                self::assertSame([92888], $run->json()['meta']['excludedIds']);
                self::assertSame([1], array_column($run->json()['data']['items'], 'taskId'));
                self::assertSame('not_needed', $run->json()['data']['items'][0]['transitionStatus']);
                self::assertSame('deferred', $rows[1]['status']);
                self::assertCount(1, $this->writes($api));
            }
        }
    }

    public function testEveryReachableTargetUsesBoundedNativeActionsAndChecksFinalState(): void
    {
        $cases = [
            ['pending', 'deferred', ['defer']], ['supposedly_completed', 'deferred', ['disapprove', 'defer']], ['in_progress', 'pending', ['pause']],
            ['deferred', 'pending', ['start', 'pause']], ['supposedly_completed', 'pending', ['disapprove', 'pause']],
            ['pending', 'in_progress', ['start']], ['supposedly_completed', 'in_progress', ['disapprove']],
            ['pending', 'supposedly_completed', ['complete']], ['pending', 'completed', ['complete', 'approve']],
            ['supposedly_completed', 'completed', ['approve']],
        ];
        foreach ($cases as [$source, $target, $actions]) {
            $rows = [1 => $this->row(status: $source)];
            [$run, $api] = $this->invoke(['--target-status', $target], $rows);
            self::assertSame(0, $run->status, $run->stdout);
            $item = $run->json()['data']['items'][0];
            self::assertSame($actions, array_column($item['transitionActions'], 'action'));
            self::assertSame($target, $rows[1]['status']);
            self::assertSame($target, $item['actualStatus']);
            foreach (['assignmentStatus', 'transitionStatus', 'verificationStatus', 'status'] as $field) {
                self::assertSame('confirmed', $item[$field]);
            }

            self::assertCount(1 + count($actions), $this->writes($api));
        }
    }

    public function testInvalidSelectionAndTargetsFailBeforeCredentials(): void
    {
        foreach ([['--source-status', 'completed'], ['--source-status', '6'], ['--source-status', ''], ['--exclude-id', '0'], ['--exclude-id', '-1'], ['--exclude-id', 'x'], ['--target-status', 'declined'], ['--target-status', '6'], ['--target-status', 'unknown']] as $options) {
            $rows = [];
            [$run, $api, $resolver] = $this->invoke($options, $rows);
            self::assertSame(2, $run->status, $run->stdout);
            self::assertSame([], $api->calls);
            self::assertSame(0, $resolver->reads);
        }
    }

    public function testOpenStatusConflictBeforeAssignmentAndStatusResetAfterAssignment(): void
    {
        foreach ([false, true] as $after) {
            $rows = [1 => $this->row(), 2 => $this->row(2)];
            [$run, $api] = $this->invoke([], $rows, static function (string $method) use (&$rows, $after): ?ApiResponse {
                if ($method === 'tasks.task.get' && (!$after || $rows[1]['responsibleId'] === 2)) {
                    return new ApiResponse(['item' => [...$rows[1], 'status' => 'in_progress']]);
                }

                return null;
            });
            self::assertSame(3, $run->status, $run->stdout);
            self::assertCount($after ? 1 : 0, $this->writes($api));
            self::assertSame($after ? 'unverified' : 'failed', $run->json()['data']['items'][0]['status']);
            self::assertSame('skipped', $run->json()['data']['items'][1]['status']);
        }
    }

    public function testDeniedOrUnknownTransitionRetainsConfirmedAssignmentAndNeverRetries(): void
    {
        foreach (['denied', 'lost', 'malformed', 'readback', 'scope-readback'] as $mode) {
            $rows = [1 => $this->row(), 2 => $this->row(2)];
            $transitioned = false;
            $postReads = 0;
            [$run, $api] = $this->invoke(['--target-status', 'deferred'], $rows, static function (string $method) use ($mode, &$transitioned, &$postReads): ?ApiResponse {
                if ($method === 'tasks.task.access.get' && $mode === 'denied') {
                    return new ApiResponse(['defer' => false]);
                }

                if ($method === 'tasks.task.defer') {
                    $transitioned = true;
                    if ($mode === 'lost') {
                        throw new Failure('transport-error', 'Response lost.', 1, [], true);
                    }

                    if ($mode === 'malformed') {
                        return new ApiResponse(['task' => ['id' => 999]]);
                    }
                }

                if ($method === 'tasks.task.get' && $transitioned && ($mode === 'readback' || ($mode === 'scope-readback' && ++$postReads === 2))) {
                    throw new Failure('api-error', 'Readback unavailable.');
                }

                return null;
            });
            self::assertSame(3, $run->status, $run->stdout);
            $item = $run->json()['data']['items'][0];
            self::assertSame('confirmed', $item['assignmentStatus']);
            self::assertSame(match ($mode) {
                'denied' => 'failed', 'readback', 'scope-readback' => 'unverified', default => 'unknown'
            }, $item['transitionStatus']);
            self::assertSame(in_array($mode, ['lost', 'malformed'], true), $run->json()['meta']['outcomeUnknown']);
            if (in_array($mode, ['readback', 'scope-readback'], true)) {
                self::assertSame('unverified', $item['status']);
                self::assertSame([$mode === 'readback' ? 'unverified' : 'confirmed'], array_column($item['transitionActions'], 'status'));
            }

            self::assertSame('skipped', $run->json()['data']['items'][1]['status']);
            self::assertCount($mode === 'denied' ? 1 : 2, $this->writes($api));
            if ($mode !== 'denied') {
                self::assertNull($item['actualStatus']);
            }
        }
    }

    public function testCompletionAndReviewCannotBypassResultControlOrPermissionGates(): void
    {
        foreach (['result', 'control', 'approve'] as $mode) {
            $rows = [1 => $this->row(patch: ['requireResult' => $mode === 'result', 'needsControl' => $mode !== 'control'])];
            [$run, $api] = $this->invoke(['--target-status', $mode === 'control' ? 'supposedly_completed' : 'completed'], $rows, static fn (string $method): ?ApiResponse => match ($method) {
                'tasks.task.result.list' => new ApiResponse(['items' => []]), 'tasks.task.access.get' => new ApiResponse(['complete' => true, 'approve' => false]), default => null
            });
            self::assertSame(3, $run->status, $run->stdout);
            self::assertSame($mode === 'control' ? 'failed' : 'partial', $run->json()['data']['items'][0]['status']);
            self::assertCount(match ($mode) {
                'control' => 0, 'result' => 1, default => 2
            }, $this->writes($api));
        }
    }

    public function testUnexpectedCompleteStatusDoesNotSatisfyReviewTarget(): void
    {
        $rows = [1 => $this->row()];
        [$run, $api] = $this->invoke(['--target-status', 'supposedly_completed'], $rows, static function (string $method) use (&$rows): ?ApiResponse {
            if ($method === 'tasks.task.complete') {
                $rows[1]['status'] = 'completed';
                return new ApiResponse(['task' => ['id' => 1]]);
            }

            return null;
        });
        self::assertSame(3, $run->status, $run->stdout);
        self::assertSame('partial', $run->json()['data']['items'][0]['status']);
        self::assertSame('completed', $run->json()['data']['items'][0]['actualStatus']);
        self::assertCount(2, $this->writes($api));
    }

    public function testDirectCompletionAndAlreadyMatchingTargets(): void
    {
        foreach (['pending', 'in_progress', 'deferred', 'supposedly_completed'] as $status) {
            $rows = [1 => $this->row(status: $status)];
            [$run, $api] = $this->invoke(['--target-status', $status], $rows);
            self::assertSame(0, $run->status, $run->stdout);
            self::assertSame('not_needed', $run->json()['data']['items'][0]['transitionStatus']);
            self::assertCount(1, $this->writes($api));
        }

        $rows = [1 => $this->row(patch: ['needsControl' => false, 'requireResult' => true])];
        [$run, $api] = $this->invoke(['--target-status', 'completed'], $rows);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame('completed', $rows[1]['status']);
        self::assertSame(['tasks.task.update', 'tasks.task.complete'], array_column($this->writes($api), 'method'));
    }

    public function testNoProgressNeverRepeatsTransitionAndFinalCardMismatchIsNotConfirmed(): void
    {
        foreach ([false, true] as $finalMismatch) {
            $rows = [1 => $this->row()];
            $postReads = 0;
            [$run, $api] = $this->invoke(['--target-status', 'deferred'], $rows, static function (string $method) use (&$rows, $finalMismatch, &$postReads): ?ApiResponse {
                if (!$finalMismatch && $method === 'tasks.task.defer') {
                    return new ApiResponse(['task' => ['id' => 1]]);
                }

                if ($finalMismatch && $method === 'tasks.task.get' && $rows[1]['status'] === 'deferred' && ++$postReads === 3) {
                    return new ApiResponse(['item' => [...$rows[1], 'status' => 'pending']]);
                }

                return null;
            });
            self::assertSame(3, $run->status, $run->stdout);
            self::assertNotSame('confirmed', $run->json()['data']['items'][0]['status']);
            self::assertSame('confirmed', $run->json()['data']['items'][0]['assignmentStatus']);
            self::assertCount(2, $this->writes($api));
        }
    }

    public function testCancellationDuringTransitionKeepsAssignmentAndStopsRemainingTasks(): void
    {
        $rows = [1 => $this->row(), 2 => $this->row(2)];
        [$run, $api] = $this->invoke(['--target-status', 'deferred'], $rows, static function (string $method): ?ApiResponse {
            if ($method === 'tasks.task.defer') {
                throw new Failure('interrupted', 'Cancelled in transport.', 130, [], true);
            }

            return null;
        });
        self::assertSame(130, $run->status, $run->stdout);
        self::assertSame('confirmed', $run->json()['data']['items'][0]['assignmentStatus']);
        self::assertSame('unknown', $run->json()['data']['items'][0]['transitionStatus']);
        self::assertSame('skipped', $run->json()['data']['items'][1]['status']);
        self::assertCount(2, $this->writes($api));
    }

    public function testDryRunListsConditionalActionsWithoutWritingAndStrictPolicyBlocksAllApi(): void
    {
        foreach ([false, true] as $strict) {
            $rows = [1 => $this->row()];
            [$run, $api] = $this->invoke(['--target-status', 'completed', '--dry-run', ...($strict ? ['--api-policy', 'strict-rest3'] : [])], $rows);
            self::assertSame($strict ? 4 : 0, $run->status, $run->stdout);
            self::assertSame([], $this->writes($api));
            if ($strict) {
                self::assertSame([], $api->calls);
            } else {
                $steps = $run->json()['data']['plan']['steps'];
                self::assertContains('tasks.task.complete', array_column($steps, 'method'));
                self::assertContains('tasks.task.approve', array_column($steps, 'method'));
                self::assertContains('tasks.task.result.list', array_column($steps, 'method'));
            }
        }
    }

    public function testPartialSummaryShowsAssignmentEvenWhenTransitionFails(): void
    {
        foreach (['--plain', '--no-ansi'] as $mode) {
            $rows = [1 => $this->row(), 2 => $this->row(2)];
            [$run] = $this->invoke(['--target-status', 'deferred'], $rows, static fn (string $method): ?ApiResponse => $method === 'tasks.task.access.get' ? new ApiResponse(['defer' => false]) : null, $mode);
            self::assertSame(3, $run->status, $run->stdout);
            self::assertSame(2, $rows[1]['responsibleId']);
            self::assertStringContainsString('передано 1 из 2; полностью выполнено 0', $run->stderr);
            self::assertStringNotContainsString('передано 0', $run->stderr);
        }
    }
}
