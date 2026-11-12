<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use Bitrix24\CLI\Tests\Support\DeadlineReadOnlyCases;
use Bitrix24\CLI\Tests\Support\TaskRoleFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:find')]
final class FindTaskCommandTest extends LiveTaskCommandTestCase
{
    use DeadlineReadOnlyCases;
    use TaskRoleFixtures;

    #[DataProvider('roleCases')]
    public function testRoleAndUnicodeTitleIncludeOnlyTheMatchingOwnedFixture(string $role): void
    {
        $needle = 'b24cli-роли-ёж-' . bin2hex(random_bytes(8));
        [$included] = $this->roleTaskPair($role, $needle);
        $result = $this->invoke(['task:find', '--title', mb_strtoupper($needle), '--role', $role, '--all']);
        self::assertSame([['id' => $included, 'title' => $needle . ' included']], $result['data']['items']);
        self::assertSame(['role' => $role, 'userId' => $this->userId], $result['meta']['roleFilter']);
        self::assertSame(1, $result['meta']['matched']);
    }

    #[Group('portal-acceptance')]
    public function testTitleRoleAndGroupUseTheServerFilter(): void
    {
        $group = $this->setting('B24CLI_TEST_GROUP_ID');
        $other = $this->setting('B24CLI_TEST_OTHER_USER_ID');
        if ($group === '' || $other === '') {
            self::markTestSkipped('Server filter acceptance needs B24CLI_TEST_GROUP_ID and B24CLI_TEST_OTHER_USER_ID.');
        }

        $needle = 'b24cli-группа-ёж-' . bin2hex(random_bytes(8));
        $match = $this->createTask($needle . ' match', ['groupId' => (int) $group]);
        $this->createTask($needle . ' other responsible', ['groupId' => (int) $group, 'responsibleId' => (int) $other]);
        $this->createTask($needle . ' outside group');
        $result = $this->invoke(['task:find', '--title', mb_strtoupper($needle), '--role', 'responsible', '--group-id', $group, '--all']);
        self::assertSame([['id' => $match, 'title' => $needle . ' match']], $result['data']['items']);
        self::assertTrue($result['meta']['complete']);
        self::assertSame('1.0', $result['meta']['listRoute']['apiVersion']);
        self::assertSame(['groupId', 'responsibleId'], $result['meta']['listRoute']['serverFilter']);
    }

    #[Group('happy-path')]
    public function testHappyPathReturnsTwoUnicodeTitleMatches(): void
    {
        $needle = 'b24cli-поиск-ёж-' . bin2hex(random_bytes(8));
        $firstTitle = 'Первый ' . $needle . ' договор';
        $secondTitle = 'Второй ' . $needle . ' договор';
        $first = $this->createTask($firstTitle);
        $second = $this->createTask($secondTitle);
        $result = $this->invoke(['task:find', '--title', mb_strtoupper($needle), '--all']);
        self::assertTrue($result['meta']['complete']);
        self::assertSame([['id' => $second, 'title' => $secondTitle], ['id' => $first, 'title' => $firstTitle]], $result['data']['items']);
        self::assertSame(['id' => 'DESC'], $result['meta']['order']);
        self::assertSame(2, $result['meta']['matched']);
        self::assertSame(2, $result['meta']['returned']);
        self::assertFalse($result['meta']['limitApplied']);
    }
    #[Group('portal-acceptance')]
    public function testBoundedScanCannotClaimCompleteFixtureSearch(): void
    {
        $needle = $this->fixtureTitle();
        $this->createTask($needle . '-first');
        $second = $this->createTask($needle . '-second');
        $result = $this->invoke(['task:find', '--title', $needle, '--max-scan', 1], [3]);
        self::assertFalse($result['meta']['complete']);
        self::assertSame('scan-budget-exhausted', $result['meta']['reason']);
        self::assertSame(1, $result['meta']['scanned']);
        self::assertSame([$second], array_column($result['data']['items'], 'id'));
        self::assertSame(['3.0'], $result['meta']['apiVersions']);
    }

}
