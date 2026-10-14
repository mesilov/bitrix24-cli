<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:fields:list')]
final class ListTaskFieldsCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathListsFieldMetadata(): void
    {
        $result = $this->invoke(['task:fields:list']);
        self::assertNotEmpty($result['data']['items']);
        self::assertContains('title', array_column($result['data']['items'], 'name'));
    }

    public function testSingleFieldSelectionReturnsTitle(): void
    {
        $result = $this->invoke(['task:fields:list', '--name', 'title']);
        self::assertCount(1, $result['data']['items']);
        self::assertSame('title', $result['data']['items'][0]['name']);
    }
    #[Group('portal-acceptance')]
    public function testSecondUserReadsSameTitleSchema(): void
    {
        $owner = $this->invoke(['task:fields:list', '--name', 'title']);
        $second = $this->invokeAsSecond(['task:fields:list', '--name', 'title']);
        self::assertSame($owner['data'], $second['data']);
        self::assertSame(['3.0'], $second['meta']['apiVersions']);
    }

}
