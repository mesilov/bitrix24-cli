<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Application\Task\{CalendarInstant, HistoryScanner, LifecycleAction};
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest1TaskHistoryAdapter;
use Bitrix24\CLI\Tests\Support\LiveTaskLifecycleTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:status:durations')]
final class StatusDurationsTaskCommandTest extends LiveTaskLifecycleTestCase
{
    public function testRawHistoryIsolationAndMoreThanFiftyKnownChanges(): void
    {
        $runtimeState = new \Bitrix24\CLI\Application\RuntimeState();
        $core = (new \Bitrix24\CLI\Infrastructure\Connection\B24ClientProvider(new \Bitrix24\CLI\Infrastructure\Connection\EnvConnectionResolver($this->root()), $runtimeState))->get()->core;
        $a = $this->createTask();
        $b = $this->createTask();
        $receipt = ['taskIds' => ['A' => $a, 'B' => $b], 'apiVersions' => ['1.0', '3.0'], 'sourceCompletenessProven' => false];
        try {
            $capture = static function (int $id, array $parameters = []) use ($core): array {
                $response = $core->call('tasks.task.history.list', ['taskId' => $id, ...$parameters], \Bitrix24\SDK\Core\Contracts\ApiVersion::v1);
                $wire = json_decode($response->getHttpResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
                $dto = $response->getResponseData();
                self::assertSame($wire['result']['list'], $dto->getResult()['list'], 'SDK must preserve server history, including any earlier lifetime.');
                return ['parameters' => $parameters, 'rootKeys' => array_keys($wire), 'resultKeys' => array_keys($wire['result']), 'next' => $dto->getPagination()->getNextItem(), 'total' => $dto->getPagination()->getTotal(), 'events' => array_map(static fn (array $row): array => [...array_intersect_key($row, array_flip(['id', 'createdDate', 'field', 'value'])), 'userId' => $row['user']['id'] ?? null], $wire['result']['list'])];
            };
            $receipt['baselineA'] = $capture($a);
            $receipt['baselineB'] = $capture($b);
            self::assertSame([], array_intersect(array_column($receipt['baselineA']['events'], 'id'), array_column($receipt['baselineB']['events'], 'id')));
            $expected = [];
            $prefix = $this->fixtureTitle();
            for ($i = 1; $i <= 60; $i++) {
                $title = $prefix . '-change-' . $i;
                $this->tasks->update($a, ['title' => $title]);
                $expected[] = $title;
            }

            $receipt['knownTitleUpdates'] = $expected;
            $receipt['creationReadback'] = $this->tasks->page([['id', '=', $a]], ['id', 'created', 'status'], ['id' => 'ASC'], 0, 2)->items;
            $receipt['probes'] = [];
            foreach ([[], ['start' => 0], ['start' => 50], ['start' => 10000], ['start' => -1], ['filter' => ['FIELD' => 'NEW']], ['filter' => ['FIELD' => 'STATUS']], ['order' => ['createdDate' => 'DESC']]] as $parameters) {
                $receipt['probes'][] = $capture($a, $parameters);
            }

            $all = $receipt['probes'][0]['events'];
            $actual = array_column(array_column(array_values(array_filter($all, static fn (array $row): bool => $row['field'] === 'TITLE')), 'value'), 'to');
            self::assertSame([], array_values(array_diff($expected, $actual)), 'Known fixture completeness is tested independently of navigation metadata.');
            self::assertGreaterThan(50, count($all));
            $receipt['unchangedB'] = $capture($b);
            self::assertSame($receipt['baselineB']['events'], $receipt['unchangedB']['events'], 'Writes to A must not alter B history.');
            $adapter = (new Rest1TaskHistoryAdapter($this->api))->page($a, [], 0);
            self::assertSame(array_column($all, 'id'), array_column($adapter->items, 'id'), 'Adapter must preserve server history IDs.');
            $receipt['commandResult'] = $this->invoke(['task:status:durations', $a], [0, 3]);
            if (!$receipt['commandResult']['meta']['complete']) {
                self::assertSame([null], array_values(array_unique($receipt['commandResult']['data']['totals'])));
            }

            self::assertNotContains('write', array_column($receipt['commandResult']['meta']['apiCalls'], 'effect'));
        } finally {
            $receipt['cleanupEvidence'] = 'portal-evidence.jsonl records owned task deletion in tearDown';
            file_put_contents($this->root() . '/var/cache/wire-history-investigation.json', json_encode($receipt, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        }
    }

    private function reopenedFixture(): int
    {
        $id = $this->createTask(fields: ['needsControl' => false, 'requireResult' => false]);
        foreach ([LifecycleAction::Start, LifecycleAction::Complete, LifecycleAction::Renew, LifecycleAction::Start, LifecycleAction::Complete, LifecycleAction::Renew, LifecycleAction::Complete] as $action) {
            // Keep this arithmetic fixture's boundaries in distinct seconds; equal-time ordering is tested offline.
            usleep(1100000);
            $this->transition($id, $action);
            $expected = match ($action) {
                LifecycleAction::Start => 'in_progress', LifecycleAction::Complete => 'completed', default => 'pending'
            };
            self::assertSame($expected, $this->card($id, ['status'])['status']);
        }

        return $id;
    }

    public function testRejectedReviewThenSuccessfulCompletionRetainsIncrementalHistory(): void
    {
        $this->reviewCycle(1100000, 'review-cycle-history.json');
    }

    public function testFiveSecondReviewCycleShowsStatusDurationResult(): void
    {
        $this->reviewCycle(5000000, 'five-second-review-cycle-history.json', true);
    }

    private function reviewCycle(int $delayMicroseconds, string $receiptName, bool $waitInCompleted = false): void
    {
        [$id, $executorApi] = $this->controlledTask();
        $rest1TaskLifecycleAdapter = new \Bitrix24\CLI\Infrastructure\Bitrix24\Rest1TaskLifecycleAdapter($executorApi);
        $snapshots = [];
        $seen = [];
        $expectedTransitions = [];
        $initial = $this->card($id, ['status', 'statusChanged', 'changed', 'needsControl', 'creator.id', 'responsible.id']);
        self::assertSame('pending', $initial['status']);
        self::assertTrue($initial['needsControl']);
        self::assertNotSame($initial['creator']['id'], $initial['responsible']['id']);
        $receipt = ['taskId' => $id, 'apiVersions' => ['1.0', '3.0'], 'initial' => $initial, 'delaySeconds' => $delayMicroseconds / 1000000];
        try {
            $receipt['creationBefore'] = $this->creationReadback($id);
            $baseline = $this->captureHistory($id);
            $receipt['baseline'] = $baseline;
            $seen = array_column($baseline['events'], null, 'id');
            $previous = '2';
            foreach ([
                [LifecycleAction::Start, 'in_progress', '3'],
                [LifecycleAction::Complete, 'supposedly_completed', '4'],
                [LifecycleAction::Disapprove, 'pending', '2'],
                [LifecycleAction::Start, 'in_progress', '3'],
                [LifecycleAction::Complete, 'supposedly_completed', '4'],
                [LifecycleAction::Approve, 'completed', '5'],
            ] as [$action, $expected, $rawStatus]) {
                $started = hrtime(true);
                usleep($delayMicroseconds);
                $delayElapsedSeconds = (hrtime(true) - $started) / 1000000000;
                self::assertGreaterThanOrEqual($delayMicroseconds / 1000000, $delayElapsedSeconds);
                $beforeAction = $this->card($id, ['status', 'statusChanged', 'changed']);
                if (in_array($action, [LifecycleAction::Disapprove, LifecycleAction::Approve], true)) {
                    $this->transition($id, $action);
                } else {
                    $rest1TaskLifecycleAdapter->transition($id, $action);
                }

                $card = $this->card($id, ['status', 'statusChanged', 'changed']);
                $history = $this->captureHistory($id);
                $new = array_values(array_filter($history['events'], static fn (array $row): bool => !isset($seen[$row['id']])));
                $snapshots[] = ['action' => $action->value, 'delayElapsedSeconds' => $delayElapsedSeconds, 'beforeCard' => $beforeAction, 'expectedStatus' => $expected, 'card' => $card, 'history' => $history, 'newEvents' => $new];
                $receipt['steps'] = $snapshots;
                self::assertSame($expected, $card['status']);
                $statusEvents = array_values(array_filter($new, static fn (array $row): bool => in_array($row['field'], ['STATUS', 'REAL_STATUS'], true)));
                self::assertNotEmpty($statusEvents, 'Each controlled action needs an independently captured history event.');
                $matching = array_values(array_filter($statusEvents, static fn (array $row): bool => (string) ($row['value']['from'] ?? '') === $previous && (string) ($row['value']['to'] ?? '') === $rawStatus));
                self::assertNotEmpty($matching, 'The captured history must confirm the independently read status transition.');
                $expectedTransitions[] = [$previous, $rawStatus];
                $previous = $rawStatus;
                $seen = array_column($history['events'], null, 'id') + $seen;
            }

            $receipt['expectedRawTransitions'] = $expectedTransitions;
            if ($waitInCompleted) {
                $started = hrtime(true);
                usleep($delayMicroseconds);
                $receipt['completedDelayElapsedSeconds'] = (hrtime(true) - $started) / 1000000000;
                self::assertGreaterThanOrEqual($delayMicroseconds / 1000000, $receipt['completedDelayElapsedSeconds']);
            }

            $result = $this->invoke(['task:status:durations', $id], [0, 3]);
            $receipt['commandResult'] = $result;
            $receipt['creationAfter'] = $this->creationReadback($id);
            if ($waitInCompleted) {
                $receipt['controlledResult'] = $this->controlledArithmetic($receipt);
            }

            self::assertNotContains('write', array_column($result['meta']['apiCalls'], 'effect'));
            if (!$result['meta']['complete']) {
                self::assertSame([null], array_values(array_unique($result['data']['totals'])));
                self::assertNotContains('unknown-history-status', $result['meta']['reasons']);
            }

            self::assertSame('completed', $this->card($id, ['status'])['status']);
        } finally {
            // Save evidence even when assertions fail; inherited tearDown always removes the owned fixture.
            $receipt['cleanupEvidence'] = 'portal-evidence.jsonl records owned task deletion in tearDown';
            file_put_contents($this->root() . '/var/cache/' . $receiptName, json_encode($receipt, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        }
    }

    private function creationReadback(int $id): array
    {
        $rows = $this->tasks->page([['id', '=', $id]], ['id', 'created'], ['id' => 'ASC'], 0, 2)->items;
        self::assertCount(1, $rows);
        self::assertSame($id, $rows[0]['id']);
        self::assertNotNull(CalendarInstant::parse($rows[0]['created']));
        return $rows[0];
    }

    private function controlledArithmetic(array $receipt): array
    {
        self::assertSame($receipt['creationBefore'], $receipt['creationAfter']);
        $created = new \DateTimeImmutable($receipt['creationBefore']['created']);
        $known = array_values(array_filter($receipt['baseline']['events'], static fn (array $row): bool => new \DateTimeImmutable($row['createdDate']) >= $created));
        self::assertCount(1, $known, 'The controlled current lifetime starts with one known NEW.');
        self::assertSame('NEW', $known[0]['field']);
        $previousTime = $created;
        $previousStatus = $receipt['initial']['status'];
        $expected = array_fill_keys(array_column(\Bitrix24\CLI\Application\Task\TaskStatus::cases(), 'value'), 0);
        foreach ($receipt['steps'] as $step) {
            self::assertSame($previousStatus, $step['beforeCard']['status']);
            self::assertCount(1, $step['newEvents'], 'Unexpected fixture activity cannot justify controlled completeness.');
            self::assertSame($step['card']['statusChanged'], $step['newEvents'][0]['createdDate']);
            $time = new \DateTimeImmutable($step['card']['statusChanged']);
            $expected[$previousStatus] += CalendarInstant::elapsed($previousTime, $time);
            $previousStatus = $step['card']['status'];
            $previousTime = $time;
            $known[] = $step['newEvents'][0];
        }

        $current = array_values(array_filter($receipt['steps'][array_key_last($receipt['steps'])]['history']['events'], static fn (array $row): bool => new \DateTimeImmutable($row['createdDate']) >= $created));
        self::assertSame(array_column($known, 'id'), array_column($current, 'id'), 'The final known fixture ID set must match every captured action.');
        $asOf = new \DateTimeImmutable($receipt['commandResult']['data']['asOf']);
        $expected[$previousStatus] += CalendarInstant::elapsed($previousTime, $asOf);
        $events = array_map(static fn (array $row): array => [
            'id' => $row['id'], 'createdDate' => $row['createdDate'], 'field' => $row['field'], 'userId' => $row['userId'], 'from' => $row['value']['from'], 'to' => $row['value']['to'],
        ], $receipt['steps'][array_key_last($receipt['steps'])]['history']['events']);
        $snapshots = $receipt['commandResult']['meta']['snapshots'];
        // Coverage comes from the isolated action journal above, not invented API pagination.
        $result = (new \Bitrix24\CLI\Application\Task\StatusDurationCalculator())->calculate($receipt['taskId'], $events, true, [], $snapshots['before'], $snapshots['after'], $asOf, ['before' => $receipt['creationBefore'], 'after' => $receipt['creationAfter']]);
        self::assertSame(0, $result->exitStatus, json_encode($result->meta));
        self::assertSame($expected, $result->data['totals']);
        self::assertEqualsWithDelta(CalendarInstant::elapsed($created, $asOf), array_sum($result->data['totals']), 0.000001);
        return ['data' => $result->data, 'meta' => [...$result->meta, 'coverage' => 'controlled-fixture-actions', 'knownCurrentEventIds' => array_column($known, 'id')], 'exitStatus' => $result->exitStatus];
    }

    private function captureHistory(int $id): array
    {
        $response = $this->api->call('tasks.task.history.list', 1, ['taskId' => $id, 'start' => 0, 'order' => ['createdDate' => 'ASC']]);
        $rows = $response->result['list'] ?? null;
        self::assertIsArray($rows);
        return ['next' => $response->next, 'total' => $response->total, 'events' => array_map(static fn (array $row): array => [...array_intersect_key($row, array_flip(['id', 'createdDate', 'field', 'value'])), 'userId' => $row['user']['id'] ?? null], $rows)];
    }

    private function evidence(int $id): \Bitrix24\CLI\Application\OperationResult
    {
        return (new HistoryScanner(new Rest1TaskHistoryAdapter($this->api)))->scan($id, ['order' => ['createdDate' => 'ASC']], true);
    }

    #[Group('happy-path')]
    public function testExactArithmeticAgainstIndependentSdkReopenReadback(): void
    {
        $id = $this->reopenedFixture();
        $history = $this->evidence($id);
        $creation = $this->creationReadback($id);
        $created = new \DateTimeImmutable($creation['created']);
        $current = array_values(array_filter($history->data['items'], static fn (array $row): bool => new \DateTimeImmutable($row['createdDate']) >= $created));
        $real = array_values(array_filter($current, static fn (array $row): bool => $row['field'] === 'REAL_STATUS'));
        $events = $real === [] ? array_values(array_filter($current, static fn (array $row): bool => $row['field'] === 'STATUS')) : $real;
        if (!$history->meta['complete'] || count($events) !== 7) {
            self::markTestIncomplete('Exact CLI arithmetic is unproven: requires verified source completeness and seven supported current-lifetime transitions. Controlled-action arithmetic is separate evidence.');
        }

        $asOf = new \DateTimeImmutable();
        $result = $this->invoke(['task:status:durations', $id, '--as-of', $asOf->format(DATE_ATOM)]);
        $cutoff = new \DateTimeImmutable($result['data']['asOf']);
        $previous = $created;
        $totals = ['pending' => 0, 'in_progress' => 0, 'completed' => 0];
        $mapping = ['2' => 'pending', '3' => 'in_progress', '5' => 'completed'];
        $status = 'pending';
        foreach ($events as $event) {
            self::assertSame($status, $mapping[(string) $event['from']] ?? $event['from']);
            $time = new \DateTimeImmutable($event['createdDate']);
            $totals[$status] += CalendarInstant::elapsed($previous, $time);
            $status = $mapping[(string) $event['to']] ?? $event['to'];
            $previous = $time;
        }

        $totals[$status] += CalendarInstant::elapsed($previous, $cutoff);
        foreach ($totals as $status => $seconds) {
            self::assertSame($seconds, $result['data']['totals'][$status]);
        }

        self::assertCount(8, $result['data']['intervals']);
        self::assertTrue($result['meta']['complete']);
    }

    public function testUnverifiedNavigationReturnsNullTotalsWithReopenEvidence(): void
    {
        $id = $this->reopenedFixture();
        $history = $this->evidence($id);
        if (!in_array('history-navigation-unverified', $history->meta['reasons'], true)) {
            self::markTestSkipped('This portal now supplies navigation; partial navigation is covered by offline fixtures.');
        }

        $result = $this->invoke(['task:status:durations', $id], [3]);
        self::assertFalse($result['meta']['historyComplete']);
        self::assertFalse($result['meta']['complete']);
        self::assertContains('history-navigation-unverified', $result['meta']['reasons']);
        self::assertSame('partial-result', $result['error']['code']);
        self::assertSame([null], array_values(array_unique($result['data']['totals'])));
        self::assertGreaterThanOrEqual(7, count($result['data']['observedTransitions']));
        self::assertNotContains('write', array_column($result['meta']['apiCalls'], 'effect'));
        self::assertSame('completed', $this->card($id, ['status'])['status']);
    }
}
