<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\{Clock, Failure, RuntimeState};
use Bitrix24\CLI\Application\Task\{Page, ReportWeekResolver, WeeklyTaskReport};
use Bitrix24\CLI\Application\Task\Port\WeeklyReportTaskGateway;
use Bitrix24\CLI\Application\Task\Request\ReportTasksRequest;
use PHPUnit\Framework\TestCase;

final class WeeklyTaskReportTest extends TestCase
{
    private function collect(\Closure $respond, int $budget = 10000, ?int $responsible = null, ?RuntimeState $state = null): \Bitrix24\CLI\Application\OperationResult
    {
        $gateway = new readonly class ($respond) implements WeeklyReportTaskGateway {
            public function __construct(private \Closure $respond)
            {
            }

            public function reportPage(array $filter, int $offset): Page
            {
                return ($this->respond)($filter, $offset);
            }
        };
        $clock = new class () implements Clock {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-10-20T12:00:00Z');
            }
        };
        $reportTasksRequest = new ReportTasksRequest(42, $responsible, (new ReportWeekResolver($clock))->resolve('2026-10-14', 'UTC'), $budget);
        return (new WeeklyTaskReport($gateway, $clock, $state ?? new RuntimeState()))->collect($reportTasksRequest);
    }

    private static function row(int $id, string $status = 'in_progress'): array
    {
        return ['id' => $id, 'title' => 'Task ' . $id, 'groupId' => 42, 'responsibleId' => 14, 'status' => $status,
            'createdDate' => '2026-10-14T12:00:00Z', 'closedDate' => $status === 'completed' ? '2026-10-15T12:00:00Z' : null];
    }

    public function testAllFourMetricsAndCurrentOldTask(): void
    {
        $result = $this->collect(static fn (array $filter): Page => new Page(match ($filter['REAL_STATUS'] ?? null) {
            5 => [self::row(1, 'completed')],
            4 => [[...self::row(2, 'supposedly_completed'), 'createdDate' => '2026-01-01T00:00:00Z']],
            3 => [self::row(3)],
            default => [self::row(1, 'completed'), self::row(3), self::row(4, 'pending')],
        }));
        self::assertSame(0, $result->exitStatus);
        self::assertSame([1, 1, 3, 1], array_column($result->data, 'count'));
        self::assertSame([1], array_column($result->data['closedTasks']['items'], 'id'));
        self::assertSame([2], array_column($result->data['awaitingControlTasks']['items'], 'id'));
        self::assertFalse($result->meta['snapshotAtomic']);
        self::assertSame('accessible', $result->meta['scope']['visibility']);
    }

    public function testPaginationAndExactBudgetAtFinalSection(): void
    {
        foreach ([51 => 0, 50 => 3] as $budget => $exit) {
            $result = $this->collect(static function (array $filter, int $offset): Page {
                if (($filter['REAL_STATUS'] ?? null) !== 3) {
                    return new Page([]);
                }

                return $offset === 0
                    ? new Page(array_map(self::row(...), range(1, 50)), 50)
                    : new Page([self::row(51)]);
            }, $budget);
            self::assertSame($exit, $result->exitStatus);
            self::assertSame($budget, $result->data['inProgressTasks']['observedCount']);
            self::assertSame($exit === 0 ? 51 : null, $result->data['inProgressTasks']['count']);
        }
    }

    public function testSharedBudgetRetainsEarlierCompleteSections(): void
    {
        $result = $this->collect(static fn (array $filter): Page => new Page(match ($filter['REAL_STATUS'] ?? null) {
            5 => [self::row(1, 'completed')], 4 => [self::row(2, 'supposedly_completed')],
            3 => [self::row(3)], default => [self::row(1, 'completed'), self::row(3)],
        }), 3);
        self::assertSame(3, $result->exitStatus);
        self::assertSame(3, $result->meta['scanned']);
        self::assertSame(1, $result->data['closedTasks']['count']);
        self::assertSame(1, $result->data['awaitingControlTasks']['count']);
        self::assertNull($result->data['newTasks']['count']);
        self::assertSame(1, $result->data['newTasks']['observedCount']);
        self::assertContains('not-scanned', $result->data['inProgressTasks']['reasons']);
    }

    public function testDuplicatesAndConflictsAreDifferent(): void
    {
        $result = $this->collect(static fn (array $filter): Page => new Page(($filter['REAL_STATUS'] ?? null) === 3 ? [self::row(1), self::row(1)] : []));
        self::assertSame(0, $result->exitStatus);
        self::assertSame(1, $result->data['inProgressTasks']['count']);
        $result = $this->collect(static fn (array $filter): Page => new Page(match ($filter['REAL_STATUS'] ?? null) {
            5 => [self::row(1, 'completed')], 4 => [], default => [self::row(1)],
        }));
        self::assertSame(3, $result->exitStatus);
        self::assertNull($result->data['closedTasks']['count']);
        self::assertContains('conflicting-observation', $result->data['newTasks']['reasons']);
        self::assertSame(0, $result->data['awaitingControlTasks']['count']);
    }

    public function testServerPredicateMismatchAndMissingFieldsArePartial(): void
    {
        foreach ([
            [...self::row(1), 'groupId' => 43],
            [...self::row(1), 'responsibleId' => 15],
            [...self::row(1), 'createdDate' => null],
            [...self::row(1), 'createdDate' => '2026-02-30T12:00:00Z'],
            [...self::row(1), 'createdDate' => '2026-10-19T00:00:00Z'],
        ] as $row) {
            $result = $this->collect(static fn (array $filter): Page => new Page(!isset($filter['REAL_STATUS']) ? [$row] : []), responsible: 14);
            self::assertSame(3, $result->exitStatus);
            self::assertNull($result->data['newTasks']['count']);
            self::assertSame(0, $result->data['newTasks']['observedCount']);
        }
    }

    public function testReopenedTaskAndCreationBoundary(): void
    {
        $row = [...self::row(1), 'createdDate' => '2026-10-12T00:00:00Z'];
        $result = $this->collect(static fn (array $filter): Page => new Page(in_array($filter['REAL_STATUS'] ?? null, [5, 4], true) ? [] : [$row]));
        self::assertSame(0, $result->exitStatus);
        self::assertSame([0, 0, 1, 1], array_column($result->data, 'count'));
    }

    public function testPageFailureRetainsClosedListAndDoesNotInventZero(): void
    {
        $result = $this->collect(static function (array $filter): Page {
            if (($filter['REAL_STATUS'] ?? null) === 5) {
                return new Page([self::row(1, 'completed')]);
            }

            throw new Failure('api-error', 'No access');
        });
        self::assertSame(3, $result->exitStatus);
        self::assertSame(1, $result->data['closedTasks']['count']);
        self::assertNull($result->data['awaitingControlTasks']['count']);
        self::assertNull($result->data['newTasks']['count']);
        self::assertContains('page-error', $result->data['awaitingControlTasks']['reasons']);
    }

    public function testFirstAccessFailureAndCancellationAreErrors(): void
    {
        foreach ([new Failure('api-error', 'Denied'), new Failure('interrupted', 'Interrupted', 130)] as $failure) {
            try {
                $this->collect(static fn () => throw $failure);
                self::fail('Failure must propagate');
            } catch (Failure $actual) {
                self::assertSame($failure, $actual);
            }
        }

        $runtimeState = new RuntimeState();
        $runtimeState->cancelled = true;
        $this->expectException(Failure::class);
        $this->expectExceptionCode(0);
        $this->collect(static fn () => throw new \LogicException('Must not call'), state: $runtimeState);
    }

    public function testUnverifiedPaginationIsNotComplete(): void
    {
        foreach ([new Page([], 50), new Page([self::row(1)], 50), new Page([], null, false)] as $page) {
            $result = $this->collect(static fn (array $filter): Page => ($filter['REAL_STATUS'] ?? null) === 3 ? $page : new Page([]));
            self::assertSame(3, $result->exitStatus);
            self::assertNull($result->data['inProgressTasks']['count']);
        }
    }
}
