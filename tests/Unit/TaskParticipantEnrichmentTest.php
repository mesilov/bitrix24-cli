<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest1ParticipantAdapter;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest1UserAdapter;
use Bitrix24\CLI\Infrastructure\Bitrix24\SdkApiTransport;
use Bitrix24\CLI\Infrastructure\Connection\B24ClientProvider;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TaskParticipantEnrichmentTest extends TestCase
{
    private function invoke(array $argv, \Closure $respond): array
    {
        $fakeApiTransport = new FakeApiTransport($respond);
        $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport), ['--json', 'task:show', '123', ...$argv]);
        return [$run, $fakeApiTransport];
    }

    private static function user(int $id): array
    {
        return ['ID' => (string) $id, 'NAME' => 'User' . $id, 'SECOND_NAME' => '', 'LAST_NAME' => 'Last', 'EMAIL' => 'private@example.invalid'];
    }

    public function testRolesShareOneFilteredLookupAndRetainTheirOwnOrder(): void
    {
        [$run, $api] = $this->invoke(['--select', 'title', '--select', 'auditors', '--select', 'accomplices', '--select', 'auditors.id'], static fn ($method, $version): ApiResponse => match ([$method, $version]) {
            ['tasks.task.get', 3] => new ApiResponse(['item' => ['id' => 123, 'title' => 'v3 title']]),
            ['tasks.task.get', 1] => new ApiResponse(['task' => ['id' => '123', 'title' => 'legacy must not overwrite', 'auditors' => ['3', '2', '3'], 'accomplices' => ['2', '4'], 'auditorsData' => ['private']]]),
            ['user.get', 1] => new ApiResponse([self::user(4), self::user(2), self::user(3)]),
            default => throw new \LogicException('Unexpected API call.'),
        });
        self::assertSame(0, $run->status, $run->stdout);
        $json = $run->json();
        self::assertSame('v3 title', $json['data']['task']['title']);
        self::assertSame([['id' => 3, 'name' => 'User3 Last'], ['id' => 2, 'name' => 'User2 Last']], $json['data']['task']['auditors']);
        self::assertSame([2, 4], array_column($json['data']['task']['accomplices'], 'id'));
        self::assertSame(['id', 'title'], $api->calls[0]['parameters']['select']);
        self::assertSame(['taskId' => 123, 'select' => ['ID', 'AUDITORS', 'ACCOMPLICES']], $api->calls[1]['parameters']);
        self::assertSame(['filter' => ['@ID' => [3, 2, 4]], 'select' => ['ID', 'NAME', 'SECOND_NAME', 'LAST_NAME']], $api->calls[2]['parameters']);
        self::assertCount(3, $api->calls);
        self::assertTrue($json['meta']['complete']);
        self::assertStringNotContainsString('private', $run->stdout);
    }

    public function testIdOnlyDoesNotReadUserProfiles(): void
    {
        [$run, $api] = $this->invoke(['--select', 'auditors.id'], static fn ($method, $version): ApiResponse => $version === 3
            ? new ApiResponse(['item' => ['id' => 123]])
            : new ApiResponse(['task' => ['id' => '123', 'auditors' => ['2']]]));
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([['id' => 2]], $run->json()['data']['task']['auditors']);
        self::assertCount(2, $api->calls);
        self::assertSame(['id'], $api->calls[0]['parameters']['select']);
    }

    public function testEmailUsesTheSameLookupAndDoesNotLeakIntoOtherRoles(): void
    {
        [$run, $api] = $this->invoke(['--select', 'auditors', '--select', 'auditors.email', '--select', 'accomplices'], static fn ($method, $version): ApiResponse => match ([$method, $version]) {
            ['tasks.task.get', 3] => new ApiResponse(['item' => ['id' => 123]]),
            ['tasks.task.get', 1] => new ApiResponse(['task' => ['id' => '123', 'auditors' => ['2'], 'accomplices' => ['2']]]),
            ['user.get', 1] => new ApiResponse([self::user(2)]),
            default => throw new \LogicException('Unexpected API call.'),
        });
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([['id' => 2, 'name' => 'User2 Last', 'email' => 'private@example.invalid']], $run->json()['data']['task']['auditors']);
        self::assertSame([['id' => 2, 'name' => 'User2 Last']], $run->json()['data']['task']['accomplices']);
        self::assertSame(['filter' => ['@ID' => [2]], 'select' => ['ID', 'NAME', 'SECOND_NAME', 'LAST_NAME', 'EMAIL']], $api->calls[2]['parameters']);
        self::assertCount(3, $api->calls);
    }

    public function testEmptyRolesNeedNoUserLookup(): void
    {
        [$run, $api] = $this->invoke(['--select', 'auditors', '--select', 'accomplices'], static fn ($method, $version): ApiResponse => $version === 3
            ? new ApiResponse(['item' => ['id' => 123]])
            : new ApiResponse(['task' => ['id' => '123', 'auditors' => [], 'accomplices' => []]]));
        self::assertSame(0, $run->status);
        self::assertSame([], $run->json()['data']['task']['auditors']);
        self::assertSame([], $run->json()['data']['task']['accomplices']);
        self::assertCount(2, $api->calls);
    }

    public function testDefaultStrictCardStaysOneRest3Read(): void
    {
        [$run, $api] = $this->invoke(['--api-policy', 'strict-rest3'], static fn (): ApiResponse => new ApiResponse(['item' => ['id' => 123, 'title' => 'Card']]));
        self::assertSame(0, $run->status);
        self::assertCount(1, $api->calls);
        self::assertSame(3, $api->calls[0]['version']);
    }

    public static function localGates(): iterable
    {
        yield 'strict enrichment' => [['--select', 'auditors', '--api-policy', 'strict-rest3'], 4];
        yield 'strict ids' => [['--select', 'accomplices.id', '--api-policy', 'strict-rest3'], 4];
        yield 'unsupported image' => [['--select', 'auditors.image'], 2];
        yield 'unsupported role' => [['--select', 'auditors.role'], 2];
    }

    #[DataProvider('localGates')]
    public function testPolicyAndUnsupportedFieldsReadNeitherCredentialsNorApi(array $argv, int $status): void
    {
        $spyConnectionResolver = new SpyConnectionResolver();
        $fakeApiTransport = new FakeApiTransport(static fn (): ApiResponse => throw new \LogicException('Unexpected API call.'));
        $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver), ['--json', 'task:show', '123', ...$argv]);
        self::assertSame($status, $run->status, $run->stdout);
        self::assertSame(0, $spyConnectionResolver->reads);
        self::assertSame([], $fakeApiTransport->calls);
    }

    public static function failures(): iterable
    {
        yield 'legacy permission' => [1, 'permission-denied', 3, 2];
        yield 'user permission' => [2, 'permission-denied', 3, 3];
        yield 'user transport' => [2, 'transport-error', 3, 3];
        yield 'legacy interruption' => [1, 'interrupted', 130, 2];
        yield 'user interruption' => [2, 'interrupted', 130, 3];
        yield 'base denial' => [0, 'permission-denied', 1, 1];
    }

    #[DataProvider('failures')]
    public function testReadFailuresRetainAvailableCardWithoutRetry(int $step, string $code, int $status, int $calls): void
    {
        $position = 0;
        [$run, $api] = $this->invoke(['--select', 'title', '--select', 'auditors'], static function () use (&$position, $step, $code): ApiResponse {
            $current = $position++;
            if ($current === $step) {
                throw new Failure($code, 'Safe fixture failure.', $code === 'interrupted' ? 130 : 1);
            }

            return $current === 0 ? new ApiResponse(['item' => ['id' => 123, 'title' => 'Card']]) : new ApiResponse(['task' => ['id' => '123', 'auditors' => ['2']]]);
        });
        self::assertSame($status, $run->status, $run->stdout);
        self::assertCount($calls, $api->calls);
        if ($status === 3) {
            $json = $run->json();
            self::assertSame('Card', $json['data']['task']['title']);
            self::assertFalse($json['meta']['complete']);
            self::assertSame($code, $json['meta']['participantEnrichment']['errorCode']);
            self::assertSame($step === 1 ? null : [['id' => 2, 'name' => null]], $json['data']['task']['auditors']);
        }
    }

    public function testMissingUsersAndScopeHiddenEmailStayExplicitlyPartial(): void
    {
        [$run, $api] = $this->invoke(['--select', 'auditors', '--select', 'auditors.email'], static fn ($method, $version): ApiResponse => match ([$method, $version]) {
            ['tasks.task.get', 3] => new ApiResponse(['item' => ['id' => 123]]),
            ['tasks.task.get', 1] => new ApiResponse(['task' => ['id' => '123', 'auditors' => ['2', '3']]]),
            ['user.get', 1] => new ApiResponse([array_diff_key(self::user(2), ['EMAIL' => true])]),
            default => throw new \LogicException('Unexpected API call.'),
        });
        self::assertSame(3, $run->status, $run->stdout);
        $json = $run->json();
        self::assertSame([['id' => 2, 'name' => 'User2 Last', 'email' => null], ['id' => 3, 'name' => null, 'email' => null]], $json['data']['task']['auditors']);
        self::assertSame([3], $json['meta']['participantEnrichment']['missingUserIds']);
        self::assertSame(['email'], $json['meta']['participantEnrichment']['missingFields'][2]);
        self::assertSame('partial-result', $json['error']['code']);
        self::assertCount(3, $api->calls);
    }

    public static function malformedMembership(): iterable
    {
        yield 'wrong task' => [['id' => '999', 'auditors' => ['2']]];
        yield 'missing task id' => [['auditors' => ['2']]];
        yield 'missing role' => [['id' => '123']];
        yield 'scalar role' => [['id' => '123', 'auditors' => '2']];
        yield 'object user' => [['id' => '123', 'auditors' => [['id' => 2]]]];
        yield 'bad id' => [['id' => '123', 'auditors' => ['2x']]];
        yield 'zero' => [['id' => '123', 'auditors' => ['0']]];
        yield 'overflow' => [['id' => '123', 'auditors' => ['999999999999999999999999']]];
    }

    #[DataProvider('malformedMembership')]
    public function testUnverifiedMembershipIsNotEmptyOrEnriched(array $legacyTask): void
    {
        [$run, $api] = $this->invoke(['--select', 'auditors'], static fn ($method, $version): ApiResponse => $version === 3
            ? new ApiResponse(['item' => ['id' => 123]]) : new ApiResponse(['task' => $legacyTask]));
        self::assertSame(3, $run->status);
        self::assertNull($run->json()['data']['task']['auditors']);
        self::assertCount(2, $api->calls);
    }

    public static function malformedUsers(): iterable
    {
        yield 'foreign' => [[self::user(999)]];
        yield 'duplicate' => [[self::user(2), self::user(2)]];
        yield 'missing ID' => [[['NAME' => 'User']]];
        yield 'invalid ID' => [[['ID' => '2x']]];
        yield 'scalar row' => [['bad']];
        yield 'object wrapper' => [['items' => [self::user(2)]]];
        yield 'wrong field type' => [[['ID' => '2', 'NAME' => [], 'LAST_NAME' => '', 'SECOND_NAME' => '']]];
    }

    #[DataProvider('malformedUsers')]
    public function testUntrustedUserRowsNeverLeakIntoTheCard(array $rows): void
    {
        [$run, $api] = $this->invoke(['--select', 'auditors'], static fn ($method, $version): ApiResponse => match ([$method, $version]) {
            ['tasks.task.get', 3] => new ApiResponse(['item' => ['id' => 123]]),
            ['tasks.task.get', 1] => new ApiResponse(['task' => ['id' => '123', 'auditors' => ['2']]]),
            ['user.get', 1] => new ApiResponse($rows),
            default => throw new \LogicException('Unexpected API call.'),
        });
        self::assertSame(3, $run->status, $run->stdout);
        self::assertSame([['id' => 2, 'name' => null]], $run->json()['data']['task']['auditors']);
        self::assertCount(3, $api->calls);
    }

    public function testLookupChunksAtFiftyAndHasAFiniteBudget(): void
    {
        $fakeApiTransport = new FakeApiTransport(static fn ($method, $version, $params): ApiResponse => new ApiResponse(array_map(self::user(...), $params['filter']['@ID'])));
        $users = (new Rest1UserAdapter($fakeApiTransport))->byIds(range(1, 51), ['name']);
        self::assertCount(51, $users);
        self::assertCount(2, $fakeApiTransport->calls);
        self::assertCount(50, $fakeApiTransport->calls[0]['parameters']['filter']['@ID']);
        self::assertSame([51], $fakeApiTransport->calls[1]['parameters']['filter']['@ID']);
        $fakeApiTransport->calls = [];
        $users = (new Rest1UserAdapter($fakeApiTransport))->byIds(range(1, 10001), ['name']);
        self::assertCount(10000, $users);
        self::assertArrayNotHasKey(10001, $users);
        self::assertCount(200, $fakeApiTransport->calls);
    }

    public function testLegacyIdsAndUsersThroughTheActualSdkHttpCodec(): void
    {
        $mockHttpClient = new MockHttpClient([
            new MockResponse(json_encode(['result' => ['task' => ['id' => '123', 'auditors' => ['2']]], 'time' => []], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['result' => [self::user(2)], 'time' => []], JSON_THROW_ON_ERROR)),
        ]);
        $runtimeState = new RuntimeState();
        $sdkApiTransport = new SdkApiTransport(new B24ClientProvider(new SpyConnectionResolver(), $runtimeState, $mockHttpClient), $runtimeState);
        self::assertSame(['auditors' => [2]], (new Rest1ParticipantAdapter($sdkApiTransport))->ids(123, ['auditors']));
        self::assertSame([2 => ['id' => 2, 'name' => 'User2 Last']], (new Rest1UserAdapter($sdkApiTransport))->byIds([2], ['name']));
        self::assertSame(2, $mockHttpClient->getRequestsCount());
        self::assertSame(['1.0', '1.0'], array_column($runtimeState->calls, 'apiVersion'));
    }
}
