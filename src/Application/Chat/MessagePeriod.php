<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat;

/** Half-open interval [from, to) with the single clock reading used by the operation. */
final readonly class MessagePeriod
{
    public function __construct(
        public string $kind,
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
        public \DateTimeImmutable $now,
        public ?string $timezone,
    ) {
    }

    public function contains(\DateTimeImmutable $date): bool
    {
        return $date >= $this->from && $date < $this->to;
    }

    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'from' => $this->from->format(\DATE_ATOM),
            'to' => $this->to->format(\DATE_ATOM),
            'timezone' => $this->timezone,
            'now' => $this->now->format(\DATE_ATOM),
        ];
    }
}
