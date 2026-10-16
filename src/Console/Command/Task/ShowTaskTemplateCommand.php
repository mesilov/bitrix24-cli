<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'task:template:show', description: 'Показать шаблон задачи по ID')]
final class ShowTaskTemplateCommand extends TaskTemplateCommand
{
    protected const string ACTION = 'get';
}
