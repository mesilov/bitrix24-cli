<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest1TaskTagAdapter;
use Bitrix24\CLI\Infrastructure\Bitrix24\SdkApiTransport;
use Bitrix24\CLI\Infrastructure\Connection\B24ClientProvider;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TaskTagCodecTest extends TestCase
{
    public static function rejectedAcks(): iterable
    {
        foreach ([false, null, true, [], ['task' => ['id' => '999']], ['task' => ['id' => '123x']]] as $index => $value) {
            yield (string) $index => [$value];
        }
    }

    #[DataProvider('rejectedAcks')]
    public function testMalformedAckRetainsUnknownOutcome(mixed $result): void
    {
        $mockHttpClient = new MockHttpClient(new MockResponse(json_encode(['result' => $result], JSON_THROW_ON_ERROR)));
        $runtimeState = new RuntimeState();
        $sdkApiTransport = new SdkApiTransport(new B24ClientProvider(new SpyConnectionResolver(), $runtimeState, $mockHttpClient), $runtimeState);
        try {
            (new Rest1TaskTagAdapter($sdkApiTransport))->set(123, ['#ошибка']);
            self::fail('Malformed write acknowledgement must not pass.');
        } catch (Failure $failure) {
            self::assertSame('api-error', $failure->errorCode);
            self::assertTrue($failure->outcomeUnknown);
            self::assertSame(['taskId' => 123], $failure->details);
        }

        self::assertSame(1, $mockHttpClient->getRequestsCount());
    }

    public function testActualSdkCodecPreservesEmptyArrayAndUnicodePayload(): void
    {
        foreach ([[], ['#плановая', 'Этап 1']] as $TAGS) {
            $client = new MockHttpClient(static function (string $method, string $url, array $options) use ($TAGS): MockResponse {
                self::assertSame('POST', $method);
                self::assertStringContainsString('/rest/1/secret-fixture/', $url);
                self::assertStringContainsString('/tasks.task.update/', parse_url($url, PHP_URL_PATH));
                self::assertSame(['taskId' => 123, 'fields' => ['TAGS' => $TAGS]], json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR));
                return new MockResponse('{"result":{"task":{"id":"123"}}}');
            });
            $state = new RuntimeState();
            $api = new SdkApiTransport(new B24ClientProvider(new SpyConnectionResolver(), $state, $client), $state);
            (new Rest1TaskTagAdapter($api))->set(123, $TAGS);
            self::assertSame([['method' => 'tasks.task.update', 'apiVersion' => '1.0', 'effect' => 'write']], $state->calls);
        }
    }

    public function testCancellationBetweenConfirmedCreateAndTagsDoesNotSendSecondRequest(): void
    {
        $runtimeState = new RuntimeState();
        $mockHttpClient = new MockHttpClient(static function () use ($runtimeState): MockResponse {
            $runtimeState->cancelled = true;
            return new MockResponse('{"result":{"item":{"id":123}}}');
        });
        $sdkApiTransport = new SdkApiTransport(new B24ClientProvider(new SpyConnectionResolver(), $runtimeState, $mockHttpClient), $runtimeState);
        $addTaskHandler = new \Bitrix24\CLI\Application\Task\Handler\AddTaskHandler(new \Bitrix24\CLI\Infrastructure\Bitrix24\Rest3TaskAdapter($sdkApiTransport), new \Bitrix24\CLI\Infrastructure\Bitrix24\Rest1ParticipantAdapter($sdkApiTransport), $runtimeState, new \Bitrix24\CLI\Application\Task\TaskTagMutation(new Rest1TaskTagAdapter($sdkApiTransport)), new \Bitrix24\CLI\Infrastructure\Bitrix24\Rest1TaskUserFieldAdapter($sdkApiTransport));
        $prepared = $addTaskHandler->prepare(new \Bitrix24\CLI\Application\Task\Request\AddTaskRequest(['title' => 'Fixture', 'creatorId' => 1, 'responsibleId' => 1], false, TAGS: ['#плановая']));
        $result = ($prepared->execute)();
        self::assertSame(130, $result->exitStatus);
        self::assertSame(123, $result->data['items'][0]['taskId']);
        self::assertSame(1, $mockHttpClient->getRequestsCount());
        self::assertFalse($result->meta['outcomeUnknown']);
    }
}
