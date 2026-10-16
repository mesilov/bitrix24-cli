<?php

declare(strict_types=1);

use Bitrix24\CLI\Bootstrap\CommandCatalog;
use Bitrix24\CLI\Console\B24Application;
use Bitrix24\CLI\Infrastructure\Connection\EnvConnectionResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;

return static function (ContainerBuilder $container, string $root, bool $packaged = false): void {
    $services = [
        Bitrix24\CLI\Application\RuntimeState::class,
        Symfony\Component\Filesystem\Filesystem::class,
        Symfony\Component\Mime\Encoder\Base64Encoder::class,
        Bitrix24\CLI\Infrastructure\Filesystem\LocalFileSource::class,
        Bitrix24\CLI\Infrastructure\Connection\ConfigDirectoryResolver::class,
        Bitrix24\CLI\Infrastructure\Connection\FileProtection::class,
        Bitrix24\CLI\Infrastructure\Connection\FileCredentialStore::class,
        Bitrix24\CLI\Infrastructure\Connection\ProfileRepository::class,
        Bitrix24\CLI\Infrastructure\Connection\ScopeStateStore::class,
        Bitrix24\CLI\Infrastructure\Connection\ScopeValidator::class,
        Bitrix24\CLI\Application\Connection\ScopeManifest::class,
        Bitrix24\CLI\Infrastructure\Bitrix24\ScopeCheckingTransport::class,
        Bitrix24\CLI\Console\ConnectionCommandRunner::class,
        Bitrix24\CLI\Console\DoctorCommandRunner::class,
        Bitrix24\CLI\Infrastructure\Doctor\ProblemTrajectoryStore::class,
        Bitrix24\CLI\Infrastructure\Doctor\SystemClock::class,
        Bitrix24\CLI\Infrastructure\Doctor\UnavailableFeedbackGateway::class,
        Bitrix24\CLI\Infrastructure\Doctor\MockFeedbackGateway::class,
        Bitrix24\CLI\Console\InputSourceReader::class,
        Bitrix24\CLI\Console\TaskInputMapper::class,
        Bitrix24\CLI\Application\Task\DeadlineWindowResolver::class,
        Bitrix24\CLI\Application\Task\ReportWeekResolver::class,
        Bitrix24\CLI\Application\Task\WeeklyTaskReport::class,
        Bitrix24\CLI\Console\UserInputMapper::class,
        Bitrix24\CLI\Console\TaskTemplateInputMapper::class,
        Bitrix24\CLI\Application\Task\TaskTemplateService::class,
        Bitrix24\CLI\Application\User\UserScanner::class,
        Bitrix24\CLI\Application\User\UserDirectoryService::class,
        Bitrix24\CLI\Console\ApiPolicyGuard::class,
        Bitrix24\CLI\Console\TaskHumanRenderer::class,
        Bitrix24\CLI\Console\WeeklyReportRenderer::class,
        Bitrix24\CLI\Console\ResultPresenter::class,
        Bitrix24\CLI\Console\CommandRunner::class,
        Bitrix24\CLI\Infrastructure\Connection\B24ClientProvider::class,
        Bitrix24\CLI\Infrastructure\Connection\IncomingWebhookCurrentUserAdapter::class,
        Bitrix24\CLI\Infrastructure\Bitrix24\SdkApiTransport::class,
        Bitrix24\CLI\Application\Task\TaskListScanner::class,
        Bitrix24\CLI\Application\Task\ChatSupport::class,
        Bitrix24\CLI\Application\Task\TimeSupport::class,
        Bitrix24\CLI\Application\Task\ChecklistSupport::class,
        Bitrix24\CLI\Application\Task\HistoryScanner::class,
        Bitrix24\CLI\Application\Task\StatusDurationCalculator::class,
        Bitrix24\CLI\Application\Task\TaskParticipantEnricher::class,
        Bitrix24\CLI\Application\Task\TaskMetadataService::class,
        Bitrix24\CLI\Application\Task\TaskTagMutation::class,
        Bitrix24\CLI\Application\Task\TaskChatService::class,
        Bitrix24\CLI\Application\Task\TaskResultService::class,
        Bitrix24\CLI\Application\Task\TimeEntryService::class,
        Bitrix24\CLI\Application\Task\ChecklistItemService::class,
        Bitrix24\CLI\Application\Task\TaskLifecycleService::class,
        Bitrix24\CLI\Application\Task\TaskHandoverService::class,

    ];
    foreach ($services as $service) {
        $container->register($service)->setAutowired(true);
    }

    $container->setAlias(Bitrix24\CLI\Application\Clock::class, Bitrix24\CLI\Infrastructure\Doctor\SystemClock::class);
    $container->setAlias(Bitrix24\CLI\Application\Doctor\FeedbackGateway::class, Bitrix24\CLI\Infrastructure\Doctor\UnavailableFeedbackGateway::class);

    $container->register(Bitrix24\SDK\Infrastructure\Filesystem\Base64Encoder::class)->setAutowired(true)->setArgument('$log', new Psr\Log\NullLogger());
    $container->setAlias(Bitrix24\CLI\Application\Task\Port\LocalFileGateway::class, Bitrix24\CLI\Infrastructure\Filesystem\LocalFileSource::class);
    $container->register(Bitrix24\CLI\Infrastructure\Connection\ConnectionManager::class)->setAutowired(true)->setArgument('$projectRoot', $root)->setArgument('$packaged', $packaged);
    $container->setAlias(Bitrix24\CLI\Infrastructure\Connection\CredentialStore::class, Bitrix24\CLI\Infrastructure\Connection\FileCredentialStore::class);
    $container->setAlias(Bitrix24\CLI\Infrastructure\Connection\ConnectionResolver::class, Bitrix24\CLI\Infrastructure\Connection\ConnectionManager::class);
    $container->setAlias(Bitrix24\CLI\Application\Task\Port\CurrentUserGateway::class, Bitrix24\CLI\Infrastructure\Connection\IncomingWebhookCurrentUserAdapter::class);
    $container->setAlias(Bitrix24\CLI\Infrastructure\Bitrix24\ApiTransport::class, Bitrix24\CLI\Infrastructure\Bitrix24\ScopeCheckingTransport::class);
    foreach ([
        'TaskLifecycleGateway' => 'Rest1TaskLifecycleAdapter',
        'ProjectMemberGateway' => 'Rest1ProjectMemberAdapter',
        'DiskUploadGateway' => 'Rest1DiskUploadAdapter',
        'DeadlineTaskGateway' => 'Rest1TaskListAdapter',
        'WeeklyReportTaskGateway' => 'Rest1TaskListAdapter',
        'TaskTemplateGateway' => 'Rest1TaskTemplateAdapter',
        'TaskResultGateway' => 'Rest3TaskResultAdapter',
        'TaskUserFieldGateway' => 'Rest1TaskUserFieldAdapter',
        'TaskGateway' => 'Rest3TaskAdapter', 'TaskTagGateway' => 'Rest1TaskTagAdapter', 'TaskChatGateway' => 'TaskChatAdapter', 'ChecklistGateway' => 'Rest1ChecklistAdapter',
        'TimeEntryGateway' => 'Rest1TimeEntryAdapter', 'ParticipantGateway' => 'Rest1ParticipantAdapter', 'TaskHistoryGateway' => 'Rest1TaskHistoryAdapter', 'UserGateway' => 'Rest1UserAdapter',
    ] as $port => $adapter) {
        $class = 'Bitrix24\\CLI\\Infrastructure\\Bitrix24\\' . $adapter;
        $container->register($class)->setAutowired(true);
        $container->setAlias('Bitrix24\\CLI\\Application\\Task\\Port\\' . $port, $class);
    }

    foreach ([
        'ReportTasks', 'AddTask', 'ShowTask', 'ListTasks', 'FindTasks', 'UpdateTask', 'DeleteTask', 'AttachTaskFiles',
        'AddTaskChecklist', 'ListTaskChecklists', 'SetTaskParticipants', 'ListTaskHistory', 'StatusDurationsTask',
    ] as $handler) {
        $container->register('Bitrix24\\CLI\\Application\\Task\\Handler\\' . $handler . 'Handler')->setAutowired(true);
    }

    foreach (['UserDirectoryGateway' => 'Rest1UserDirectoryAdapter', 'DepartmentGateway' => 'Rest1DepartmentAdapter'] as $port => $adapter) {
        $class = 'Bitrix24\\CLI\\Infrastructure\\Bitrix24\\' . $adapter;
        $container->register($class)->setAutowired(true);
        $container->setAlias('Bitrix24\\CLI\\Application\\User\\Port\\' . $port, $class);
    }

    foreach (['CurrentUser', 'FindUsers', 'ListColleagues', 'GetManager'] as $handler) {
        $container->register('Bitrix24\\CLI\\Application\\User\\Handler\\' . $handler . 'Handler')->setAutowired(true);
    }

    foreach (CommandCatalog::all() as $command) {
        $container->register($command['class'])->setAutowired(true)->setPublic(true);
    }

    $container->register(B24Application::class)->setAutowired(true)->setPublic(true);
};
