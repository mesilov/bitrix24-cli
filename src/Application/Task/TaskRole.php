<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

enum TaskRole: string
{
    case Responsible = 'responsible';
    case Accomplice = 'accomplice';
    case Creator = 'creator';
    case Auditor = 'auditor';

    public function scalarField(): ?string
    {
        return match ($this) {
            self::Responsible => 'responsibleId',
            self::Creator => 'creatorId',
            default => null,
        };
    }

    public function participantField(): ?string
    {
        return match ($this) {
            self::Accomplice => 'accomplices',
            self::Auditor => 'auditors',
            default => null,
        };
    }
}
