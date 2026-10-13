<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

final readonly class DeadlineWindow
{
    public const OPEN_STATUSES = ['pending', 'in_progress', 'supposedly_completed', 'deferred'];

    public function __construct(public ?string $window, public bool $overdue, public string $timezone, public \DateTimeImmutable $now, public ?\DateTimeImmutable $from, public ?\DateTimeImmutable $to)
    {
    }

    public function empty(): bool
    {
        return $this->from instanceof \DateTimeImmutable && $this->to instanceof \DateTimeImmutable && $this->from >= $this->to;
    }

    public function filter(): array
    {
        return [
            ...($this->from instanceof \DateTimeImmutable ? ['>=DEADLINE' => $this->from->format(DATE_ATOM)] : []),
            ...($this->to instanceof \DateTimeImmutable ? ['<DEADLINE' => $this->to->format(DATE_ATOM)] : []),
        ];
    }

    public function matches(mixed $deadline): ?bool
    {
        if ($deadline === null) {
            return false;
        }

        try {
            $date = new \DateTimeImmutable(FieldSchema::date($deadline));
        } catch (\Throwable) {
            return null;
        }

        return (!$this->from instanceof \DateTimeImmutable || $date >= $this->from) && (!$this->to instanceof \DateTimeImmutable || $date < $this->to);
    }

    public function metadata(): array
    {
        return [
            'window' => $this->window, 'overdue' => $this->overdue, 'timezone' => $this->timezone,
            'now' => $this->now->format(DATE_ATOM), 'fromInclusive' => $this->from?->format(DATE_ATOM), 'toExclusive' => $this->to?->format(DATE_ATOM),
            'route' => ['method' => 'tasks.task.list', 'apiVersion' => '1.0'],
            'deadlineApplied' => 'server', 'overdueStatusApplied' => $this->overdue ? 'local' : null,
        ];
    }
}
