<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\OperationResult;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ResultPresenter
{
    private const COLUMNS = [
        'task-list' => ['id', 'title', 'responsibleId', 'groupId', 'status', 'deadline'],
        'title-matches' => ['id', 'title'], 'task-fields' => ['name', 'type', 'editable', 'filterable', 'sortable'],
        'chat-messages' => ['id', 'createdDate', 'authorId', 'text'],
        'time-entries' => ['id', 'userId', 'seconds', 'text', 'createdDate'], 'checklist-roots' => ['id', 'title'],
        'checklist-items' => ['id', 'parentId', 'title', 'isComplete'],
        'task-history' => ['id', 'createdDate', 'userId', 'field', 'from', 'to'],
        'mutation' => ['taskId', 'resourceId', 'action', 'changedFields'],
        'plan' => ['taskId', 'step', 'method', 'apiVersion', 'effect', 'fields'],
    ];

    public function success(OperationResult $result, string $mode, OutputInterface $output, string $command, array $calls): void
    {
        $error = $result->exitStatus === 0 ? null : ['code' => $result->exitStatus === 130 ? 'interrupted' : 'partial-result', 'message' => $result->meta['reason'] ?? 'The operation is incomplete.'];
        $meta = ['schemaVersion' => 1, 'command' => $command, 'apiCalls' => $calls, 'apiVersions' => array_values(array_unique(array_column($calls, 'apiVersion'))), ...$result->meta];
        if ($mode === 'json') {
            $output->writeln(json_encode(['data' => $result->data, 'meta' => $meta, 'error' => $error], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);
        } else {
            [$columns, $rows] = $this->rows($result);
            if ($mode === 'plain') {
                foreach ($rows as $row) {
                    $output->writeln(implode("\t", array_map($this->escape(...), $row)), OutputInterface::OUTPUT_RAW);
                }
            } else {
                (new Table($output))->setHeaders($columns)->setRows(array_map(fn ($row): array => array_map(fn ($value): string => OutputFormatter::escape($this->string($value)), $row), $rows))->render();
            }
        }

        if ($error !== null) {
            $this->diagnostic($output, $error['message']);
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
        }
    }

    public function diagnostic(OutputInterface $output, string $message): void
    {
        $stream = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $stream->writeln($message, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET);
    }

    private function rows(OperationResult $result): array
    {
        if (in_array($result->profile, ['task-card', 'time-context', 'task-access'], true)) {
            $object = $result->data['task'] ?? $result->data['time'] ?? $result->data['access'] ?? [];
            ksort($object);
            $rows = [];
            foreach ($object as $key => $value) {
                $rows[] = [$key, $value];
            }

            return [[$result->profile === 'task-access' ? 'permission' : 'field', $result->profile === 'task-access' ? 'allowed' : 'value'], $rows];
        }

        $columns = self::COLUMNS[$result->profile] ?? ['field', 'value'];
        $items = $result->profile === 'plan' ? ($result->data['plan']['steps'] ?? []) : ($result->data['items'] ?? [$result->data['operation'] ?? []]);
        return [$columns, array_map(static fn (array $item): array => array_map(static fn (string $field): mixed => $item[$field] ?? null, $columns), $items)];
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
