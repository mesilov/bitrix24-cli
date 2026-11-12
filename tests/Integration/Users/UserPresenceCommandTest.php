<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Users;

use Bitrix24\CLI\Tests\Support\LiveUsersCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('users')]
#[Group('command:users:presence')]
final class UserPresenceCommandTest extends LiveUsersCommandTestCase
{
    private const UNKNOWN_ID = 2147480000;

    #[Group('happy-path')]
    public function testHappyPathReturnsLastActivityWithoutOnlineFlags(): void
    {
        $result = $this->invoke(['users:presence', '--user-id', (string) $this->userId, '--user-id', (string) self::UNKNOWN_ID]);
        self::assertSame([$this->userId, self::UNKNOWN_ID], array_column($result['data']['items'], 'id'));
        self::assertTrue($result['data']['items'][0]['found']);
        self::assertNotNull($result['data']['items'][0]['lastActivityDate']);
        self::assertFalse($result['data']['items'][1]['found']);
        self::assertSame([self::UNKNOWN_ID], $result['meta']['notFound']);
        foreach ($result['data']['items'] as $item) {
            self::assertArrayNotHasKey('status', $item);
            self::assertArrayNotHasKey('IS_ONLINE', $item);
        }
    }
}
