<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Offline;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest1ChecklistAdapter;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest1ParticipantAdapter;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest1TimeEntryAdapter;
use Bitrix24\CLI\Infrastructure\Bitrix24\SdkApiTransport;
use Bitrix24\CLI\Infrastructure\Connection\B24ClientProvider;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class LegacyWriteResponsesTest extends TestCase
{
    private function execute(string $operation, mixed $result): void
    {
        $mockHttpClient = new MockHttpClient(new MockResponse(json_encode(['result' => $result, 'time' => []], JSON_THROW_ON_ERROR)));
        $runtimeState = new RuntimeState();
        $sdkApiTransport = new SdkApiTransport(new B24ClientProvider(new SpyConnectionResolver(), $runtimeState, $mockHttpClient), $runtimeState);
        match ($operation) {
            'time.update' => (new Rest1TimeEntryAdapter($sdkApiTransport))->update(123, 30, 120, null),
            'time.delete' => (new Rest1TimeEntryAdapter($sdkApiTransport))->delete(123, 30),
            'checklist.update' => (new Rest1ChecklistAdapter($sdkApiTransport))->update(123, 11, 'Changed'),
            'checklist.complete' => (new Rest1ChecklistAdapter($sdkApiTransport))->complete(123, 11),
            'checklist.renew' => (new Rest1ChecklistAdapter($sdkApiTransport))->renew(123, 11),
            'checklist.delete' => (new Rest1ChecklistAdapter($sdkApiTransport))->delete(123, 11),
            'participants' => (new Rest1ParticipantAdapter($sdkApiTransport))->set(123, null, [2]),
            default => throw new \LogicException('Unsupported fixture operation.'),
        };
        self::assertSame(1, $mockHttpClient->getRequestsCount());
        self::assertSame('1.0', $runtimeState->calls[0]['apiVersion']);
    }

    public static function acknowledgements(): iterable
    {
        yield 'time update null' => ['time.update', null];
        yield 'time delete null' => ['time.delete', null];
        yield 'checklist update null' => ['checklist.update', null];
        yield 'checklist complete boolean' => ['checklist.complete', true];
        yield 'checklist renew boolean' => ['checklist.renew', true];
        yield 'checklist delete boolean' => ['checklist.delete', true];
        yield 'participants task object' => ['participants', ['task' => ['id' => '123']]];
    }

    #[DataProvider('acknowledgements')]
    public function testDocumentedResponsesThroughActualSdkCodec(string $operation, mixed $result): void
    {
        $this->execute($operation, $result);
    }

    public static function rejectedResponses(): iterable
    {
        foreach (['time.update', 'time.delete', 'checklist.update'] as $operation) {
            foreach ([false, true, [], ['unexpected' => null]] as $index => $result) {
                yield $operation . '-' . $index => [$operation, $result];
            }
        }

        foreach ([null, true, [], ['task' => ['id' => '999']], ['task' => ['id' => '123x']]] as $index => $result) {
            yield 'participants-' . $index => ['participants', $result];
        }

        yield 'boolean rejects null' => ['checklist.complete', null];
        yield 'boolean rejects false' => ['checklist.delete', false];
    }

    #[DataProvider('rejectedResponses')]
    public function testMalformedOrWrongTargetResponseIsNotAcknowledged(string $operation, mixed $result): void
    {
        $this->expectException(Failure::class);
        $this->execute($operation, $result);
    }
}
