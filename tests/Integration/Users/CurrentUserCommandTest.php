<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Users;

use Bitrix24\CLI\Tests\Support\LiveUsersCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('users')]
#[Group('command:users:current')]
final class CurrentUserCommandTest extends LiveUsersCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathReturnsActualConnectionIdentity(): void
    {
        $expected = $this->currentUser();
        $result = $this->invoke(['users:current']);
        self::assertSame((string) $expected['ID'], $result['data']['user']['ID']);
        self::assertSame((string) $this->userId, $result['data']['user']['ID']);
    }
}
