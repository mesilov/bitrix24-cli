<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

use Bitrix24\CLI\Application\Failure;

final readonly class ReportWeek
{
    public function __construct(public \DateTimeImmutable $from, public \DateTimeImmutable $to, public \DateTimeImmutable $reportAt, public string $timezone)
    {
    }

    public function effectiveTo(): \DateTimeImmutable
    {
        return min($this->to, $this->reportAt);
    }

    public function contains(mixed $date): ?bool
    {
        try {
            $instant = new \DateTimeImmutable(FieldSchema::date($date));
            return $instant >= $this->from && $instant < $this->effectiveTo();
        } catch (Failure) {
            return null;
        }
    }

    public function metadata(): array
    {
        return [
            'from' => $this->from->format(DATE_ATOM), 'to' => $this->to->format(DATE_ATOM),
            'effectiveTo' => $this->effectiveTo()->format('Y-m-d\TH:i:s.uP'),
            'timezone' => $this->timezone, 'unfinished' => $this->reportAt < $this->to,
        ];
    }
}
