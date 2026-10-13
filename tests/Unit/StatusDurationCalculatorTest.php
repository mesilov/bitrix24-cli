<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\{CalendarInstant, StatusDurationCalculator};
use PHPUnit\Framework\TestCase;

final class StatusDurationCalculatorTest extends TestCase
{
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
        self::assertSame(['pending' => 30, 'in_progress' => 50, 'supposedly_completed' => 0, 'completed' => 40, 'deferred' => 0, 'declined' => 0], $result->data['totals']);
        self::assertCount(8, $result->data['intervals']);
        self::assertSame(120, array_sum($result->data['totals']));
        self::assertNotEmpty($result->data['historyEvidence']);
        self::assertSame([1, 2], $result->data['intervals'][0]['evidenceIds']);
    }

    public function testHistoricalCutoffUsesLaterEventsForTailWithoutAccruingTheirTime(): void
    {
        $result = $this->calculate(cutoff: 35);
        self::assertSame(0, $result->exitStatus);
        self::assertSame(5, $result->data['totals']['completed']);
        self::assertSame(35, array_sum($result->data['totals']));
        self::assertCount(7, $result->data['observedTransitions']);
        self::assertSame(0, $this->calculate(cutoff: 0)->data['totals']['pending']);
        self::assertSame(5, $this->calculate(cutoff: 5)->data['totals']['pending']);
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
            self::assertSame([null], array_values(array_unique($result->data['totals'])));
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
            self::assertSame([null], array_values(array_unique($result->data['totals'])));
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
        self::assertSame(120, array_sum($result->data['totals']));
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
        self::assertSame(20, $result->data['totals']['supposedly_completed']);
        self::assertSame(60, $result->data['totals']['completed']);
        self::assertSame(120, array_sum($result->data['totals']));
        $statusOnly = array_map(static fn (array $row): array => [...$row, 'field' => $row['field'] === 'REAL_STATUS' ? 'STATUS' : $row['field']], $rows);
        $partial = $this->calculate($statusOnly, before: ['statusChanged' => $this->time(60)], after: ['statusChanged' => $this->time(60)]);
        self::assertSame(3, $partial->exitStatus);
        self::assertNotContains('unknown-history-status', $partial->meta['reasons']);
        self::assertSame([null], array_values(array_unique($partial->data['totals'])));
    }

    public function testMissingInitialStatusAndAsOfBeforeProvenCreation(): void
    {
        self::assertContains('initial-status-unverified', $this->calculate([$this->events()[0]])->meta['reasons']);
        $this->expectException(Failure::class);
        $this->calculate(cutoff: -1);
    }
}
