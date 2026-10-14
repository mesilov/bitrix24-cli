<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\{CalendarInstant, StatusDurationCalculator};
use PHPUnit\Framework\TestCase;

final class StatusDurationCalculatorTest extends TestCase
{
    public function testRetainedServerHistoryForTwoLifetimesCannotYieldExactTotalsEvenWithNavigation(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__ . '/../Fixtures/task-history/reused-numeric-id.json'), true, 512, JSON_THROW_ON_ERROR);
        $events = array_map(static fn (array $row): array => [
            'id' => $row['id'], 'createdDate' => $row['createdDate'], 'field' => $row['field'], 'userId' => $row['userId'],
            'from' => $row['value']['from'], 'to' => $row['value']['to'],
        ], $fixture['modern']['events']);
        $result = (new StatusDurationCalculator())->calculate($fixture['taskId'], $events, true, [], $fixture['finalCard'], $fixture['finalCard'], new \DateTimeImmutable($fixture['finalCard']['changed']));
        self::assertSame(3, $result->exitStatus);
        self::assertContains('ambiguous-creation-boundary', $result->meta['reasons']);
        self::assertContains('deleted-task-history', $result->meta['reasons']);
        self::assertSame([null], array_values(array_unique(array_column($result->data['totals'], 'seconds'))));
        self::assertCount(4, $result->data['historyEvidence']);
        self::assertCount(1, $result->data['observedTransitions']);
        self::assertSame('in_progress', $result->data['observedTransitions'][0]['toStatus']);
    }

    private function time(int $seconds): string
    {
        return (new \DateTimeImmutable('2026-10-01T00:00:00Z'))->modify('+' . $seconds . ' seconds')->format(DATE_ATOM);
    }

    private function events(): array
    {
        $rows = [['id' => 1, 'field' => 'NEW', 'createdDate' => $this->time(0), 'from' => null, 'to' => null]];
        $states = ['pending', 'in_progress', 'completed', 'pending', 'in_progress', 'completed', 'pending', 'completed'];
        foreach ([10, 30, 40, 50, 80, 90, 100] as $key => $second) {
            $rows[] = ['id' => $key + 2, 'field' => 'REAL_STATUS', 'createdDate' => $this->time($second), 'userId' => 9, 'from' => $states[$key], 'to' => $states[$key + 1]];
        }

        return $rows;
    }

    private function calculate(?array $events = null, bool $complete = true, array $reasons = [], int $cutoff = 120, array $before = [], array $after = []): \Bitrix24\CLI\Application\OperationResult
    {
        $snapshot = ['status' => 'completed', 'statusChanged' => $this->time(100), 'changed' => $this->time(100)];
        return (new StatusDurationCalculator())->calculate(1, $events ?? $this->events(), $complete, $reasons, [...$snapshot, ...$before], [...$snapshot, ...$after], new \DateTimeImmutable($this->time($cutoff)));
    }

    public function testTwoReopeningsConserveAllCalendarSecondsIncludingCompleted(): void
    {
        $result = $this->calculate();
        self::assertSame(0, $result->exitStatus);
        self::assertSame(['pending' => 30, 'in_progress' => 50, 'supposedly_completed' => 0, 'completed' => 40, 'deferred' => 0, 'declined' => 0], array_map(static fn (array $duration): ?int => $duration['seconds'], $result->data['totals']));
        self::assertCount(8, $result->data['intervals']);
        self::assertSame(120, array_sum(array_column($result->data['totals'], 'seconds')));
        self::assertNotEmpty($result->data['historyEvidence']);
        self::assertSame([1, 2], $result->data['intervals'][0]['evidenceIds']);
    }

    public function testHistoricalCutoffUsesLaterEventsForTailWithoutAccruingTheirTime(): void
    {
        $result = $this->calculate(cutoff: 35);
        self::assertSame(0, $result->exitStatus);
        self::assertSame(5, $result->data['totals']['completed']['seconds']);
        self::assertSame(35, array_sum(array_column($result->data['totals'], 'seconds')));
        self::assertCount(7, $result->data['observedTransitions']);
        self::assertSame(0, $this->calculate(cutoff: 0)->data['totals']['pending']['seconds']);
        self::assertSame(5, $this->calculate(cutoff: 5)->data['totals']['pending']['seconds']);
    }

    public function testOffsetsAndDstAreAbsoluteInstants(): void
    {
        $events = $this->events();
        $events[1]['createdDate'] = '2026-10-01T03:00:10+03:00';
        $events[2]['createdDate'] = '2026-09-30T20:00:30-04:00';
        self::assertSame($this->calculate()->data['totals'], $this->calculate(array_reverse($events))->data['totals']);
        $from = CalendarInstant::parse('2026-11-01T01:30:00-04:00');
        $to = CalendarInstant::parse('2026-11-01T01:30:00-05:00');
        self::assertNotNull($from);
        self::assertNotNull($to);
        self::assertSame(3600, CalendarInstant::elapsed($from, $to));
    }

    public function testDuplicateAndNoOpDoNotCreateAnotherEntry(): void
    {
        $events = $this->events();
        $events[] = $events[1];
        $events[] = ['id' => 9, 'field' => 'REAL_STATUS', 'createdDate' => $this->time(20), 'from' => 'in_progress', 'to' => 'in_progress'];
        $result = $this->calculate($events);
        self::assertSame(0, $result->exitStatus);
        self::assertCount(8, $result->data['intervals']);
        self::assertSame($this->calculate()->data['totals'], $result->data['totals']);
    }

    public function testEqualTimesUseUniqueChainAndNeverIdOrdering(): void
    {
        $events = $this->events();
        $events[2]['createdDate'] = $events[1]['createdDate'];
        $events[2]['id'] = 99;
        $events[1]['id'] = 999;
        $result = $this->calculate(array_reverse($events));
        self::assertSame(0, $result->exitStatus);
        self::assertSame(0, $result->data['intervals'][1]['seconds']);
        $events[3]['createdDate'] = $events[1]['createdDate'];
        self::assertContains('ambiguous-event-order', $this->calculate($events)->meta['reasons']);
    }

    public function testPartialSourcesNeverTurnConsistentNeighboursIntoExactLowerBounds(): void
    {
        foreach (['history-navigation-unverified', 'page-error', 'history-budget-exhausted'] as $reason) {
            $result = $this->calculate(complete: false, reasons: [$reason]);
            self::assertSame(3, $result->exitStatus);
            self::assertFalse($result->meta['complete']);
            self::assertContains($reason, $result->meta['reasons']);
            self::assertSame([null], array_values(array_unique(array_column($result->data['totals'], 'seconds'))));
            self::assertNull($result->data['intervals'][1]['seconds']);
            self::assertSame(20, $result->data['intervals'][1]['elapsedBetweenEventsSeconds']);
            self::assertFalse($result->data['intervals'][1]['verified']);
            self::assertNull($result->data['intervals'][0]['enteredAt']);
        }
    }

    public function testAllEvidenceFailuresKeepTotalsUnknown(): void
    {
        $mutations = [
            static function (array $rows): array {
                unset($rows[0]);
                return $rows;
            },
            static function (array $rows): array {
                unset($rows[3]);
                return $rows;
            },
            static function (array $rows): array {
                unset($rows[7]);
                return $rows;
            },
            static function (array $rows): array {
                $rows[2]['from'] = 'declined';
                return $rows;
            },
            static function (array $rows): array {
                $rows[1]['to'] = 'future-status';
                return $rows;
            },
            static function (array $rows): array {
                $rows[1]['createdDate'] = '2026-02-30T00:00:00Z';
                return $rows;
            },
            static function (array $rows): array {
                $conflict = $rows[1];
                $conflict['to'] = 'completed';
                $rows[] = $conflict;
                return $rows;
            },
            static function (array $rows): array {
                $rows[0]['id'] = null;
                return $rows;
            },
            static function (array $rows): array {
                $rows[] = [...$rows[0], 'id' => 999];
                return $rows;
            },
        ];
        foreach ($mutations as $change) {
            $result = $this->calculate($change($this->events()));
            self::assertSame(3, $result->exitStatus, json_encode($result->meta));
            self::assertNotEmpty($result->meta['reasons']);
            self::assertSame([null], array_values(array_unique(array_column($result->data['totals'], 'seconds'))));
        }

        foreach ([['status' => 'pending'], ['changed' => $this->time(101)], ['statusChanged' => null], ['changed' => null]] as $after) {
            self::assertSame(3, $this->calculate(after: $after)->exitStatus);
        }
    }

    public function testUnconfirmedStatusAliasesDoNotDoubleCountAndStagesAreExcluded(): void
    {
        $events = $this->events();
        $events[] = [...$events[1], 'id' => 99, 'field' => 'STATUS'];
        $events[] = ['id' => 100, 'field' => 'STAGE_ID', 'createdDate' => $this->time(15), 'from' => 1, 'to' => 2];
        $result = $this->calculate($events);
        self::assertSame(0, $result->exitStatus);
        self::assertSame([1, 2, 99], $result->data['intervals'][0]['evidenceIds']);
        self::assertSame(120, array_sum(array_column($result->data['totals'], 'seconds')));
        $events = array_map(static fn (array $row): array => [...$row, 'field' => $row['field'] === 'REAL_STATUS' ? 'STATUS' : $row['field']], $this->events());
        self::assertContains('status-equivalence-unverified', $this->calculate($events)->meta['reasons']);
    }

    public function testNumericReviewReturnAndApprovalMappingUsesObservedCodeFour(): void
    {
        $rows = [['id' => 1, 'field' => 'NEW', 'createdDate' => $this->time(0), 'from' => null, 'to' => null]];
        foreach ([['2', '3'], ['3', '4'], ['4', '2'], ['2', '3'], ['3', '4'], ['4', '5']] as $key => [$from, $to]) {
            $rows[] = ['id' => $key + 2, 'field' => 'REAL_STATUS', 'createdDate' => $this->time(($key + 1) * 10), 'from' => $from, 'to' => $to];
        }

        $result = $this->calculate($rows, before: ['statusChanged' => $this->time(60)], after: ['statusChanged' => $this->time(60)]);
        self::assertSame(0, $result->exitStatus);
        self::assertSame(20, $result->data['totals']['supposedly_completed']['seconds']);
        self::assertSame(60, $result->data['totals']['completed']['seconds']);
        self::assertSame(120, array_sum(array_column($result->data['totals'], 'seconds')));
        $statusOnly = array_map(static fn (array $row): array => [...$row, 'field' => $row['field'] === 'REAL_STATUS' ? 'STATUS' : $row['field']], $rows);
        $partial = $this->calculate($statusOnly, before: ['statusChanged' => $this->time(60)], after: ['statusChanged' => $this->time(60)]);
        self::assertSame(0, $partial->exitStatus);
        self::assertNotContains('unknown-history-status', $partial->meta['reasons']);
        self::assertSame($result->data['totals'], $partial->data['totals']);
    }

    public function testStableRest3CreationExcludesEarlierLivesAndUsesCreatedBeforeNewLog(): void
    {
        $rows = $this->events();
        $rows[0]['createdDate'] = $this->time(1); // NEW was recorded one second after actual creation.
        array_unshift(
            $rows,
            ['id' => 100, 'field' => 'NEW', 'createdDate' => '2026-02-23T00:00:00Z'],
            ['id' => 101, 'field' => 'REAL_STATUS', 'createdDate' => '2026-02-23T00:01:00Z', 'from' => 'pending', 'to' => 'declined'],
            ['id' => 102, 'field' => 'DELETE', 'createdDate' => '2026-02-23T00:02:00Z'],
        );
        $snapshot = ['status' => 'completed', 'statusChanged' => $this->time(100), 'changed' => $this->time(100)];
        $creation = ['before' => ['id' => 1, 'created' => $this->time(0)], 'after' => ['id' => 1, 'created' => '2026-10-01T03:00:00+03:00']];
        $result = (new StatusDurationCalculator())->calculate(1, $rows, true, [], $snapshot, $snapshot, new \DateTimeImmutable($this->time(120)), $creation);
        self::assertSame(0, $result->exitStatus);
        self::assertSame($this->calculate()->data['totals'], $result->data['totals']);
        self::assertSame($this->time(0), $result->data['intervals'][0]['enteredAt']);
        self::assertSame([100, 101, 102], array_column($result->data['excludedHistoryEvidence'], 'id'));
        self::assertCount(11, $result->data['historyEvidence']);
        self::assertCount(7, $result->data['observedTransitions']);
        self::assertSame('tasks.task.list', $result->data['creationBoundary']['source']);
        $partial = (new StatusDurationCalculator())->calculate(1, $rows, false, ['history-navigation-unverified'], $snapshot, $snapshot, new \DateTimeImmutable($this->time(120)), $creation);
        self::assertSame(['history-navigation-unverified'], $partial->meta['reasons']);
        self::assertSame([null], array_values(array_unique(array_column($partial->data['totals'], 'seconds'))));
    }

    public function testCreationIdentityRaceAndUnclassifiableEventsKeepTotalsPartial(): void
    {
        $snapshot = ['status' => 'completed', 'statusChanged' => $this->time(100), 'changed' => $this->time(100)];
        $before = ['id' => 1, 'created' => $this->time(0)];
        foreach ([[], ['id' => 2, 'created' => $this->time(0)], ['id' => 1, 'created' => $this->time(1)], ['id' => 1, 'created' => '2026-10-01T00:00:00']] as $after) {
            $result = (new StatusDurationCalculator())->calculate(1, $this->events(), true, [], $snapshot, $snapshot, new \DateTimeImmutable($this->time(120)), ['before' => $before, 'after' => $after]);
            self::assertContains('current-task-creation-unverified', $result->meta['reasons']);
            self::assertSame([], $result->data['excludedHistoryEvidence']);
            self::assertSame([null], array_values(array_unique(array_column($result->data['totals'], 'seconds'))));
        }

        foreach ([$this->time(0), 'invalid-date'] as $date) {
            $rows = [...$this->events(), ['id' => 90, 'field' => 'DELETE', 'createdDate' => $date]];
            $result = (new StatusDurationCalculator())->calculate(1, $rows, true, [], $snapshot, $snapshot, new \DateTimeImmutable($this->time(120)), ['before' => $before, 'after' => $before]);
            self::assertSame(3, $result->exitStatus);
            self::assertSame([], $result->data['excludedHistoryEvidence']);
        }
    }

    public function testNegativeMetaStatusIsNeverAcceptedAsLifecycle(): void
    {
        $rows = $this->events();
        $rows[1] = [...$rows[1], 'field' => 'STATUS', 'from' => '2', 'to' => '-1'];
        $result = $this->calculate($rows);
        self::assertSame(3, $result->exitStatus);
        self::assertContains('unknown-history-status', $result->meta['reasons']);
        self::assertSame([null], array_values(array_unique(array_column($result->data['totals'], 'seconds'))));
    }

    public function testMissingInitialStatusAndAsOfBeforeProvenCreation(): void
    {
        self::assertContains('initial-status-unverified', $this->calculate([$this->events()[0]])->meta['reasons']);
        $this->expectException(Failure::class);
        $this->calculate(cutoff: -1);
    }

    public function testWeekendExclusionAndExactAggregationPreserveServerDates(): void
    {
        $created = '2026-10-16T23:59:58.200000+03:00';
        $last = '2026-10-19T00:00:00+03:00';
        $rows = [
            ['id' => 1, 'field' => 'NEW', 'createdDate' => $created],
            ['id' => 2, 'field' => 'REAL_STATUS', 'createdDate' => '2026-10-16T23:59:59+03:00', 'from' => 'pending', 'to' => 'in_progress'],
            ['id' => 3, 'field' => 'REAL_STATUS', 'createdDate' => '2026-10-17T00:00:00+03:00', 'from' => 'in_progress', 'to' => 'completed'],
            ['id' => 4, 'field' => 'REAL_STATUS', 'createdDate' => $last, 'from' => 'completed', 'to' => 'pending'],
        ];
        $snapshot = ['status' => 'pending', 'statusChanged' => $last, 'changed' => $last];
        $asOf = new \DateTimeImmutable('2026-10-18T21:00:00.500000Z');
        $result = (new StatusDurationCalculator())->calculate(1, $rows, true, [], $snapshot, $snapshot, $asOf);
        self::assertSame(0, $result->exitStatus);
        self::assertSame(['seconds' => 1, 'hours' => 0.0, 'days' => 0.0], $result->data['totals']['pending']);
        self::assertSame(2, $result->data['intervals'][0]['seconds'] + $result->data['intervals'][3]['seconds']);
        self::assertSame(0, $result->data['totals']['completed']['seconds']);
        self::assertSame(172800, $result->data['intervals'][2]['elapsedBetweenEventsSeconds']);
        self::assertSame($created, $result->data['intervals'][0]['enteredAt']);
        self::assertSame('2026-10-18T21:00:00.500000+00:00', $result->data['intervals'][3]['leftAt']);
        self::assertSame('+03:00', $result->meta['calendar']['utcOffset']);
        $partial = (new StatusDurationCalculator())->calculate(1, $rows, false, ['page-error'], $snapshot, $snapshot, $asOf);
        foreach ($partial->data['totals'] as $duration) {
            self::assertSame(['seconds' => null, 'hours' => null, 'days' => null], $duration);
        }
    }
}
