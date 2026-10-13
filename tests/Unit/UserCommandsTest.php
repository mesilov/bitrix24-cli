<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Application\User\UserQuery;
use Bitrix24\CLI\Application\User\UserScanner;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest1UserDirectoryAdapter;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\FixtureDirectory;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserCommandsTest extends TestCase
{
    private function invoke(array $argv, ?\Closure $respond = null): array
    {
        $fixtureDirectory = new FixtureDirectory();
        $fakeApiTransport = new FakeApiTransport($respond ?? $fixtureDirectory->respond(...));
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        $result = ConsoleHarness::run($app, ['--json', ...$argv]);
        return [$result->status, $result->json(), $fakeApiTransport->calls, $spyConnectionResolver->reads];
    }

    public function testCurrentPreservesApiNamesAndCanonicalIdentity(): void
    {
        [$status, $json, $calls] = $this->invoke(['users:current']);
        self::assertSame(0, $status);
        self::assertSame('1', $json['data']['user']['ID']);
        self::assertSame([7], $json['data']['user']['UF_DEPARTMENT']);
        self::assertSame([], $json['meta']['unavailableFields']);
        self::assertSame(['method' => 'user.current', 'version' => 1, 'parameters' => [], 'effect' => 'read'], $calls[0]);
        [$status, $json] = $this->invoke(['users:current'], static fn (): ApiResponse => new ApiResponse(['ID' => 5]));
        self::assertSame(0, $status);
        self::assertSame(['ID' => '5'], $json['data']['user']);
        self::assertContains('UF_DEPARTMENT', $json['meta']['unavailableFields']);
    }

    public static function invalidInputs(): iterable
    {
        yield 'no search' => [['users:find']];
        yield 'both criteria' => [['users:find', '--name', 'Иван', '--email', 'ivan@example.test']];
        yield 'blank name' => [['users:find', '--name', '  ']];
        yield 'unicode whitespace' => [['users:find', '--name', "\u{00A0}\u{2003}"]];
        yield 'invalid utf8' => [['users:find', '--name', "\xff"]];
        yield 'email wildcard' => [['users:find', '--email', '*@example.test']];
        yield 'email domain' => [['users:find', '--email', 'example.test']];
        yield 'negative budget' => [['users:find', '--name', 'Иван', '--max-scan', '-1']];
        yield 'all and limit' => [['users:colleagues', '--all', '--limit', '1']];
        yield 'zero department' => [['users:colleagues', '--department-id', '0']];
        yield 'wrong manager source' => [['users:manager', '--source', 'maybe']];
        yield 'config department conflict' => [['users:manager', '--source', 'config', '--department-id', '7']];
    }

    #[DataProvider('invalidInputs')]
    public function testInvalidInputsDoNotReadCredentialsOrCallApi(array $argv): void
    {
        [$status, $json, $calls, $reads] = $this->invoke($argv);
        self::assertSame(2, $status, json_encode($json));
        self::assertSame('usage-error', $json['error']['code']);
        self::assertSame([], $calls);
        self::assertSame(0, $reads);
    }

    public function testNameAndEmailSearchAlwaysReturnCandidateLists(): void
    {
        $fixtureDirectory = new FixtureDirectory();
        $fixtureDirectory->users[] = [...$fixtureDirectory->current, 'ID' => '3'];
        $fixtureDirectory->users[] = [...$fixtureDirectory->users[1], 'ID' => '4', 'WORK_POSITION' => 'Иванов Иван'];
        [$status, $json, $calls] = $this->invoke(['users:find', '--name', 'иванов ИВАН'], $fixtureDirectory->respond(...));
        self::assertSame(0, $status);
        self::assertSame(['1', '3'], array_column($json['data']['items'], 'ID'));
        self::assertSame(['USER_TYPE' => 'employee', 'ACTIVE' => true], $calls[0]['parameters']['FILTER']);
        [$status, $json, $calls] = $this->invoke(['users:find', '--email', 'ivan@example.test'], $fixtureDirectory->respond(...));
        self::assertSame(0, $status);
        self::assertCount(2, $json['data']['items']);
        self::assertSame('ivan@example.test', $calls[0]['parameters']['FILTER']['EMAIL']);
        [$status, $json] = $this->invoke(['users:find', '--name', 'Несуществующий']);
        self::assertSame(0, $status);
        self::assertSame([], $json['data']['items']);
        self::assertTrue($json['meta']['complete']);
    }

    private function manyUsers(int $count): FixtureDirectory
    {
        $fixtureDirectory = new FixtureDirectory();
        $fixtureDirectory->users = array_map(static fn (int $id): array => [...$fixtureDirectory->current, 'ID' => (string) $id], range(1, $count));
        return $fixtureDirectory;
    }

    public function testPaginationOutputLimitAndScanBudgetAreIndependent(): void
    {
        $directory = $this->manyUsers(70);
        [$status, $json, $calls] = $this->invoke(['users:find', '--name', 'Иван'], $directory->respond(...));
        self::assertSame(0, $status);
        self::assertSame(70, $json['meta']['scanned']);
        self::assertSame(70, $json['meta']['matched']);
        self::assertCount(50, $json['data']['items']);
        self::assertTrue($json['meta']['limitApplied']);
        self::assertTrue($json['meta']['complete']);
        self::assertSame([0, 50], array_column(array_column($calls, 'parameters'), 'start'));
        [$status, $json] = $this->invoke(['users:find', '--name', 'Иван', '--all', '--max-scan', '10'], $directory->respond(...));
        self::assertSame(3, $status);
        self::assertCount(10, $json['data']['items']);
        self::assertSame(10, $json['meta']['scanned']);
        self::assertFalse($json['meta']['complete']);
        $directory->users[60]['NAME'] = 'Поздний';
        [$status, $json] = $this->invoke(['users:find', '--name', 'Поздний'], $directory->respond(...));
        self::assertSame(0, $status);
        self::assertSame(['61'], array_column($json['data']['items'], 'ID'));
    }

    public function testIncompleteNamesAndLaterPageErrorsRetainKnownMatches(): void
    {
        $directory = $this->manyUsers(60);
        foreach (['failure', 'cursor', 'duplicate'] as $mode) {
            [$status, $json] = $this->invoke(['users:find', '--name', 'Иван', '--all'], static function ($method, int $version, array $params) use ($directory, $mode): ApiResponse {
                if ($params['start'] === 50) {
                    return match ($mode) {
                        'failure' => throw new Failure('permission-denied', 'Later page denied.'),
                        'cursor' => new ApiResponse([$directory->current], 50),
                        'duplicate' => new ApiResponse([$directory->current]),
                    };
                }

                return $directory->respond($method, $version, $params);
            });
            self::assertSame(3, $status);
            self::assertCount(50, $json['data']['items']);
            self::assertSame('partial-result', $json['error']['code']);
        }

        [$status, $json] = $this->invoke(['users:find', '--name', 'Иван'], static fn (): ApiResponse => new ApiResponse([['ID' => '8']]));
        self::assertSame(3, $status);
        self::assertSame([], $json['data']['items']);
        self::assertContains('NAME', $json['meta']['unavailableFields']);
        self::assertContains('LAST_NAME', $json['meta']['unavailableFields']);
    }

    public static function malformedRows(): iterable
    {
        yield 'missing ID' => [[['NAME' => 'Иван']]];
        yield 'invalid ID' => [[['ID' => 'wrong']]];
        yield 'duplicate ID' => [[['ID' => '1'], ['ID' => '1']]];
        yield 'scalar row' => [['bad']];
        yield 'invalid name' => [[['ID' => '1', 'NAME' => false]]];
        yield 'invalid departments' => [[['ID' => '1', 'UF_DEPARTMENT' => ['bad']]]];
        yield 'non-list page' => [['user' => ['ID' => '1']]];
    }

    #[DataProvider('malformedRows')]
    public function testMalformedDirectoryResponsesFailWithoutInventingUsers(array $rows): void
    {
        [$status, $json] = $this->invoke(['users:find', '--name', 'Иван'], static fn (): ApiResponse => new ApiResponse($rows));
        self::assertSame(1, $status);
        self::assertSame('api-error', $json['error']['code']);
        self::assertNull($json['data']);
    }

    public function testColleaguesStayInChosenDepartmentAndExcludeSelf(): void
    {
        $fixtureDirectory = new FixtureDirectory();
        $fixtureDirectory->users[] = [...$fixtureDirectory->current, 'ID' => '3', 'UF_DEPARTMENT' => [8]];
        [$status, $json, $calls] = $this->invoke(['users:colleagues'], $fixtureDirectory->respond(...));
        self::assertSame(0, $status);
        self::assertSame(['2'], array_column($json['data']['items'], 'ID'));
        self::assertSame(7, $json['meta']['departmentId']);
        self::assertSame(7, $calls[1]['parameters']['FILTER']['UF_DEPARTMENT']);
        $fixtureDirectory->users = [$fixtureDirectory->current];
        [$status, $json] = $this->invoke(['users:colleagues'], $fixtureDirectory->respond(...));
        self::assertSame(0, $status);
        self::assertSame([], $json['data']['items']);
    }

    public function testAmbiguousMissingAndForeignDepartmentsAreExplicit(): void
    {
        $fixtureDirectory = new FixtureDirectory();
        $fixtureDirectory->current['UF_DEPARTMENT'] = [7, 9];
        [$status, $json, $calls] = $this->invoke(['users:colleagues'], $fixtureDirectory->respond(...));
        self::assertSame(2, $status);
        self::assertSame('ambiguous-department', $json['error']['code']);
        self::assertSame([7, 9], $json['error']['details']['departments']);
        self::assertCount(1, $calls);
        [$status, , $calls] = $this->invoke(['users:colleagues', '--department-id', '15'], $fixtureDirectory->respond(...));
        self::assertSame(2, $status);
        self::assertCount(1, $calls);
        [$status, $json] = $this->invoke(['users:colleagues', '--department-id', '9'], $fixtureDirectory->respond(...));
        self::assertSame(0, $status);
        self::assertSame(9, $json['meta']['departmentId']);
        unset($fixtureDirectory->current['UF_DEPARTMENT']);
        [$status, $json] = $this->invoke(['users:manager'], $fixtureDirectory->respond(...));
        self::assertSame(1, $status);
        self::assertSame('organization-unavailable', $json['error']['code']);
    }

    public function testManagerFollowsHeadThenParentAndHandlesNoManager(): void
    {
        $fixtureDirectory = new FixtureDirectory();
        [$status, $json, $calls] = $this->invoke(['users:manager'], $fixtureDirectory->respond(...));
        self::assertSame(0, $status);
        self::assertSame('2', $json['data']['user']['ID']);
        self::assertSame('org', $json['meta']['source']);
        self::assertSame(['ID' => 2], $calls[2]['parameters']['FILTER']);
        $fixtureDirectory->departments = [7 => ['ID' => 7, 'UF_HEAD' => 1, 'PARENT' => 9], 9 => ['ID' => 9, 'UF_HEAD' => 2, 'PARENT' => 0]];
        [$status, $json] = $this->invoke(['users:manager'], $fixtureDirectory->respond(...));
        self::assertSame(0, $status);
        self::assertSame([7, 9], $json['meta']['departmentChain']);
        $fixtureDirectory->departments[9]['UF_HEAD'] = 0;
        [$status, $json] = $this->invoke(['users:manager'], $fixtureDirectory->respond(...));
        self::assertSame(0, $status);
        self::assertNull($json['data']['user']);
        self::assertSame('no-manager', $json['meta']['reason']);
    }

    public function testManagerRejectsCyclesUnavailableHeadsAndBrokenParents(): void
    {
        $fixtureDirectory = new FixtureDirectory();
        foreach ([
            [7 => ['ID' => 7, 'PARENT' => 7]],
            [7 => ['ID' => 7]],
            [7 => ['ID' => 7, 'UF_HEAD' => 999]],
            [],
            array_combine(range(7, 107), array_map(static fn (int $id): array => ['ID' => $id, 'PARENT' => $id + 1], range(7, 107))),
        ] as $departments) {
            $fixtureDirectory->departments = $departments;
            [$status, $json] = $this->invoke(['users:manager'], $fixtureDirectory->respond(...));
            self::assertSame(1, $status);
            self::assertSame('organization-unavailable', $json['error']['code']);
        }
    }

    public function testExistingStrictPolicyBlocksAllUserRoutesBeforeCredentials(): void
    {
        foreach (['current', 'find', 'colleagues', 'manager'] as $action) {
            [$status, $json, $calls, $reads] = $this->invoke(['--api-policy', 'strict-rest3', 'users:' . $action, ...($action === 'find' ? ['--name', 'Иван'] : [])]);
            self::assertSame(4, $status);
            self::assertSame('policy-denied', $json['error']['code']);
            self::assertSame([], $calls);
            self::assertSame(0, $reads);
        }
    }

    public function testUserOutputProfilesAndOfflineDiscovery(): void
    {
        $fixtureDirectory = new FixtureDirectory();
        $fixtureDirectory->current['NAME'] = "Иван\t\n<info>";
        $fakeApiTransport = new FakeApiTransport($fixtureDirectory->respond(...));
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        $plain = ConsoleHarness::run($app, ['users:current', '--plain']);
        self::assertSame(0, $plain->status);
        self::assertCount(6, explode("\t", trim($plain->stdout)));
        self::assertStringContainsString('\\t\\n', $plain->stdout);
        self::assertStringNotContainsString("\033[", $plain->stdout);
        $human = ConsoleHarness::run($app, ['users:current', '--no-ansi']);
        self::assertStringContainsString('<info>', $human->stdout);
        $quiet = ConsoleHarness::run($app, ['users:current', '--json', '--quiet']);
        self::assertSame('', $quiet->stdout);
        $before = count($fakeApiTransport->calls);
        foreach ([['list', 'users'], ['users:find', '--help'], ['users:manager', '--help'], ['completion', 'bash']] as $argv) {
            self::assertSame(0, ConsoleHarness::run($app, $argv)->status);
        }

        self::assertCount($before, $fakeApiTransport->calls);
        self::assertSame(0, $spyConnectionResolver->reads);
    }

    public function testCancellationRetainsConfirmedMatches(): void
    {
        $runtimeState = new RuntimeState();
        $fixtureDirectory = new FixtureDirectory();
        $userScanner = new UserScanner(new Rest1UserDirectoryAdapter(new FakeApiTransport($fixtureDirectory->respond(...))), $runtimeState);
        $result = $userScanner->scan(new UserQuery(), [], static function () use ($runtimeState): bool {
            $runtimeState->cancelled = true;
            return true;
        });
        self::assertSame(130, $result->exitStatus);
        self::assertCount(1, $result->data['items']);
        self::assertFalse($result->meta['complete']);
    }
}
