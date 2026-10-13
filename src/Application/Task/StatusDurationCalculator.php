<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;

/** Calendar occupancy over [enteredAt, leftAt). Elapsed observations are not lower bounds. */
final class StatusDurationCalculator
{
    public function calculate(int $taskId, array $events, bool $historyComplete, array $reasons, array $before, array $after, \DateTimeImmutable $asOf): OperationResult
    {
        if (!$historyComplete && $reasons === []) {
            $reasons[] = 'history-incomplete';
        }

        $unique = [];
        foreach ($events as $event) {
            $id = $event['id'] ?? null;
            if (!is_int($id) || $id < 1) {
                $reasons[] = 'missing-history-id';
            } elseif (isset($unique[$id])) {
                if ($unique[$id] !== $event) {
                    $reasons[] = 'conflicting-history-id';
                }

                continue;
            }

            $unique[$id ?? count($unique)] = $event;
        }

        $creation = [];
        $real = [];
        $status = [];
        $observed = [];
        foreach ($unique as $event) {
            $field = $event['field'] ?? null;
            if (!in_array($field, ['NEW', 'DELETE', 'REAL_STATUS', 'STATUS'], true)) {
                continue;
            }

            $date = CalendarInstant::parse($event['createdDate'] ?? null);
            if (!$date instanceof \DateTimeImmutable) {
                $reasons[] = 'invalid-history-date';
            }

            if ($field === 'NEW') {
                $creation[] = ['event' => $event, 'date' => $date];
                continue;
            }

            if ($field === 'DELETE') {
                $reasons[] = 'deleted-task-history';
                continue;
            }

            $transition = [...$event, 'source' => 'tasks.task.history.list', 'apiVersion' => '1.0', 'fromStatus' => $this->status($event['from'] ?? null), 'toStatus' => $this->status($event['to'] ?? null), 'evidenceIds' => [$event['id'] ?? null]];
            $observed[] = $transition;
            if ($transition['fromStatus'] === null || $transition['toStatus'] === null) {
                $reasons[] = 'unknown-history-status';
            }

            if (!$date instanceof \DateTimeImmutable || $transition['fromStatus'] === null || $transition['toStatus'] === null) {
                continue;
            }

            $transition['date'] = $date;
            if ($field === 'REAL_STATUS') {
                $real[] = $transition;
            } else {
                $status[] = $transition;
            }
        }

        // STATUS-only values are observations: the live receipt does not prove REAL_STATUS equivalence.
        foreach ($status as $alias) {
            $matched = false;
            foreach ($real as &$canonical) {
                if ($canonical['date'] == $alias['date'] && $canonical['fromStatus'] === $alias['fromStatus'] && $canonical['toStatus'] === $alias['toStatus']) {
                    $canonical['evidenceIds'][] = $alias['id'];
                    $matched = true;
                    break;
                }
            }

            unset($canonical);
            if (!$matched) {
                $reasons[] = 'status-equivalence-unverified';
            }
        }

        $transitions = $real === [] ? $status : $real;
        usort($transitions, static fn (array $a, array $b): int => $a['date'] <=> $b['date']);
        $groups = [];
        foreach ($transitions as $transition) {
            $groups[$transition['date']->format('U.u')][] = $transition;
        }

        $ordered = [];
        $current = null;
        foreach ($groups as $group) {
            $paths = [];
            $this->paths($group, $current, [], $paths);
            if (count($paths) !== 1) {
                $reasons[] = $paths === [] ? 'status-chain-gap' : 'ambiguous-event-order';
                // Preserve observations without pretending their input/ID order is chronological.
                $current = null;
                continue;
            }

            foreach ($paths[0] as $transition) {
                $ordered[] = $transition;
                $current = $transition['toStatus'];
            }
        }

        $created = count($creation) === 1 ? $creation[0]['date'] : null;
        if (!$created instanceof \DateTimeImmutable) {
            $reasons[] = count($creation) > 1 ? 'ambiguous-creation-boundary' : 'missing-creation-boundary';
        }

        if ($historyComplete && $created instanceof \DateTimeImmutable && $asOf < $created) {
            throw Failure::usage('--as-of precedes the proven creation of the task.');
        }

        $initial = $ordered[0]['fromStatus'] ?? null;
        if ($initial === null) {
            $reasons[] = 'initial-status-unverified';
        }

        if ($created instanceof \DateTimeImmutable && isset($ordered[0]) && $ordered[0]['date'] < $created) {
            $reasons[] = 'transition-before-creation';
        }

        $fields = ['status', 'statusChanged', 'changed'];
        if (array_intersect_key($before, array_flip($fields)) !== array_intersect_key($after, array_flip($fields))) {
            $reasons[] = 'concurrent-task-change';
        }

        $last = $ordered === [] ? null : $ordered[array_key_last($ordered)];
        $tailDate = CalendarInstant::parse($after['statusChanged'] ?? null);
        if ($last === null || $last['toStatus'] !== ($after['status'] ?? null) || !$tailDate instanceof \DateTimeImmutable || $tailDate != $last['date'] || !CalendarInstant::parse($before['changed'] ?? null) instanceof \DateTimeImmutable || !CalendarInstant::parse($after['changed'] ?? null) instanceof \DateTimeImmutable) {
            $reasons[] = 'tail-state-unverified';
        }

        $reasons = array_values(array_unique($reasons));
        $complete = $historyComplete && $reasons === [];
        $intervals = [];
        $entered = $created instanceof \DateTimeImmutable && $initial !== null && $complete ? $created : null;
        $entryText = $entered instanceof \DateTimeImmutable ? $creation[0]['event']['createdDate'] : null;
        $entryIds = $entered instanceof \DateTimeImmutable ? [$creation[0]['event']['id']] : [];
        $occupancy = $initial;
        foreach ($ordered as $transition) {
            if ($transition['date'] > $asOf) {
                break;
            }

            if ($transition['fromStatus'] === $transition['toStatus']) {
                continue;
            }

            $intervals[] = $this->interval($occupancy, $entered, $transition['date'], $entryText, $transition['createdDate'], $complete, [...$entryIds, ...$transition['evidenceIds']]);
            $occupancy = $transition['toStatus'];
            $entered = $transition['date'];
            $entryText = $transition['createdDate'];
            $entryIds = $transition['evidenceIds'];
        }

        $intervals[] = $this->interval($occupancy, $entered, $asOf, $entryText, $asOf->format('Y-m-d\TH:i:s.uP'), $complete, $entryIds);
        $totals = array_fill_keys(array_column(TaskStatus::cases(), 'value'), $complete ? 0 : null);
        if ($complete) {
            foreach ($intervals as $interval) {
                $totals[$interval['status']] += $interval['seconds'];
            }
        }

        return new OperationResult('task-status-durations', [
            'taskId' => $taskId, 'asOf' => $asOf->format('Y-m-d\TH:i:s.uP'), 'intervals' => $intervals, 'totals' => $totals, 'observedTransitions' => $observed,
            'unknownBoundaries' => $complete ? [] : $reasons,
            'historyEvidence' => array_values(array_filter($events, static fn (array $event): bool => in_array($event['field'] ?? null, ['NEW', 'DELETE', 'REAL_STATUS', 'STATUS'], true))),
        ], ['complete' => $complete, 'historyComplete' => $historyComplete, 'reasons' => $reasons, 'reason' => $reasons === [] ? null : implode(', ', $reasons), 'semantics' => 'calendar-seconds'], $complete ? 0 : 3);
    }

    private function status(mixed $value): ?string
    {
        if (is_string($value) && TaskStatus::tryFrom($value) !== null) {
            return $value;
        }

        // Numeric mappings observed with independent REST3 readbacks; other codes stay unknown.
        return is_int($value) || is_string($value) ? (['2' => 'pending', '3' => 'in_progress', '5' => 'completed'][(string) $value] ?? null) : null;
    }

    private function paths(array $remaining, ?string $status, array $path, array &$paths): void
    {
        if (count($paths) >= 2) {
            return;
        }

        if (count($remaining) > 8) {
            $paths = [[], []];
            return;
        }

        if ($remaining === []) {
            $paths[] = $path;
            return;
        }

        foreach ($remaining as $key => $transition) {
            if ($status !== null && $transition['fromStatus'] !== $status) {
                continue;
            }

            $rest = $remaining;
            unset($rest[$key]);
            $this->paths($rest, $transition['toStatus'], [...$path, $transition], $paths);
        }
    }

    private function interval(?string $status, ?\DateTimeImmutable $entered, \DateTimeImmutable $left, ?string $entryText, string $leftText, bool $verified, array $ids): array
    {
        $elapsed = !$entered instanceof \DateTimeImmutable || $entered > $left ? null : CalendarInstant::elapsed($entered, $left);
        return ['status' => $status, 'enteredAt' => $entryText, 'leftAt' => $leftText, 'seconds' => $verified ? $elapsed : null, 'elapsedBetweenEventsSeconds' => $elapsed, 'verified' => $verified, 'evidenceIds' => array_values(array_unique($ids))];
    }
}
