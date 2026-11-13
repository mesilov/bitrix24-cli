<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Auth;

use Bitrix24\CLI\Application\Connection\ScopeManifest;
use Bitrix24\CLI\Tests\Support\LiveCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('auth')]
#[Group('command:auth:status')]
final class StatusCommandTest extends LiveCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathReadsWebhookOwnerAndActualPermissions(): void
    {
        $granted = $this->api->call('scope', 1, [])->result;
        if ((new ScopeManifest())->affectedCommands($granted) !== []) {
            self::markTestSkipped('Full compatibility happy path needs the scopes of every supported capability. Restricted combinations are covered with controlled SDK fixtures.');
        }

        $this->withProfile([], function (string $directory) use ($granted): void {
            $result = $this->invoke(['auth:status', '--profile', 'fixture', '--config-dir', $directory]);
            $expected = $granted;
            sort($expected);
            self::assertSame($expected, $result['data']['grantedScopes']);
            self::assertSame('fresh', $result['data']['status']);
            self::assertFileExists($directory . '/state.json');
            self::assertSame((string) $this->userId, (string) $result['data']['owner']['ID']);
            self::assertSame(['ID', 'NAME', 'LAST_NAME', 'ADMIN'], array_keys($result['data']['owner']));
            self::assertSame(['profile', 'scope'], array_column($result['meta']['apiCalls'], 'method'));
        });
    }
}
