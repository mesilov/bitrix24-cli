<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'task:template:update', description: 'Изменить шаблон задачи')]
final class UpdateTaskTemplateCommand extends TaskTemplateCommand
{
    protected const string ACTION = 'update';
}
