<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Users;

use Bitrix24\CLI\Tests\Support\LiveUsersCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('users')]
#[Group('command:users:find')]
final class FindUsersCommandTest extends LiveUsersCommandTestCase
{
    public function testNameSearchReportsMissingDirectoryFieldsAndRetainsKnownMatches(): void
    {
        $employees = $this->userRows(['USER_TYPE' => 'employee', 'ACTIVE' => true]);
        $employee = array_find($employees, static fn (array $row): bool => trim($row['NAME'] ?? '') !== '' && array_key_exists('LAST_NAME', $row));
        if ($employee === null) {
            self::markTestSkipped('No visible active employee with name fields for a search fixture.');
        }

        $result = $this->invoke(['users:find', '--name', $employee['NAME'], '--all'], [0, 3]);
        if (!$result['meta']['complete']) {
            self::assertSame('partial-result', $result['error']['code']);
            self::assertSame('Required user fields are unavailable for matching; check user scope.', $result['meta']['reason']);
        }

        self::assertContains((string) $employee['ID'], array_column($result['data']['items'], 'ID'));
        foreach ($result['data']['items'] as $user) {
            $fullName = implode(' ', [$user['NAME'], $user['SECOND_NAME'] ?? '', $user['LAST_NAME']]);
            self::assertStringContainsString(mb_strtolower(trim($employee['NAME'])), mb_strtolower($fullName));
        }
    }

    #[Group('happy-path')]
    public function testHappyPathFindsEmployeeByExactEmail(): void
    {
        $employees = $this->userRows(['USER_TYPE' => 'employee', 'ACTIVE' => true]);
        $employee = array_find($employees, static fn (array $row): bool => filter_var($row['EMAIL'] ?? '', FILTER_VALIDATE_EMAIL) !== false);
        if ($employee === null) {
            self::markTestSkipped('No visible active employee email; the webhook needs an appropriate user scope.');
        }

        $result = $this->invoke(['users:find', '--email', $employee['EMAIL'], '--all']);
        self::assertContains((string) $employee['ID'], array_column($result['data']['items'], 'ID'));
        $expected = $this->userRows(['USER_TYPE' => 'employee', 'ACTIVE' => true, 'EMAIL' => $employee['EMAIL']]);
        self::assertEqualsCanonicalizing(array_map(strval(...), array_column($expected, 'ID')), array_column($result['data']['items'], 'ID'));
    }
}
