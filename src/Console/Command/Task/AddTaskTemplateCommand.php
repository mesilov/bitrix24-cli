<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'task:template:add', description: 'Создать шаблон задачи')]
final class AddTaskTemplateCommand extends TaskTemplateCommand
{
    protected const string ACTION = 'add';
}
