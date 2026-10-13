<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Users;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Tests\Support\LiveUsersCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('users')]
#[Group('command:users:manager')]
final class GetManagerCommandTest extends LiveUsersCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathMatchesOrganizationManagerChain(): void
    {
        $department = $this->currentDepartment();
        $id = $department;
        $visited = [];
        $head = 0;
        while ($id !== 0) {
            if (isset($visited[$id]) || count($visited) >= 100) {
                self::markTestSkipped('Organization fixture has a cycle or exceeds the bounded hierarchy budget.');
            }

            $visited[$id] = true;
            try {
                $rows = $this->api->call('department.get', 1, ['ID' => $id])->result;
            } catch (Failure $failure) {
                if ($failure->errorCode === 'permission-denied') {
                    self::markTestSkipped('The configured webhook lacks department scope for the org manager scenario.');
                }

                throw $failure;
            }

            $row = $rows[0] ?? null;
            if ($row === null) {
                self::markTestSkipped('The current department is not visible for SDK org fixture readback.');
            }

            $candidate = (int) ($row['UF_HEAD'] ?? 0);
            if ($candidate > 0 && $candidate !== $this->userId) {
                $head = $candidate;
                break;
            }

            if (!array_key_exists('PARENT', $row)) {
                self::markTestSkipped('The organization fixture omitted parent data.');
            }

            $id = (int) $row['PARENT'];
        }

        $result = $this->invoke(['users:manager', '--department-id', $department]);
        self::assertSame($head === 0 ? null : (string) $head, $result['data']['user']['ID'] ?? null);
        self::assertSame('org', $result['meta']['source']);
        if ($head !== 0) {
            self::assertNotEmpty($this->userRows(['ID' => $head]));
        }
    }

    #[Group('happy-path')]
    public function testHappyPathUsesExplicitConfiguredManager(): void
    {
        $this->withProfile(['managerId' => $this->userId], function (string $directory): void {
            $result = $this->invoke(['users:manager', '--source', 'config', '--profile', 'fixture', '--config-dir', $directory]);
            self::assertSame((string) $this->userId, $result['data']['user']['ID']);
            self::assertSame('config', $result['meta']['source']);
            self::assertSame(['user.get'], array_column($result['meta']['apiCalls'], 'method'));
        });
    }
}
