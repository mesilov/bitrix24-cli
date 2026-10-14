<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\OperationResult;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

final class TaskHumanRenderer
{
    private const STATUSES = ['pending' => 'Ждёт выполнения', 'in_progress' => 'В работе', 'supposedly_completed' => 'Ожидает контроля', 'completed' => 'Завершена', 'deferred' => 'Отложена', 'declined' => 'Отклонена'];

    public function statusDurations(OperationResult $result, OutputInterface $output): void
    {
        $this->write($output, 'Календарное время задачи #' . $result->data['taskId'] . ' до ' . $result->data['asOf']);
        $table = (new Table($output))->setHeaders(['Статус', 'Вход', 'Выход', 'Секунды']);
        $table->setRows(array_map(fn (array $row): array => array_map(OutputFormatter::escape(...), [$this->status($row['status'] ?? 'неизвестно'), $row['enteredAt'] ?? 'неизвестно', $row['leftAt'] ?? 'неизвестно', (string) ($row['seconds'] ?? 'неизвестно')]), $result->data['intervals']))->render();
        foreach ($result->data['totals'] as $status => $seconds) {
            $this->write($output, $this->status($status) . ': ' . ($seconds ?? 'неизвестно'));
        }

        $this->write($output, 'Календарные секунды включают выходные и время в завершённом статусе.');
    }

    public function card(array $task, OutputInterface $output): void
    {
        $this->write($output, (isset($task['id']) ? $task['id'] . ' · ' : '') . ($task['title'] ?? 'Задача'));
        if (array_key_exists('status', $task)) {
            $this->write($output, 'Статус: ' . $this->status($task['status']));
        }

        foreach (['responsible' => ['responsibleId', 'Ответственный'], 'group' => ['groupId', 'Проект']] as $key => [$idKey, $label]) {
            if (array_key_exists($key, $task) || array_key_exists($idKey, $task)) {
                $this->write($output, $label . ': ' . $this->relation($task[$key] ?? null, $task[$idKey] ?? null));
            }
        }

        if (array_key_exists('deadline', $task)) {
            $this->write($output, 'Срок: ' . ($task['deadline'] ?? 'не задан'));
        }

        if (array_key_exists('description', $task)) {
            $this->write($output, "\nОписание\n" . $this->value($task['description']));
        }

        if (isset($task['link'])) {
            $this->write($output, 'Ссылка: ' . $this->value($task['link']));
        }

        $extras = array_diff_key($task, array_flip(['id', 'title', 'status', 'responsible', 'responsibleId', 'group', 'groupId', 'deadline', 'description', 'link']));
        foreach (['responsible', 'group'] as $key) {
            if (is_array($task[$key] ?? null)) {
                $remaining = array_diff_key($task[$key], array_flip(['id', 'name']));
                if ($remaining !== []) {
                    $extras[$key] = $remaining;
                }
            }
        }

        if ($extras !== []) {
            $this->write($output, "\nДополнительные поля");
            foreach ($extras as $key => $value) {
                $this->write($output, $key . ': ' . $this->value($value));
            }
        }
    }

    public function listing(OperationResult $result, OutputInterface $output): void
    {
        $items = $result->data['items'] ?? [];
        $width = $this->width();
        if ($width < 24 || ($result->profile === 'task-list' && $width < 110)) {
            foreach ($items as $item) {
                $this->card($item, $output);
                $output->writeln('');
            }

            return;
        }

        $columns = $result->profile === 'title-matches' ? ['id', 'title'] : ['id', 'title', 'responsibleId', 'groupId', 'status', 'deadline'];
        $headers = $result->profile === 'title-matches' ? ['ID', 'Задача'] : ['ID', 'Задача', 'Ответственный', 'Проект', 'Статус', 'Срок'];
        $table = (new Table($output))->setHeaders($headers);
        $maxWidth = max(4, intdiv($width - 3 * count($columns) - 1, count($columns)));
        $table->setRows(array_map(fn (array $item): array => array_map(fn (string $field): string => OutputFormatter::escape($this->wrap($this->value($item[$field] ?? null), $maxWidth)), $columns), $items))->render();
    }

    public function summary(array $meta, OutputInterface $output): void
    {
        $scope = $meta['scope'] ?? 'tasks-visible-to-connection';
        $this->write($output, 'Область: ' . ($scope === 'explicit-task-ids' ? 'явно заданные ID задач' : 'задачи, доступные подключению') . ' (' . $scope . ').');
        if (isset($meta['roleFilter'])) {
            $this->write($output, 'Роль: ' . $meta['roleFilter']['role'] . ', пользователь #' . $meta['roleFilter']['userId'] . '.');
        }

        $this->write($output, 'Показано ' . ($meta['returned'] ?? 0) . ', найдено ' . ($meta['matched'] ?? 0) . ', просмотрено ' . ($meta['scanned'] ?? 0) . ' доступных задач.');
        $complete = ($meta['complete'] ?? false) === true;
        $limited = ($meta['limitApplied'] ?? false) === true;
        $order = implode(',', array_map(static fn (string $field, string $direction): string => $field . ':' . $direction, array_keys($meta['order'] ?? []), array_values($meta['order'] ?? [])));
        $this->write($output, ($complete ? 'Обход завершён' : 'Обход неполный') . '; complete=' . ($complete ? 'true' : 'false') . ', limitApplied=' . ($limited ? 'true' : 'false') . ', order=' . $order . '.');
        if ($complete && ($meta['matched'] ?? 0) === 0) {
            $this->write($output, 'Совпадений нет в доступной выборке.');
        }

        if ($limited) {
            $this->write($output, 'Вывод ограничен --limit; --all снимает только лимит вывода.');
        }
    }

    public function hint(string $reason): string
    {
        return match ($reason) {
            'scan-budget-exhausted' => 'Увеличьте --max-scan; --all снимает только лимит вывода. Неполный поиск не доказывает отсутствие дублей.',
            'page-error' => 'Повторите чтение и проверьте подключение и доступ.',
            'missing-id', 'missing-predicate-field' => 'Проверьте данные выбранных полей и права доступа.',
            'pagination-no-progress' => 'Повторите чтение и проверьте пагинацию: следующая страница не даёт новых задач.',
            'role-read-error' => 'Проверьте права чтения участников задачи и выбранное подключение.',
            default => 'Проверьте доступные данные и повторите чтение.',
        };
    }

    private function relation(mixed $object, mixed $id): string
    {
        $id = is_array($object) ? ($object['id'] ?? $id) : $id;
        $name = is_array($object) ? ($object['name'] ?? null) : null;
        return ($name === null || $name === '' ? '' : $this->value($name) . ' ') . ($id === null ? 'не задан' : ($name === null || $name === '' ? '#' . $this->value($id) : '(#' . $this->value($id) . ')'));
    }

    private function status(mixed $value): string
    {
        $value = $this->value($value);
        return isset(self::STATUSES[$value]) ? self::STATUSES[$value] . ' (' . $value . ')' : $value;
    }

    private function value(mixed $value): string
    {
        return is_array($value) || is_object($value) || is_bool($value) ? json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) ($value ?? '');
    }

    private function safe(string $text): string
    {
        return preg_replace_callback('/[\p{Cc}\p{Cf}]/u', static fn (array $match): string => match ($match[0]) {
            "\n" => "\n", "\t" => '\\t', "\r" => '\\r', default => '\\u{' . strtoupper(dechex(mb_ord($match[0]))) . '}',
        }, $text) ?? '';
    }

    private function width(): int
    {
        return max(8, (new Terminal())->getWidth());
    }

    private function write(OutputInterface $output, string $text): void
    {
        $output->writeln($this->wrap($text, $this->width()), OutputInterface::OUTPUT_RAW);
    }

    private function wrap(string $text, int $width): string
    {
        $lines = [];
        foreach (explode("\n", $this->safe($text)) as $line) {
            $buffer = '';
            $length = 0;
            preg_match_all('/\X/u', $line, $characters);
            foreach ($characters[0] as $character) {
                $size = Helper::width($character);
                if ($length + $size > $width && $buffer !== '') {
                    $lines[] = $buffer;
                    $buffer = '';
                    $length = 0;
                }

                $buffer .= $character;
                $length += $size;
            }

            $lines[] = $buffer;
        }

        return implode("\n", $lines);
    }
}
