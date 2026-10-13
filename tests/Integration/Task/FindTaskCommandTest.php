<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use Bitrix24\CLI\Tests\Support\TaskRoleFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:find')]
final class FindTaskCommandTest extends LiveTaskCommandTestCase
{
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
        self::assertSame([['id' => $first, 'title' => $firstTitle], ['id' => $second, 'title' => $secondTitle]], $result['data']['items']);
        self::assertSame(2, $result['meta']['matched']);
        self::assertSame(2, $result['meta']['returned']);
        self::assertFalse($result['meta']['limitApplied']);
    }
}
