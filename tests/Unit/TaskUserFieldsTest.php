<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\TestCase;

final class TaskUserFieldsTest extends TestCase
{
    private const CREATE = ['task:add', '--title', 'Custom task', '--creator', '1', '--responsible', '2', '--group-id', '42'];

    private function api(?\Closure $write = null): FakeApiTransport
    {
        return new FakeApiTransport(static function (string $method, int $version, array $params) use ($write): ApiResponse {
            if ($method === 'tasks.task.getFields') {
                return new ApiResponse(['fields' => ['UF_QUERY' => ['type' => 'string'], 'UF_AMOUNT' => ['type' => 'double'], 'UF_MULTI' => ['type' => 'string'], 'UF_FLAG' => ['type' => 'boolean'], 'UF_SPECIAL' => ['type' => 'disk_file']]]);
            }

            if ($write instanceof \Closure) {
                return $write($method, $version, $params);
            }

            return new ApiResponse(match ([$method, $version]) {
                ['tasks.task.add', 1] => ['task' => ['id' => '123']],
                ['tasks.task.add', 3] => ['item' => ['id' => 123]],
                ['tasks.task.update', 1] => ['task' => ['id' => '123']],
                ['tasks.task.update', 3] => ['result' => true],
                default => throw new \LogicException('Unexpected route.'),
            });
        });
    }

    private function invoke(FakeApiTransport $api, array $argv, string $stdin = ''): \Bitrix24\CLI\Tests\Support\ConsoleRun
    {
        return ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api), ['--json', ...$argv], $stdin);
    }

    public function testSingleNativeLegacyCreateAndSparseCustomUpdate(): void
    {
        $api = $this->api();
        $run = $this->invoke($api, [...self::CREATE, '--user-fields', '{"UF_QUERY":"5","UF_AMOUNT":58}']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame(['tasks.task.getFields', 'tasks.task.add'], array_column($api->calls, 'method'));
        self::assertSame([1, 1], array_column($api->calls, 'version'));
        self::assertSame(['fields' => ['TITLE' => 'Custom task', 'CREATED_BY' => 1, 'RESPONSIBLE_ID' => 2, 'GROUP_ID' => 42, 'ALLOW_TIME_TRACKING' => 'N', 'UF_QUERY' => '5', 'UF_AMOUNT' => 58]], $api->calls[1]['parameters']);
        self::assertSame(123, $run->json()['data']['operation']['taskId']);
        $api = $this->api();
        $run = $this->invoke($api, ['task:update', '123', '--user-fields', '{"UF_QUERY":"6"}']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([1, 1], array_column($api->calls, 'version'));
        self::assertSame(['taskId' => 123, 'fields' => ['UF_QUERY' => '6']], $api->calls[1]['parameters']);
    }

    public function testNativeAdvancedCreateNamesAndPreservedTypes(): void
    {
        $api = $this->api();
        $run = $this->invoke($api, ['task:add', '--fields', '{"TITLE":"Native","CREATED_BY":1,"RESPONSIBLE_ID":2,"ALLOW_TIME_TRACKING":"Y"}', '--user-fields-file', '-'], '{"UF_QUERY":"x:y","UF_MULTI":["a","b"],"UF_FLAG":true}');
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame('Y', $api->calls[1]['parameters']['fields']['ALLOW_TIME_TRACKING']);
        self::assertSame(['a', 'b'], $api->calls[1]['parameters']['fields']['UF_MULTI']);
        self::assertTrue($api->calls[1]['parameters']['fields']['UF_FLAG']);
        $run = $this->invoke($api, ['task:add', '--fields', '{"title":"v3","creatorId":1,"responsibleId":2}', '--user-fields', '{"UF_QUERY":"5"}']);
        self::assertSame(2, $run->status);
        self::assertCount(2, $api->calls);
    }

    public function testCustomInlineFileAndStdinSources(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'custom-json');
        file_put_contents($file, '{"UF_QUERY":"5"}');
        try {
            foreach (['--user-fields', '--user-fields-file'] as $option) {
                $api = $this->api();
                $run = $this->invoke($api, ['task:update', '123', $option, $option === '--user-fields' ? '{"UF_QUERY":"5"}' : $file]);
                self::assertSame(0, $run->status, $run->stdout);
                self::assertSame('5', $api->calls[1]['parameters']['fields']['UF_QUERY']);
            }
        } finally {
            unlink($file);
        }
    }

    public function testInvalidCustomInputMakesNoApiOrCredentialCalls(): void
    {
        foreach (['{}', '[]', '{"title":"x"}', '{"UF_QUERY":null}', '{"UF_QUERY":{}}', '{"UF_QUERY":[{}]}', '{"UF_QUERY":[["x"]]}', '{"UF_QUERY":"a","UF_QUERY":"b"}', '{"UF_QUERY":"a","\u0055F_QUERY":"b"}', '{"UF_CRM_TASK":["D_1"]}', '{"UF_TASK_WEBDAV_FILES":[1]}', '{"UF_MAIL_MESSAGE":1}', '{"uf_query":"x"}'] as $json) {
            $api = $this->api();
            $spy = new SpyConnectionResolver();
            $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api, $spy), ['--json', 'task:update', '123', '--user-fields', $json]);
            self::assertSame(2, $run->status, $json . $run->stdout);
            self::assertSame([], $api->calls);
            self::assertSame(0, $spy->reads);
        }
    }

    public function testCustomUnknownAndUnsupportedTypesFailBeforeAnyWrite(): void
    {
        foreach (['{"UF_MISSING":"x"}', '{"UF_SPECIAL":1}', '{"UF_AMOUNT":"NaN"}', '{"UF_QUERY":false}'] as $json) {
            $api = $this->api();
            $run = $this->invoke($api, [...self::CREATE, '--user-fields', $json]);
            self::assertSame(2, $run->status, $run->stdout);
            self::assertSame(['tasks.task.getFields'], array_column($api->calls, 'method'));
        }
    }

    public function testDryRunAndStrictPolicy(): void
    {
        foreach ([self::CREATE, ['task:update', '123', '--title', 'New']] as $base) {
            $api = $this->api();
            $run = $this->invoke($api, [...$base, '--user-fields', '{"UF_QUERY":"5"}', '--dry-run']);
            self::assertSame(0, $run->status, $run->stdout);
            self::assertSame(['tasks.task.getFields'], array_column($api->calls, 'method'));
            self::assertSame('1.0', $run->json()['data']['plan']['steps'][0]['apiVersion']);
            $api = $this->api();
            $spy = new SpyConnectionResolver();
            $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api, $spy), ['--json', '--api-policy', 'strict-rest3', ...$base, '--user-fields', '{"UF_QUERY":"5"}']);
            self::assertSame(4, $run->status, $run->stdout);
            self::assertSame([], $api->calls);
            self::assertSame(0, $spy->reads);
        }
    }

    public function testMixedUpdateOrderAndClearing(): void
    {
        $api = $this->api();
        $run = $this->invoke($api, ['task:update', '123', '--title', 'New', '--user-fields', '{"UF_QUERY":"","UF_MULTI":[]}', '--tag', '#x']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([1, 3, 1, 1], array_column($api->calls, 'version'));
        self::assertSame(['UF_QUERY' => '', 'UF_MULTI' => []], $api->calls[2]['parameters']['fields']);
        self::assertSame(['TAGS' => ['#x']], $api->calls[3]['parameters']['fields']);
    }

    public function testCustomFailureAfterOrdinaryUpdatePreservesPartialAndSkipsTags(): void
    {
        foreach ([false, true] as $unknown) {
            $api = $this->api(static function ($method, $version) use ($unknown): ApiResponse {
                if ($version === 1) {
                    throw new Failure('api-error', 'Custom failed.', 1, [], $unknown);
                }

                return new ApiResponse(['result' => true]);
            });
            $run = $this->invoke($api, ['task:update', '123', '--title', 'New', '--user-fields', '{"UF_QUERY":"5"}', '--tag', '#x']);
            self::assertSame(3, $run->status, $run->stdout);
            self::assertSame([1, 3, 1], array_column($api->calls, 'version'));
            self::assertSame(123, $run->json()['data']['items'][0]['taskId']);
            self::assertSame($unknown, $run->json()['meta']['outcomeUnknown']);
            self::assertSame('skipped', $run->json()['meta']['ledger'][2]['status']);
        }
    }

    public function testUnconfirmedAndDeniedSoleCustomWritesAreNotSuccess(): void
    {
        foreach ([new ApiResponse(['task' => ['id' => '999']]), new Failure('permission-denied', 'Denied.')] as $response) {
            $api = $this->api(static function () use ($response): ApiResponse {
                if ($response instanceof Failure) {
                    throw $response;
                }

                return $response;
            });
            $run = $this->invoke($api, ['task:update', '123', '--user-fields', '{"UF_QUERY":"5"}']);
            self::assertSame(1, $run->status, $run->stdout);
            self::assertCount(2, $api->calls);
            self::assertNotNull($run->json()['error']);
        }
    }

    public function testRejectedCreateDoesNotRunDependentTags(): void
    {
        $api = $this->api(static fn () => throw new Failure('api-error', 'Required custom value missing.'));
        $run = $this->invoke($api, [...self::CREATE, '--user-fields', '{"UF_QUERY":"5"}', '--tag', '#x']);
        self::assertSame(1, $run->status);
        self::assertSame(['tasks.task.getFields', 'tasks.task.add'], array_column($api->calls, 'method'));
    }

    public function testMalformedCreateAckAndCancellationRetainUnknownAndConfirmedOutcomes(): void
    {
        $api = $this->api(static fn (): ApiResponse => new ApiResponse(['result' => true]));
        $run = $this->invoke($api, [...self::CREATE, '--user-fields', '{"UF_QUERY":"5"}', '--tag', '#x']);
        self::assertSame(1, $run->status);
        self::assertTrue($run->json()['meta']['outcomeUnknown']);
        self::assertCount(2, $api->calls);

        $runtimeState = new \Bitrix24\CLI\Application\RuntimeState();
        $api = new FakeApiTransport(static function ($method, $version) use ($runtimeState): ApiResponse {
            if ($method === 'tasks.task.getFields') {
                return new ApiResponse(['fields' => ['UF_QUERY' => ['type' => 'string']]]);
            }

            self::assertSame(3, $version);
            $runtimeState->cancelled = true;
            return new ApiResponse(['result' => true]);
        });
        $updateTaskHandler = new \Bitrix24\CLI\Application\Task\Handler\UpdateTaskHandler(new \Bitrix24\CLI\Infrastructure\Bitrix24\Rest3TaskAdapter($api), new \Bitrix24\CLI\Application\Task\TaskTagMutation(new \Bitrix24\CLI\Infrastructure\Bitrix24\Rest1TaskTagAdapter($api)), new \Bitrix24\CLI\Infrastructure\Bitrix24\Rest1TaskUserFieldAdapter($api), $runtimeState);
        $prepared = $updateTaskHandler->prepare(new \Bitrix24\CLI\Application\Task\Request\UpdateTaskRequest(123, ['title' => 'x'], false, 'task:update', ['#x'], ['UF_QUERY' => '5']));
        $result = ($prepared->execute)();
        self::assertSame(130, $result->exitStatus);
        self::assertCount(2, $api->calls);
        self::assertSame(123, $result->data['items'][0]['taskId']);
        self::assertSame('skipped', $result->meta['ledger'][2]['status']);
    }

    public function testOrdinaryAdvancedIsOfflineAndRejectsWrongTypes(): void
    {
        $api = $this->api();
        $run = $this->invoke($api, ['task:add', '--fields', '{"title":"x","creatorId":1,"responsibleId":2}', '--dry-run']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([], $api->calls);
        foreach (['{"priority":2}', '{"needsControl":"Y"}', '{"fileIds":["1"]}', '{"crmItemIds":[1]}', '{"mark":"P"}', '{"status":"completed"}', '{"userFields":[]}', '{"creator":{}}'] as $fields) {
            $run = $this->invoke($api, ['task:update', '123', '--fields', $fields]);
            self::assertSame(2, $run->status, $run->stdout);
            self::assertSame([], $api->calls);
        }
    }
}
