<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\SdkApiTransport;
use Bitrix24\CLI\Infrastructure\Connection\B24ClientProvider;
use Bitrix24\CLI\Infrastructure\Connection\ConnectionResolver;

/** A second connection is an identity, not proof of restricted task access. */
trait SecondUserFixtures
{
    private array $roleConnections = [];

    protected function secondUser(string $setting = 'BITRIX24_SECOND_USER_WEBHOOK'): array
    {
        if (isset($this->roleConnections[$setting])) {
            return $this->roleConnections[$setting];
        }

        $webhook = $this->setting($setting);
        if ($webhook === '') {
            self::markTestSkipped('Portal role acceptance needs ' . $setting . '.');
        }

        $resolver = new readonly class ($webhook) implements ConnectionResolver {
            public function __construct(private string $value)
            {
            }

            public function webhook(): string
            {
                return $this->value;
            }
        };
        $runtimeState = new RuntimeState();
        $sdkApiTransport = new SdkApiTransport(new B24ClientProvider($resolver, $runtimeState), $runtimeState);
        $id = (int) ($sdkApiTransport->call('profile', 1, [])->result['ID'] ?? 0);
        self::assertGreaterThan(0, $id);
        self::assertNotSame($this->userId, $id, 'Second webhook must identify a different user.');
        $scopes = $sdkApiTransport->call('scope', 1, [])->result;
        $this->acceptanceRecords[] = ['case' => 'second-user-identity', 'setting' => $setting, 'actingUserId' => $id, 'scopes' => $scopes, 'apiVersions' => ['1.0'], 'expected' => 'different-user', 'observed' => 'different-user'];
        return $this->roleConnections[$setting] = [$id, $sdkApiTransport, (new ApplicationFactory())->create($this->root(), null, $resolver)];
    }

    protected function invokeAsSecond(array $argv, array $statuses = [0], string $setting = 'BITRIX24_SECOND_USER_WEBHOOK'): array
    {
        [$id, , $app] = $this->secondUser($setting);
        $index = count($this->observedCommands);
        try {
            return $this->invoke($argv, $statuses, $app);
        } finally {
            if (isset($this->observedCommands[$index])) {
                $this->observedCommands[$index]['actingUserId'] = $id;
            }
        }
    }

    protected function assertSecondUserDenied(array $argv): void
    {
        $taskId = (int) $argv[1];
        $setting = $this->setting('B24CLI_TEST_RESTRICTED_WEBHOOK') === '' ? 'BITRIX24_SECOND_USER_WEBHOOK' : 'B24CLI_TEST_RESTRICTED_WEBHOOK';
        [$actor, $api] = $this->secondUser($setting);
        $access = (new \Bitrix24\CLI\Infrastructure\Bitrix24\Rest3TaskAdapter($api))->access($taskId);
        $this->acceptanceRecords[] = ['case' => 'outsider-access-preflight', 'taskId' => $taskId, 'ownerId' => $this->userId, 'actingUserId' => $actor, 'apiVersion' => '3.0', 'expected' => 'non-admin-outsider', 'observed' => $access];
        if (($access['admin'] ?? false) === true) {
            self::markTestSkipped('Denied-role acceptance is unverified: the supplied second user has admin=true. Supply a non-admin B24CLI_TEST_RESTRICTED_WEBHOOK.');
        }

        $before = $this->card($taskId, ['title', 'status', 'responsible.id']);
        $result = $this->invokeAsSecond($argv, [1, 4], $setting);
        self::assertContains($result['error']['code'], ['permission-denied', 'api-error', 'resource-binding-unverified', 'message-binding-unverified']);
        self::assertNotEmpty($result['meta']['apiCalls']);
        self::assertSame($before, $this->card($taskId, ['title', 'status', 'responsible.id']));
        $this->acceptanceRecords[] = ['case' => 'outsider-denied', 'taskId' => $taskId, 'ownerId' => $this->userId, 'actingUserId' => $actor, 'expected' => 'rejected', 'observed' => $result['error']['code'], 'unchangedTask' => true];
    }
}
