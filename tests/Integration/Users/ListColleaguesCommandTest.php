<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Users;

use Bitrix24\CLI\Tests\Support\LiveUsersCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('users')]
#[Group('command:users:colleagues')]
final class ListColleaguesCommandTest extends LiveUsersCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathMatchesSelectedDepartmentMembership(): void
    {
        $department = $this->currentDepartment();
        $expected = $this->userRows(['USER_TYPE' => 'employee', 'ACTIVE' => true, 'UF_DEPARTMENT' => $department]);
        $expectedIds = array_values(array_filter(array_map(strval(...), array_column($expected, 'ID')), fn (string $id): bool => $id !== (string) $this->userId));
        $result = $this->invoke(['users:colleagues', '--department-id', $department, '--all']);
        self::assertEqualsCanonicalizing($expectedIds, array_column($result['data']['items'], 'ID'));
        self::assertSame($department, $result['meta']['departmentId']);
        foreach ($result['data']['items'] as $user) {
            self::assertContains($department, $user['UF_DEPARTMENT']);
            self::assertNotSame((string) $this->userId, $user['ID']);
        }
    }
}
