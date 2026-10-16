<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\OperationResult;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

final class WeeklyReportRenderer
{
    public const COLUMNS = ['section', 'recordType', 'id', 'title', 'responsibleId', 'groupId', 'status', 'createdDate', 'closedDate', 'count', 'observedCount', 'complete', 'reason'];
    private const LABELS = ['closedTasks' => 'Закрытые за неделю', 'awaitingControlTasks' => 'Ожидают контроля сейчас', 'newTasks' => 'Новые за неделю', 'inProgressTasks' => 'В работе сейчас'];

    public function rows(OperationResult $result): array
    {
        $rows = [];
        foreach ($result->data as $section => $value) {
            foreach ($value['items'] ?? [] as $item) {
                $row = ['section' => $section, 'recordType' => 'task', ...$item];
                $rows[] = array_map(static fn (string $column): mixed => $row[$column] ?? '', self::COLUMNS);
            }

            $row = ['section' => $section, 'recordType' => 'summary', 'count' => $value['count'] ?? 'null', 'observedCount' => $value['observedCount'], 'complete' => $value['complete'] ? 'true' : 'false', 'reason' => implode(';', $value['reasons'])];
            $rows[] = array_map(static fn (string $column): mixed => $row[$column] ?? '', self::COLUMNS);
        }

        return [self::COLUMNS, $rows];
    }

    public function summary(OperationResult $result): string
    {
        $period = $result->meta['period'];
        return 'Проект #' . $result->meta['scope']['groupId'] . ($result->meta['scope']['responsibleId'] === null ? ': весь проект' : ', ответственный #' . $result->meta['scope']['responsibleId'])
            . '; неделя [' . $period['from'] . ', ' . $period['to'] . '), до ' . $period['effectiveTo'] . '; ' . $period['timezone']
            . '; наблюдения [' . $result->meta['reportAt'] . ', ' . $result->meta['observedUntil'] . ']. Область: доступные подключению задачи. Контроль/работа — текущие; снимок не атомарный.';
    }

    public function human(OperationResult $result, OutputInterface $output): void
    {
        $output->writeln(OutputFormatter::escape($this->summary($result)));
        foreach (self::LABELS as $section => $label) {
            $value = $result->data[$section];
            $count = $value['complete'] ? (string) $value['count'] : 'неизвестно, найдено ' . $value['observedCount'] . ' (partial)';
            $output->writeln(OutputFormatter::escape($label . ': ' . $count));
            if (($value['items'] ?? []) !== []) {
                $columns = ['id', 'title', 'responsibleId', 'groupId', 'status', 'closedDate'];
                (new Table($output))->setHeaders($columns)->setRows(array_map(static fn (array $row): array => array_map(static fn (string $column): string => OutputFormatter::escape((string) ($row[$column] ?? '')), $columns), $value['items']))->render();
            }

            if ($value['reasons'] !== []) {
                $output->writeln(OutputFormatter::escape('Причины: ' . implode(', ', $value['reasons'])));
            }
        }
    }
}
