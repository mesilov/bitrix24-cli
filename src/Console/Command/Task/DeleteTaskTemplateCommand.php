<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'task:template:delete', description: 'Запросить удаление шаблона задачи')]
final class DeleteTaskTemplateCommand extends TaskTemplateCommand
{
    protected const string ACTION = 'delete';
}
