<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\{Clock, Failure, OperationResult, RuntimeState};
use Bitrix24\CLI\Application\Task\Port\WeeklyReportTaskGateway;
use Bitrix24\CLI\Application\Task\Request\ReportTasksRequest;

final readonly class WeeklyTaskReport
{
    private const STATUSES = ['closedTasks' => 'completed', 'awaitingControlTasks' => 'supposedly_completed', 'inProgressTasks' => 'in_progress'];

    public function __construct(private WeeklyReportTaskGateway $tasks, private Clock $clock, private RuntimeState $state)
    {
    }

    public function collect(ReportTasksRequest $request): OperationResult
    {
        $scope = ['GROUP_ID' => $request->groupId, ...($request->responsibleId === null ? [] : ['RESPONSIBLE_ID' => $request->responsibleId])];
        $from = $request->week->from->format(DATE_ATOM);
        $to = $request->week->effectiveTo()->format('Y-m-d\TH:i:s.uP');
        $filters = [
            'closedTasks' => ['REAL_STATUS' => 5, '>=CLOSED_DATE' => $from, '<CLOSED_DATE' => $to],
            'awaitingControlTasks' => ['REAL_STATUS' => 4],
            'newTasks' => ['>=CREATED_DATE' => $from, '<CREATED_DATE' => $to],
            'inProgressTasks' => ['REAL_STATUS' => 3],
        ];
        $data = [];
        foreach (array_keys($filters) as $section) {
            $data[$section] = [...(in_array($section, ['closedTasks', 'awaitingControlTasks'], true) ? ['items' => []] : []), 'count' => null, 'observedCount' => 0, 'complete' => false, 'reasons' => ['not-scanned']];
        }

        $scanned = 0;
        $observations = [];
        foreach ($filters as $section => $filter) {
            if ($scanned >= $request->maxScan) {
                $data[$section]['reasons'][] = 'scan-budget-exhausted';
                continue;
            }

            $data[$section]['reasons'] = [];
            $offset = 0;
            $seen = [];
            $matches = [];
            $finished = false;
            do {
                $this->state->checkCancellation();
                try {
                    $page = $this->tasks->reportPage([...$scope, ...$filter], $offset);
                } catch (Failure $failure) {
                    if ($failure->exitStatus === 130 || $scanned === 0) {
                        throw $failure;
                    }

                    $data[$section]['reasons'][] = 'page-error';
                    break 2;
                }

                if (!$page->complete || count($page->items) > 50) {
                    $data[$section]['reasons'][] = 'page-incomplete';
                }

                $progress = 0;
                foreach ($page->items as $row) {
                    $this->state->checkCancellation();
                    if ($scanned >= $request->maxScan) {
                        $data[$section]['reasons'][] = 'scan-budget-exhausted';
                        break 2;
                    }

                    $scanned++;
                    $id = $row['id'] ?? null;
                    if (!is_int($id) || $id < 1) {
                        $data[$section]['reasons'][] = 'missing-id';
                        continue;
                    }

                    if (!isset($seen[$id])) {
                        $progress++;
                        $seen[$id] = true;
                    }

                    $reason = $this->invalid($row, $section, $request);
                    if ($reason !== null) {
                        $data[$section]['reasons'][] = $reason;
                        continue;
                    }

                    if (isset($observations[$id]) && $observations[$id]['row'] !== $row) {
                        foreach ([...$observations[$id]['sections'], $section] as $affected) {
                            $data[$affected]['reasons'][] = 'conflicting-observation';
                        }

                        continue;
                    }

                    $observations[$id] ??= ['row' => $row, 'sections' => []];
                    $observations[$id]['sections'][] = $section;
                    if (!isset($matches[$id])) {
                        $matches[$id] = true;
                        $data[$section]['observedCount']++;
                        if (array_key_exists('items', $data[$section])) {
                            $data[$section]['items'][] = $row;
                        }
                    }
                }

                if ($page->next === null) {
                    $finished = true;
                    break;
                }

                if (!is_int($page->next) || $page->next !== $offset + 50 || $progress === 0 || count($page->items) !== 50) {
                    $data[$section]['reasons'][] = 'pagination-unverified';
                    break;
                }

                if ($scanned >= $request->maxScan) {
                    $data[$section]['reasons'][] = 'scan-budget-exhausted';
                    break;
                }

                $offset = $page->next;
            } while (true);

            $data[$section]['complete'] = $finished;
        }

        $reasons = [];
        foreach ($data as $section => &$result) {
            $result['reasons'] = array_values(array_unique($result['reasons']));
            $result['complete'] = $result['complete'] && $result['reasons'] === [];
            $result['count'] = $result['complete'] ? $result['observedCount'] : null;
            if (array_key_exists('items', $result)) {
                usort($result['items'], static fn (array $a, array $b): int => $a['id'] <=> $b['id']);
            }

            foreach ($result['reasons'] as $reason) {
                $reasons[] = $section . ':' . $reason;
            }
        }

        unset($result);
        $complete = array_all($data, static fn (array $result): bool => $result['complete']);
        return new OperationResult('task-weekly-report', $data, [
            'reportAt' => $request->week->reportAt->format('Y-m-d\TH:i:s.uP'),
            'observedUntil' => $this->clock->now()->format('Y-m-d\TH:i:s.uP'),
            'period' => $request->week->metadata(),
            'scope' => ['groupId' => $request->groupId, 'responsibleId' => $request->responsibleId, 'visibility' => 'accessible'],
            'apiVersion' => '1.0', 'snapshotAtomic' => false, 'scanned' => $scanned, 'maxScan' => $request->maxScan,
            'complete' => $complete, 'reasons' => $reasons, 'reason' => $complete ? null : implode('; ', $reasons),
        ], $complete ? 0 : 3);
    }

    private function invalid(array $row, string $section, ReportTasksRequest $request): ?string
    {
        if (!is_string($row['title'] ?? null) || !is_int($row['responsibleId'] ?? null) || $row['responsibleId'] < 1 || !is_int($row['groupId'] ?? null) || !is_string($row['status'] ?? null) || TaskStatus::tryFrom($row['status']) === null) {
            return 'missing-predicate-field';
        }

        if ($row['groupId'] !== $request->groupId || ($request->responsibleId !== null && $row['responsibleId'] !== $request->responsibleId)) {
            return 'scope-mismatch';
        }

        if (isset(self::STATUSES[$section]) && $row['status'] !== self::STATUSES[$section]) {
            return 'status-mismatch';
        }

        $date = match ($section) {
            'closedTasks' => 'closedDate', 'newTasks' => 'createdDate', default => null,
        };
        if ($date !== null) {
            $matches = $request->week->contains($row[$date] ?? null);
            if ($matches !== true) {
                return $matches === null ? 'missing-predicate-field' : 'date-mismatch';
            }
        }

        return null;
    }
}
