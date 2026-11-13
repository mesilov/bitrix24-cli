<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

/**
 * Calendar window over a task creation, change or actual completion date, sent to REST 1.0 tasks.task.list as a half-open range.
 */
final readonly class DateWindow
{
    /** CLI field => REST 1.0 tasks.task.list filter field. */
    public const FIELDS = ['created' => 'CREATED_DATE', 'changed' => 'CHANGED_DATE', 'closed' => 'CLOSED_DATE'];

    public function __construct(public string $field, public string $window, public string $timezone, public \DateTimeImmutable $now, public \DateTimeImmutable $from, public \DateTimeImmutable $to)
    {
        if (!array_key_exists($field, self::FIELDS)) {
            throw new \InvalidArgumentException('Unsupported date window field ' . $field . '.');
        }
    }

    public function filter(): array
    {
        $native = self::FIELDS[$this->field];
        return ['>=' . $native => $this->from->format(DATE_ATOM), '<' . $native => $this->to->format(DATE_ATOM)];
    }

    /**
     * Every task has creation and change dates, so an empty value is missing data (null), not a mismatch.
     * The completion date is empty for a task that was never completed, which is a mismatch; an unreadable value is missing data.
     */
    public function matches(mixed $value): ?bool
    {
        if ($this->field === 'closed' && ($value === null || $value === '')) {
            return false;
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            $date = new \DateTimeImmutable(FieldSchema::date($value));
        } catch (\Throwable) {
            return null;
        }

        return $date >= $this->from && $date < $this->to;
    }

    public function metadata(): array
    {
        return [
            'window' => $this->window, 'timezone' => $this->timezone, 'now' => $this->now->format(DATE_ATOM),
            'fromInclusive' => $this->from->format(DATE_ATOM), 'toExclusive' => $this->to->format(DATE_ATOM),
            'route' => ['method' => 'tasks.task.list', 'apiVersion' => '1.0'], 'applied' => 'server',
        ];
    }
}
