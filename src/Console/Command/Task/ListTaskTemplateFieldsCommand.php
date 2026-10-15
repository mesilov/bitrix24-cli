<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'task:template:fields:list', description: 'Показать поля шаблона задачи')]
final class ListTaskTemplateFieldsCommand extends TaskTemplateCommand
{
    protected const string ACTION = 'fields';
}
