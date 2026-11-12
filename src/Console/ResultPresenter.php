<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class ResultPresenter
{
    private const COLUMNS = [
        'task-handover' => ['taskId', 'title', 'fromUserId', 'responsibleId', 'status', 'reason', 'sourceStatus', 'targetStatus', 'actualStatus', 'assignmentStatus', 'transitionStatus', 'verificationStatus'],
        'doctor-cards' => ['cardId', 'state', 'sanitizedScenario', 'missingCapability', 'createdAt', 'analysisRef'],
        'user-card' => ['ID', 'NAME', 'SECOND_NAME', 'LAST_NAME', 'EMAIL', 'UF_DEPARTMENT'],
        'user-list' => ['ID', 'NAME', 'SECOND_NAME', 'LAST_NAME', 'EMAIL', 'UF_DEPARTMENT'],
        'connections' => ['profile', 'name', 'hostname', 'default', 'source', 'path', 'isDefault'],
        'task-list' => ['id', 'title', 'responsibleId', 'groupId', 'status', 'deadline'],
        'title-matches' => ['id', 'title'], 'task-fields' => ['name', 'type', 'editable', 'filterable', 'sortable'],
        'chat-messages' => ['id', 'createdDate', 'authorId', 'text', 'kind', 'callId'],
        'user-presence' => ['id', 'name', 'lastActivityDate', 'absent'],
        'chat-calls' => ['callId', 'startedAt', 'durationSeconds', 'initiatorId', 'present', 'absent', 'leftEarly', 'unknown'],
        'chat-call' => ['callId', 'startedAt', 'durationSeconds', 'initiatorId', 'present', 'absent', 'leftEarly', 'unknown'],
        'chat-message-sent' => ['target', 'chatId', 'messageId', 'confirmed', 'readback'],
        'chat-created' => ['target', 'chatId', 'title', 'type', 'confirmed', 'readback'],
        'chat-candidates' => ['source', 'target', 'name', 'exact', 'writable', 'chatId', 'reason'],
        'task-results' => ['id', 'taskId', 'authorId', 'createdAt', 'status', 'text'],
        'time-entries' => ['id', 'userId', 'seconds', 'text', 'createdDate'], 'checklist-roots' => ['id', 'title'],
        'checklist-items' => ['id', 'parentId', 'title', 'isComplete'],
        'task-history' => ['id', 'createdDate', 'userId', 'field', 'from', 'to'],
        'task-lifecycle' => ['taskId', 'action', 'confirmed', 'status', 'needsControl', 'requireResult'],
        'template-mutation' => ['templateId', 'action', 'changedFields', 'confirmed'],
        'mutation' => ['taskId', 'resourceId', 'action', 'changedFields'],
        'plan' => ['taskId', 'step', 'method', 'apiVersion', 'effect', 'fields'],
    ];

    public function __construct(private TaskHumanRenderer $tasks = new TaskHumanRenderer(), private WeeklyReportRenderer $reports = new WeeklyReportRenderer())
    {
    }

    public function success(OperationResult $result, string $mode, OutputInterface $output, string $command, array $calls): void
    {
        $message = $result->meta['reason'] ?? 'The operation is incomplete.';
        if ($result->exitStatus === 3 && in_array($result->profile, ['task-list', 'title-matches'], true)) {
            $order = $result->meta['order'] ?? [];
            $message .= '; scanned=' . ($result->meta['scanned'] ?? 0) . '; order=' . implode(',', array_map(static fn (string $field, string $direction): string => $field . ':' . $direction, array_keys($order), array_values($order)));
            if (isset($result->meta['hint'])) {
                $message .= '. ' . $result->meta['hint'];
            }
        }

        $error = $result->exitStatus === 0 ? null : ['code' => $result->exitStatus === 130 ? 'interrupted' : 'partial-result', 'message' => $message];
        $meta = ['schemaVersion' => 1, 'command' => $command, 'apiCalls' => $calls, 'apiVersions' => array_values(array_unique(array_column($calls, 'apiVersion'))), ...$result->meta];
        if ($mode === 'json') {
            $output->writeln(json_encode(['data' => $result->data, 'meta' => $meta, 'error' => $error], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);
        } else {
            [$columns, $rows] = $this->rows($result);
            if ($mode === 'plain') {
                foreach ($rows as $row) {
                    $output->writeln(implode("\t", array_map($this->escape(...), $row)), OutputInterface::OUTPUT_RAW);
                }
            } elseif ($result->profile === 'task-weekly-report') {
                $this->reports->human($result, $output);
            } elseif ($result->profile === 'task-status-durations') {
                $this->tasks->statusDurations($result, $output);
            } elseif ($result->profile === 'task-card') {
                $this->tasks->card($result->data['task'] ?? [], $output);
            } elseif (in_array($result->profile, ['task-list', 'title-matches'], true)) {
                $this->tasks->listing($result, $output);
            } else {
                (new Table($output))->setHeaders($columns)->setRows(array_map(fn ($row): array => array_map(fn ($value): string => OutputFormatter::escape($this->string($value)), $row), $rows))->render();
            }
        }

        if ($mode !== 'json' && in_array($result->profile, ['task-list', 'title-matches'], true)) {
            $stream = $mode === 'plain' && $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $this->tasks->summary($result->meta, $stream);
        }

        if ($mode === 'plain' && $result->profile === 'task-weekly-report') {
            $this->diagnostic($output, implode("\t", WeeklyReportRenderer::COLUMNS));
            $this->diagnostic($output, $this->reports->summary($result));
        }

        if ($mode !== 'json' && $result->profile === 'chat-candidates') {
            $selection = $result->data['selection'];
            $this->diagnostic($output, 'Выбор по названию: ' . $selection['status'] . ($selection['target'] === null ? '' : ' → ' . $selection['target']) . '; точных совпадений ' . $selection['exactMatches'] . '; complete=' . ($result->meta['complete'] ? 'true' : 'false') . '.');
        }

        if ($mode !== 'json' && in_array($result->profile, ['chat-messages', 'chat-calls', 'chat-call'], true)) {
            foreach ($result->data['calls'] ?? ($result->profile === 'chat-call' ? [$result->data['call']] : ($result->profile === 'chat-calls' ? $result->data['items'] : [])) as $call) {
                $this->diagnostic($output, $this->callSummary($call));
            }
        }

        if ($mode !== 'json' && $result->profile === 'user-presence') {
            $this->diagnostic($output, 'Последняя активность на ' . ($result->meta['observedAt'] ?? '') . '; статус и признак онлайна не учитываются.' . (($result->meta['notFound'] ?? []) === [] ? '' : ' Не найдены: ' . implode(', ', $result->meta['notFound']) . '.'));
        }

        if ($mode !== 'json' && $result->profile === 'task-handover') {
            $this->diagnostic($output, 'Проект #' . $result->meta['groupId'] . ': передано ' . $result->meta['assigned'] . ' из ' . $result->meta['selected'] . '; полностью выполнено ' . $result->meta['confirmed'] . '; complete=' . ($result->meta['complete'] ? 'true' : 'false') . '. Область: задачи, доступные подключению.');
        }

        if ($mode === 'plain' && $result->profile === 'task-status-durations') {
            foreach ($result->data['totals'] as $status => $duration) {
                $this->diagnostic($output, $status . ': ' . ($duration['seconds'] === null ? 'неизвестно' : sprintf('%d s / %.2f h / %.2f d', $duration['seconds'], $duration['hours'], $duration['days'])));
            }
        }

        if ($error !== null) {
            $hint = $mode !== 'json' && in_array($result->profile, ['task-list', 'title-matches'], true) ? ' ' . $this->tasks->hint($result->meta['reason'] ?? '', $result->profile) : '';
            $this->diagnostic($output, $error['message'] . $hint);
        }

        if ($mode === 'human' && ($result->data['task']['status'] ?? null) === 'supposedly_completed') {
            $this->diagnostic($output, 'Задача ожидает контроля постановщика (supposedly_completed).');
        }

        if ($mode !== 'json' && isset($result->meta['scopeValidation']) && $result->meta['scopeValidation']['status'] !== 'fresh') {
            $this->diagnostic($output, $result->meta['scopeValidation']['reason']);
        }

        if ($mode === 'human' && in_array('1.0', $meta['apiVersions'], true)) {
            $this->diagnostic($output, 'This operation used an explicitly admitted REST 1.0 route.');
        }
    }

    public function failure(Failure $failure, string $mode, OutputInterface $output, array $calls = []): void
    {
        if ($mode === 'json' && !$output->isQuiet() && !$output->isSilent()) {
            $output->writeln(json_encode([
                'data' => null, 'meta' => ['schemaVersion' => 1, 'apiCalls' => $calls, 'outcomeUnknown' => $failure->outcomeUnknown],
                'error' => ['code' => $failure->errorCode, 'message' => $failure->getMessage(), 'details' => $failure->details],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);
        } else {
            $this->diagnostic($output, $failure->errorCode . ': ' . $failure->getMessage());
            if ($mode !== 'json' && isset($failure->details['scopeValidation'])) {
                foreach ($failure->details['scopeValidation'] as $field => $value) {
                    $this->diagnostic($output, $mode === 'plain' ? $this->escape($field) . "\t" . $this->escape($value) : $field . ': ' . $this->string($value));
                }
            }
        }
    }

    public function diagnostic(OutputInterface $output, string $message): void
    {
        $stream = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $stream->writeln($message, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET);
    }

    private function rows(OperationResult $result): array
    {
        if ($result->profile === 'task-weekly-report') {
            return $this->reports->rows($result);
        }

        if ($result->profile === 'task-status-durations') {
            $columns = ['status', 'enteredAt', 'leftAt', 'seconds', 'hours', 'days', 'verified', 'evidenceIds'];
            return [$columns, array_map(static fn (array $row): array => array_map(static fn (string $field): mixed => $row[$field] ?? 'неизвестно', $columns), $result->data['intervals'])];
        }

        if ($result->profile === 'doctor') {
            $rows = [];
            foreach ($result->data as $key => $value) {
                $rows[] = [$key, $value];
            }

            return [['field', 'value'], $rows];
        }

        if (in_array($result->profile, ['template-card', 'template-fields'], true)) {
            $object = $result->data[$result->profile === 'template-card' ? 'template' : 'fields'];
            ksort($object);
            return [['field', 'value'], array_map(static fn (string $field, mixed $value): array => [$field, $value], array_keys($object), array_values($object))];
        }

        if ($result->profile === 'scope-check') {
            return [['field', 'value'], array_map(static fn (string $field, mixed $value): array => [$field, $value], array_keys($result->data), array_values($result->data))];
        }

        if ($result->profile === 'chat-destination') {
            $rows = [];
            foreach (['destination', 'dialog'] as $section) {
                foreach ($result->data[$section] ?? [] as $key => $value) {
                    $rows[] = [$section . '.' . $key, $value];
                }
            }

            return [['field', 'value'], $rows];
        }

        if (in_array($result->profile, ['task-card', 'time-context', 'task-access'], true)) {
            $object = $result->data['task'] ?? $result->data['time'] ?? $result->data['access'] ?? [];
            ksort($object);
            $rows = [];
            foreach ($object as $key => $value) {
                $rows[] = [$key, $value];
            }

            return [[$result->profile === 'task-access' ? 'permission' : 'field', $result->profile === 'task-access' ? 'allowed' : 'value'], $rows];
        }

        if ($result->profile === 'task-lifecycle') {
            $item = [...$result->data['operation'], ...array_intersect_key($result->data['task'], array_flip(['status', 'needsControl', 'requireResult']))];
            return [self::COLUMNS['task-lifecycle'], [array_map(static fn (string $field): mixed => $item[$field] ?? null, self::COLUMNS['task-lifecycle'])]];
        }

        if ($result->profile === 'user-card') {
            $user = $result->data['user'] ?? null;
            $columns = self::COLUMNS['user-card'];
            return [$columns, $user === null ? [] : [array_map(static fn (string $field): mixed => $user[$field] ?? null, $columns)]];
        }

        $columns = self::COLUMNS[$result->profile] ?? ['field', 'value'];
        if ($result->profile === 'connections' && array_any($result->data['items'] ?? [], static fn (array $item): bool => array_key_exists('logging', $item))) {
            $columns[] = 'logging';
        }

        if ($result->profile === 'template-mutation' && array_key_exists('visibleAfter', $result->data['operation'] ?? [])) {
            $columns[] = 'visibleAfter';
        }

        if ($result->profile === 'plan' && array_any($result->data['plan']['steps'] ?? [], static fn (array $step): bool => isset($step['taskIdFromStep']))) {
            $columns[] = 'taskIdFromStep';
        }

        if ($result->profile === 'plan' && array_any($result->data['plan']['steps'] ?? [], static fn (array $step): bool => array_key_exists('templateId', $step))) {
            $columns[] = 'templateId';
        }

        $items = $result->profile === 'plan' ? ($result->data['plan']['steps'] ?? []) : ($result->data['items'] ?? [$result->data['operation'] ?? []]);
        if ($result->profile === 'chat-created') {
            $items = [[...$result->data['operation'], 'title' => $result->data['chat']['title'], 'type' => $result->data['chat']['type']]];
        }

        if ($result->profile === 'chat-messages') {
            $items = array_map(static fn (array $item): array => [...$item, 'callId' => $item['callRef']['callId'] ?? null], $items);
        }

        if (in_array($result->profile, ['chat-calls', 'chat-call'], true)) {
            $items = array_map(static fn (array $call): array => [...$call, ...$call['participants']['inferred']['summary']], $result->profile === 'chat-call' ? [$result->data['call']] : $items);
        }

        return [$columns, array_map(static fn (array $item): array => array_map(static fn (string $field): mixed => $item[$field] ?? ($field === 'visibleAfter' ? 'unknown' : null), $columns), $items)];
    }

    private function callSummary(array $call): string
    {
        $inferred = $call['participants']['inferred'];
        $confirmed = $call['participants']['confirmed'];
        $parts = [
            'Звонок ' . $call['callId'] . ': ' . ($call['startedAt'] ?? '?') . ' — ' . ($call['endedAt'] ?? '?') . ', ' . ($call['durationSeconds'] === null ? 'длительность неизвестна' : $call['durationSeconds'] . ' с'),
            'данные звонка: ' . ($call['details']['status'] === 'ok' ? 'ok' : 'недоступны (' . $call['details']['reason'] . ')'),
            'подтверждено: ' . ($confirmed['available'] ? (string) count($confirmed['users']) : 'нет (' . $confirmed['reason'] . ')'),
            'оценка v' . $inferred['rulesVersion'] . ' (' . $inferred['evidenceFreshness'] . '): present ' . $inferred['summary']['present'] . ', absent ' . $inferred['summary']['absent'] . ', left-early ' . $inferred['summary']['leftEarly'] . ', unknown ' . $inferred['summary']['unknown'],
        ];
        return implode('; ', $parts) . '. Оценка не является фактом присутствия.';
    }

    private function string(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value) || is_array($value) || is_object($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    private function escape(mixed $value): string
    {
        return str_replace(["\\", "\t", "\r", "\n"], ["\\\\", '\\t', '\\r', '\\n'], $this->string($value));
    }
}
