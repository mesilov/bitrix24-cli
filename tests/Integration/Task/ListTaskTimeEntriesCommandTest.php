<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:time:list')]
final class ListTaskTimeEntriesCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathListsOwnTimeEntry(): void
    {
        $id = $this->createTask();
        $entry = $this->entries->add($id, 60, 'fixture');
        $items = $this->invoke(['task:time:list', $id])['data']['items'];
        self::assertCount(1, $items);
        self::assertSame($entry, $items[0]['id']);
        self::assertSame(60, $items[0]['seconds']);
        self::assertSame('fixture', $items[0]['text']);
        self::assertSame($this->userId, $items[0]['userId']);
    }
}
