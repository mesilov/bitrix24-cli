<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

use Bitrix24\CLI\Application\Task\LifecycleAction;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest1TaskLifecycleAdapter;
use Bitrix24\CLI\Infrastructure\Bitrix24\SdkApiTransport;
use Bitrix24\CLI\Infrastructure\Connection\B24ClientProvider;
use Bitrix24\CLI\Infrastructure\Connection\ConnectionResolver;

abstract class LiveTaskLifecycleTestCase extends LiveTaskCommandTestCase
{
    protected function assertObserverCannotTransition(LifecycleAction $action): void
    {
        $webhook = $this->setting('B24CLI_TEST_RESTRICTED_WEBHOOK');
        if ($webhook === '') {
            self::markTestSkipped('Denied observer matrix needs B24CLI_TEST_RESTRICTED_WEBHOOK.');
        }

        $resolver = new readonly class ($webhook) implements ConnectionResolver {
            public function __construct(private string $webhook)
            {
            }
            public function webhook(): string
            {
                return $this->webhook;
            }
        };
        $runtimeState = new RuntimeState();
        $sdkApiTransport = new SdkApiTransport(new B24ClientProvider($resolver, $runtimeState), $runtimeState);
        $observerId = (int) $sdkApiTransport->call('profile', 1, [])->result['ID'];
        self::assertNotSame($this->userId, $observerId);
        $id = in_array($action, [LifecycleAction::Approve, LifecycleAction::Disapprove], true) ? $this->awaitingReview() : $this->createTask();
        if ($action === LifecycleAction::Pause) {
            $this->transition($id, LifecycleAction::Start);
        }

        if ($action === LifecycleAction::Renew) {
            $this->transition($id, LifecycleAction::Complete);
        }

        $this->participants->set($id, null, [$observerId]);
        $before = $this->card($id, ['status']);
        $result = $this->invoke(['task:' . $action->value, $id], [1], (new ApplicationFactory())->create($this->root(), null, $resolver));
        self::assertSame('permission-denied', $result['error']['code']);
        self::assertNotContains('write', array_column($result['meta']['apiCalls'], 'effect'));
        self::assertSame($before['status'], $this->card($id, ['status'])['status']);
    }

    protected function transition(int $id, LifecycleAction $action): void
    {
        (new Rest1TaskLifecycleAdapter($this->api))->transition($id, $action);
    }

    protected function nativeResult(int $id): void
    {
        $result = $this->api->call('tasks.task.result.add', 3, ['fields' => ['taskId' => $id, 'text' => 'Disposable lifecycle result']], 'write')->result;
        self::assertSame($id, $result['item']['taskId']);
    }

    protected function awaitingReview(): int
    {
        [$id, $api] = $this->controlledTask();
        (new Rest1TaskLifecycleAdapter($api))->transition($id, LifecycleAction::Complete);
        self::assertSame('supposedly_completed', $this->card($id, ['status'])['status']);
        return $id;
    }

    protected function controlledTask(): array
    {
        if ($this->setting('B24CLI_TEST_EXECUTOR_WEBHOOK') !== '') {
            $api = $this->executorApi();
            $executorId = (int) $api->call('profile', 1, [])->result['ID'];
            self::assertNotSame($this->userId, $executorId, 'Review fixture needs a different executor.');
            return [$this->createTask(fields: ['needsControl' => true, 'responsibleId' => $executorId]), $api];
        }

        $creatorId = $this->setting('B24CLI_TEST_OTHER_USER_ID');
        if ($creatorId === '') {
            self::markTestSkipped('Control acceptance needs a separate creator ID or executor webhook.');
        }

        self::assertMatchesRegularExpression('/^[1-9][0-9]*$/', $creatorId);
        self::assertNotSame($this->userId, (int) $creatorId);
        return [$this->createTask(fields: ['needsControl' => true, 'creatorId' => (int) $creatorId]), $this->api];
    }

    protected function completingApi(): SdkApiTransport
    {
        return $this->setting('B24CLI_TEST_EXECUTOR_WEBHOOK') === '' ? $this->api : $this->executorApi();
    }

    protected function completingApp(): \Bitrix24\CLI\Console\B24Application
    {
        return $this->setting('B24CLI_TEST_EXECUTOR_WEBHOOK') === '' ? $this->app : $this->executorApp();
    }

    protected function executorResolver(): ConnectionResolver
    {
        $webhook = $this->setting('B24CLI_TEST_EXECUTOR_WEBHOOK');
        if ($webhook === '') {
            self::markTestSkipped('Control acceptance needs a separate B24CLI_TEST_EXECUTOR_WEBHOOK; creator completion can skip review.');
        }

        return new readonly class ($webhook) implements ConnectionResolver {
            public function __construct(private string $webhook)
            {
            }
            public function webhook(): string
            {
                return $this->webhook;
            }
        };
    }

    protected function executorApi(): SdkApiTransport
    {
        $runtimeState = new RuntimeState();
        return new SdkApiTransport(new B24ClientProvider($this->executorResolver(), $runtimeState), $runtimeState);
    }

    protected function executorApp(): \Bitrix24\CLI\Console\B24Application
    {
        return (new ApplicationFactory())->create($this->root(), null, $this->executorResolver());
    }

    protected function assertActualState(int $id, array $result, string $expected): void
    {
        self::assertTrue($result['data']['operation']['confirmed']);
        self::assertSame($expected, $result['data']['task']['status']);
        self::assertSame($expected, $this->card($id, ['status'])['status']);
    }
}
