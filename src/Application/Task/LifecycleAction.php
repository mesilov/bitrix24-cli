<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Task;

enum LifecycleAction: string
{
    case Start = 'start';
    case Pause = 'pause';
    case Defer = 'defer';
    case Renew = 'renew';
    case Complete = 'complete';
    case Approve = 'approve';
    case Disapprove = 'disapprove';

    public function method(): string
    {
        return 'tasks.task.' . $this->value;
    }
}
