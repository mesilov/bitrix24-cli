<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Offline;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Infrastructure\Bitrix24\SdkApiTransport;
use Bitrix24\CLI\Infrastructure\Connection\B24ClientProvider;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use Bitrix24\CLI\Infrastructure\Connection\EnvConnectionResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ConnectionAndSdkTest extends TestCase
{
    private function resolver(): SpyConnectionResolver
    {
        return new SpyConnectionResolver();
    }

    public function testEnvironmentPrecedenceAndRootFileDoNotMutateProcessEnvironment(): void
    {
        $names = ['BITRIX24_WEBHOOK', 'BITRIX24_PHP_SDK_PLAYGROUND_WEBHOOK', 'B24CLI_ENV_FILE'];
        $old = [];
        $root = sys_get_temp_dir() . '/b24cli-env-' . bin2hex(random_bytes(6));
        mkdir($root);
        try {
            foreach ($names as $name) {
                $old[$name] = [getenv($name), $_ENV[$name] ?? null];
                putenv($name);
                unset($_ENV[$name]);
            }

            file_put_contents($root . '/.env', 'BITRIX24_WEBHOOK=https://file.invalid/rest/1/file-token/' . "\n");
            $envConnectionResolver = new EnvConnectionResolver($root);
            self::assertSame('https://file.invalid/rest/1/file-token/', $envConnectionResolver->webhook());
            self::assertFalse(getenv('BITRIX24_WEBHOOK'));
            file_put_contents($root . '/.env.local', "BITRIX24_WEBHOOK=https://local.invalid/rest/1/local-token/\n");
            self::assertSame('https://local.invalid/rest/1/local-token/', $envConnectionResolver->webhook());
            self::assertFalse(getenv('BITRIX24_WEBHOOK'));
            putenv('B24CLI_ENV_FILE=' . $root . '/.env');
            self::assertSame('https://file.invalid/rest/1/file-token/', $envConnectionResolver->webhook());
            putenv('BITRIX24_WEBHOOK=https://process.invalid/rest/1/process-token/');
            self::assertSame('https://process.invalid/rest/1/process-token/', $envConnectionResolver->webhook());
        } finally {
            unlink($root . '/.env');
            unlink($root . '/.env.local');
            rmdir($root);
            foreach ($old as $name => [$process, $value]) {
                putenv($process === false ? $name : $name . '=' . $process);
                if ($value === null) {
                    unset($_ENV[$name]);
                } else {
                    $_ENV[$name] = $value;
                }
            }
        }
    }

    public function testActualSdkCoreUsesWebhookAndExplicitApiVersions(): void
    {
        $requests = [];
        $mockHttpClient = new MockHttpClient(static function ($method, $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR)];
            return new MockResponse('{"result":{"item":{"id":123}},"time":{}}');
        });
        $resolver = $this->resolver();
        $runtimeState = new RuntimeState();
        $b24ClientProvider = new B24ClientProvider($resolver, $runtimeState, $mockHttpClient);
        self::assertSame(0, $resolver->reads);
        $sdkApiTransport = new SdkApiTransport($b24ClientProvider, $runtimeState);
        self::assertSame(123, $sdkApiTransport->call('tasks.task.get', 3, ['id' => 123])->result['item']['id']);
        $sdkApiTransport->call('tasks.task.get', 1, ['taskId' => 123]);
        self::assertSame(1, $resolver->reads);
        self::assertStringContainsString('/rest/api/1/secret-fixture/tasks.task.get', $requests[0][1]);
        self::assertStringContainsString('/rest/1/secret-fixture/', $requests[1][1]);
        self::assertStringContainsString('tasks.task.get', $requests[1][1]);
        self::assertSame(['id' => 123], $requests[0][2]);
        self::assertSame(['3.0', '1.0'], array_column($runtimeState->calls, 'apiVersion'));
    }

    public function testSdkInternalServerErrorIsReportedAsApiFailureWithoutRetry(): void
    {
        foreach (['read', 'write'] as $effect) {
            $client = new MockHttpClient(new MockResponse('{"error":"internal_server_error","error_description":"internal server error"}', ['http_code' => 500]));
            $state = new RuntimeState();
            $api = new SdkApiTransport(new B24ClientProvider($this->resolver(), $state, $client), $state);
            try {
                $api->call('tasks.task.get', 3, ['id' => 123, 'select' => ['auditors.id']], $effect);
                self::fail('Expected the server error.');
            } catch (Failure $failure) {
                self::assertSame('api-error', $failure->errorCode);
                self::assertSame('INTERNAL_SERVER_ERROR', $failure->details['apiErrorCode']);
                self::assertSame($effect === 'write', $failure->outcomeUnknown);
                self::assertSame(1, $client->getRequestsCount());
                self::assertSame(['3.0'], array_column($state->calls, 'apiVersion'));
            }
        }
    }

    public function testStructuredValidationIsRedactedAndMappedToResponsibleFlag(): void
    {
        $mockHttpClient = new MockHttpClient(new MockResponse(json_encode(['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'bad', 'validation' => [['field' => 'task.responsible.id', 'message' => 'secret-fixture is rejected']]]], JSON_THROW_ON_ERROR), ['http_code' => 400]));
        $runtimeState = new RuntimeState();
        $sdkApiTransport = new SdkApiTransport(new B24ClientProvider($this->resolver(), $runtimeState, $mockHttpClient), $runtimeState);
        try {
            $sdkApiTransport->call('tasks.task.update', 3, ['id' => 123, 'fields' => ['responsibleId' => 2]], 'write');
            self::fail('Expected a validation failure.');
        } catch (Failure $failure) {
            self::assertSame('api-validation-error', $failure->errorCode);
            self::assertSame('--responsible', $failure->details['validation'][0]['option']);
            self::assertStringNotContainsString('secret-fixture', json_encode($failure->details, JSON_THROW_ON_ERROR));
            self::assertFalse($failure->outcomeUnknown);
        }
    }

    public function testLostWriteResponseIsUnknownAndNeverRetried(): void
    {
        $requests = 0;
        $mockHttpClient = new MockHttpClient(static function () use (&$requests): never {
            $requests++;
            throw new TransportException('secret-fixture response lost');
        });
        $runtimeState = new RuntimeState();
        $sdkApiTransport = new SdkApiTransport(new B24ClientProvider($this->resolver(), $runtimeState, $mockHttpClient), $runtimeState);
        try {
            $sdkApiTransport->call('tasks.task.update', 3, ['id' => 123, 'fields' => ['title' => 'x']], 'write');
            self::fail('Expected transport failure.');
        } catch (Failure $failure) {
            self::assertSame('transport-error', $failure->errorCode);
            self::assertTrue($failure->outcomeUnknown);
            self::assertStringNotContainsString('secret-fixture', $failure->getMessage());
        }

        self::assertSame(1, $requests);
    }

    public function testDomainMigrationDoesNotReplayWriteToAnotherHost(): void
    {
        $mockHttpClient = new MockHttpClient(new MockResponse('', ['http_code' => 302, 'response_headers' => ['location: https://other.invalid/rest/api/1/secret-fixture/tasks.task.update']]));
        $runtimeState = new RuntimeState();
        $sdkApiTransport = new SdkApiTransport(new B24ClientProvider($this->resolver(), $runtimeState, $mockHttpClient), $runtimeState);
        try {
            $sdkApiTransport->call('tasks.task.update', 3, ['id' => 123], 'write');
            self::fail('Expected domain change rejection.');
        } catch (Failure $failure) {
            self::assertSame(1, $failure->exitStatus);
        }

        self::assertSame(1, $mockHttpClient->getRequestsCount());
    }

    public function testInterruptionDuringLostWriteResponseKeepsExit130AndUnknownOutcome(): void
    {
        $attempts = 0;
        $runtimeState = new RuntimeState();
        $mockHttpClient = new MockHttpClient(static function () use ($runtimeState, &$attempts): never {
            $attempts++;
            $runtimeState->cancelled = true;
            throw new TransportException('response lost during interruption');
        });
        $sdkApiTransport = new SdkApiTransport(new B24ClientProvider($this->resolver(), $runtimeState, $mockHttpClient), $runtimeState);
        try {
            $sdkApiTransport->call('tasks.task.add', 3, ['fields' => ['title' => 'x']], 'write');
            self::fail('Expected interrupted write.');
        } catch (Failure $failure) {
            self::assertSame('interrupted', $failure->errorCode);
            self::assertSame(130, $failure->exitStatus);
            self::assertTrue($failure->outcomeUnknown);
        }

        self::assertSame(1, $attempts);
    }

    public function testRest3PermissionFailureMapsWithoutLeakingServerMessage(): void
    {
        $mockHttpClient = new MockHttpClient(new MockResponse('{"error":{"code":"BITRIX_REST_V3_EXCEPTION_ACCESSDENIEDEXCEPTION","message":"secret-fixture denied"}}', ['http_code' => 403]));
        $runtimeState = new RuntimeState();
        $sdkApiTransport = new SdkApiTransport(new B24ClientProvider($this->resolver(), $runtimeState, $mockHttpClient), $runtimeState);
        try {
            $sdkApiTransport->call('tasks.task.update', 3, ['id' => 123], 'write');
            self::fail('Expected permission failure.');
        } catch (Failure $failure) {
            self::assertSame('permission-denied', $failure->errorCode);
            self::assertStringNotContainsString('secret-fixture', $failure->getMessage());
        }
    }
}
