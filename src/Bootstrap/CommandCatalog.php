<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Bootstrap;

final class CommandCatalog
{
    public const COMMANDS = [
        'task:add' => ['class' => \Bitrix24\CLI\Console\Command\Task\AddTaskCommand::class, 'effect' => 'write'],
        'task:show' => ['class' => \Bitrix24\CLI\Console\Command\Task\ShowTaskCommand::class, 'effect' => 'read'],
        'task:list' => ['class' => \Bitrix24\CLI\Console\Command\Task\ListTaskCommand::class, 'effect' => 'read'],
        'task:find' => ['class' => \Bitrix24\CLI\Console\Command\Task\FindTaskCommand::class, 'effect' => 'read'],
        'task:update' => ['class' => \Bitrix24\CLI\Console\Command\Task\UpdateTaskCommand::class, 'effect' => 'write'],
        'task:delete' => ['class' => \Bitrix24\CLI\Console\Command\Task\DeleteTaskCommand::class, 'effect' => 'delete'],
        'task:fields:list' => ['class' => \Bitrix24\CLI\Console\Command\Task\ListTaskFieldsCommand::class, 'effect' => 'read'],
        'task:access:show' => ['class' => \Bitrix24\CLI\Console\Command\Task\ShowTaskAccessCommand::class, 'effect' => 'read'],
        'task:assign' => ['class' => \Bitrix24\CLI\Console\Command\Task\AssignTaskCommand::class, 'effect' => 'write'],
        'task:deadline:set' => ['class' => \Bitrix24\CLI\Console\Command\Task\SetTaskDeadlineCommand::class, 'effect' => 'write'],
        'task:chat:list' => ['class' => \Bitrix24\CLI\Console\Command\Task\ListTaskChatCommand::class, 'effect' => 'read'],
        'task:chat:send' => ['class' => \Bitrix24\CLI\Console\Command\Task\SendTaskChatMessageCommand::class, 'effect' => 'write'],
        'task:chat:update' => ['class' => \Bitrix24\CLI\Console\Command\Task\UpdateTaskChatMessageCommand::class, 'effect' => 'write'],
        'task:chat:delete' => ['class' => \Bitrix24\CLI\Console\Command\Task\DeleteTaskChatMessageCommand::class, 'effect' => 'delete'],
        'task:file:attach' => ['class' => \Bitrix24\CLI\Console\Command\Task\AttachTaskFilesCommand::class, 'effect' => 'write'],
        'task:time:show' => ['class' => \Bitrix24\CLI\Console\Command\Task\ShowTaskTimeCommand::class, 'effect' => 'read'],
        'task:time:add' => ['class' => \Bitrix24\CLI\Console\Command\Task\AddTaskTimeEntryCommand::class, 'effect' => 'write'],
        'task:time:list' => ['class' => \Bitrix24\CLI\Console\Command\Task\ListTaskTimeEntriesCommand::class, 'effect' => 'read'],
        'task:time:update' => ['class' => \Bitrix24\CLI\Console\Command\Task\UpdateTaskTimeEntryCommand::class, 'effect' => 'write'],
        'task:time:delete' => ['class' => \Bitrix24\CLI\Console\Command\Task\DeleteTaskTimeEntryCommand::class, 'effect' => 'delete'],
        'task:checklist:add' => ['class' => \Bitrix24\CLI\Console\Command\Task\AddTaskChecklistCommand::class, 'effect' => 'write'],
        'task:checklist:list' => ['class' => \Bitrix24\CLI\Console\Command\Task\ListTaskChecklistsCommand::class, 'effect' => 'read'],
        'task:checklist:item:add' => ['class' => \Bitrix24\CLI\Console\Command\Task\AddTaskChecklistItemCommand::class, 'effect' => 'write'],
        'task:checklist:item:list' => ['class' => \Bitrix24\CLI\Console\Command\Task\ListTaskChecklistItemsCommand::class, 'effect' => 'read'],
        'task:checklist:item:update' => ['class' => \Bitrix24\CLI\Console\Command\Task\UpdateTaskChecklistItemCommand::class, 'effect' => 'write'],
        'task:checklist:item:complete' => ['class' => \Bitrix24\CLI\Console\Command\Task\CompleteTaskChecklistItemCommand::class, 'effect' => 'write'],
        'task:checklist:item:renew' => ['class' => \Bitrix24\CLI\Console\Command\Task\RenewTaskChecklistItemCommand::class, 'effect' => 'write'],
        'task:checklist:item:delete' => ['class' => \Bitrix24\CLI\Console\Command\Task\DeleteTaskChecklistItemCommand::class, 'effect' => 'delete'],
        'task:participants:set' => ['class' => \Bitrix24\CLI\Console\Command\Task\SetTaskParticipantsCommand::class, 'effect' => 'write'],
        'task:history:list' => ['class' => \Bitrix24\CLI\Console\Command\Task\ListTaskHistoryCommand::class, 'effect' => 'read'],
    ];

    public const ROUTES = [
        'task:add' => [['tasks.task.add', 3], ['tasks.task.field.list', 3]],
        'task:show' => [['tasks.task.get', 3]],
        'task:list' => [['tasks.task.list', 3]],
        'task:find' => [['tasks.task.list', 3]],
        'task:update' => [['tasks.task.update', 3], ['tasks.task.field.list', 3]],
        'task:delete' => [['tasks.task.delete', 3], ['tasks.task.get', 3]],
        'task:fields:list' => [['tasks.task.field.list', 3], ['tasks.task.field.get', 3]],
        'task:access:show' => [['tasks.task.access.get', 3]],
        'task:assign' => [['tasks.task.update', 3]],
        'task:deadline:set' => [['tasks.task.update', 3]],
        'task:chat:list' => [['tasks.task.get', 3], ['im.dialog.messages.get', 1]],
        'task:chat:send' => [['tasks.task.chat.message.send', 3]],
        'task:chat:update' => [['tasks.task.get', 3], ['im.message.update', 1], ['im.dialog.messages.get', 1]],
        'task:chat:delete' => [['tasks.task.get', 3], ['im.message.delete', 1], ['im.dialog.messages.get', 1]],
        'task:file:attach' => [['tasks.task.file.attach', 3]],
        'task:time:show' => [['tasks.task.get', 3]],
        'task:time:add' => [['task.elapseditem.add', 1]],
        'task:time:list' => [['task.elapseditem.getlist', 1]],
        'task:time:update' => [['task.elapseditem.update', 1], ['task.elapseditem.getlist', 1]],
        'task:time:delete' => [['task.elapseditem.delete', 1], ['task.elapseditem.getlist', 1]],
        'task:checklist:add' => [['task.checklistitem.add', 1]],
        'task:checklist:list' => [['task.checklistitem.getlist', 1]],
        'task:checklist:item:add' => [['task.checklistitem.getlist', 1], ['task.checklistitem.add', 1]],
        'task:checklist:item:list' => [['task.checklistitem.getlist', 1]],
        'task:checklist:item:update' => [['task.checklistitem.getlist', 1], ['task.checklistitem.update', 1]],
        'task:checklist:item:complete' => [['task.checklistitem.getlist', 1], ['task.checklistitem.complete', 1]],
        'task:checklist:item:renew' => [['task.checklistitem.getlist', 1], ['task.checklistitem.renew', 1]],
        'task:checklist:item:delete' => [['task.checklistitem.getlist', 1], ['task.checklistitem.delete', 1]],
        'task:participants:set' => [['tasks.task.update', 1], ['tasks.task.get', 3]],
        'task:history:list' => [['tasks.task.history.list', 1]],
    ];
}
