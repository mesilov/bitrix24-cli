<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

/**
 * A relative deadline (day token and wall-clock time) resolved to an exact instant with the offset of its timezone.
 */
final readonly class DeadlineResolution
{
    public function __construct(public string $day, public string $time, public string $timezone, public \DateTimeImmutable $now, public \DateTimeImmutable $deadline)
    {
    }

    public function value(): string
    {
        return $this->deadline->format(DATE_ATOM);
    }

    public function metadata(): array
    {
        return ['day' => $this->day, 'time' => $this->time, 'timezone' => $this->timezone, 'now' => $this->now->format(DATE_ATOM), 'deadline' => $this->value()];
    }
}
