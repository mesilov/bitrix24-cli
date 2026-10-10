<?php

declare(strict_types=1);

use Bitrix24\CLI\Bootstrap\CommandCatalog;
use Bitrix24\CLI\Console\B24Application;
use Bitrix24\CLI\Infrastructure\Connection\EnvConnectionResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;

return static function (ContainerBuilder $container, string $root): void {
    $services = [
        Bitrix24\CLI\Application\RuntimeState::class,
        Bitrix24\CLI\Console\InputSourceReader::class,
        Bitrix24\CLI\Console\TaskInputMapper::class,
        Bitrix24\CLI\Console\ApiPolicyGuard::class,
        Bitrix24\CLI\Console\ResultPresenter::class,
        Bitrix24\CLI\Console\CommandRunner::class,
        Bitrix24\CLI\Infrastructure\Connection\B24ClientProvider::class,
        Bitrix24\CLI\Infrastructure\Bitrix24\SdkApiTransport::class,
        Bitrix24\CLI\Application\Task\TaskListScanner::class,
        Bitrix24\CLI\Application\Task\ChatSupport::class,
        Bitrix24\CLI\Application\Task\TimeSupport::class,
        Bitrix24\CLI\Application\Task\ChecklistSupport::class,
        Bitrix24\CLI\Application\Task\HistoryScanner::class,
    ];
    foreach ($services as $service) {
        $container->register($service)->setAutowired(true);
    }

    $container->register(EnvConnectionResolver::class)->setArgument('$projectRoot', $root);
    $container->setAlias(Bitrix24\CLI\Infrastructure\Connection\ConnectionResolver::class, EnvConnectionResolver::class);
    $container->setAlias(Bitrix24\CLI\Infrastructure\Bitrix24\ApiTransport::class, Bitrix24\CLI\Infrastructure\Bitrix24\SdkApiTransport::class);
    foreach ([
        'TaskGateway' => 'Rest3TaskAdapter', 'TaskChatGateway' => 'TaskChatAdapter', 'ChecklistGateway' => 'Rest1ChecklistAdapter',
        'TimeEntryGateway' => 'Rest1TimeEntryAdapter', 'ParticipantGateway' => 'Rest1ParticipantAdapter', 'TaskHistoryGateway' => 'Rest1TaskHistoryAdapter',
    ] as $port => $adapter) {
        $class = 'Bitrix24\\CLI\\Infrastructure\\Bitrix24\\' . $adapter;
        $container->register($class)->setAutowired(true);
        $container->setAlias('Bitrix24\\CLI\\Application\\Task\\Port\\' . $port, $class);
    }

    foreach ([
        'AddTask', 'ShowTask', 'ListTasks', 'FindTasks', 'UpdateTask', 'DeleteTask', 'ListTaskFields', 'ShowTaskAccess',
        'ListTaskChat', 'SendTaskChatMessage', 'UpdateTaskChatMessage', 'DeleteTaskChatMessage', 'AttachTaskFiles',
        'ShowTaskTime', 'AddTaskTimeEntry', 'ListTaskTimeEntries', 'UpdateTaskTimeEntry', 'DeleteTaskTimeEntry',
        'AddTaskChecklist', 'ListTaskChecklists', 'AddTaskChecklistItem', 'ListTaskChecklistItems', 'UpdateTaskChecklistItem',
        'CompleteTaskChecklistItem', 'RenewTaskChecklistItem', 'DeleteTaskChecklistItem', 'SetTaskParticipants', 'ListTaskHistory',
    ] as $handler) {
        $container->register('Bitrix24\\CLI\\Application\\Task\\Handler\\' . $handler . 'Handler')->setAutowired(true);
    }

    foreach (CommandCatalog::COMMANDS as $name => $command) {
        $container->register($command['class'])->setAutowired(true)->setPublic(true)->addTag('console.command', ['name' => $name]);
    }

    $container->register(B24Application::class)->setAutowired(true)->setPublic(true);
};
