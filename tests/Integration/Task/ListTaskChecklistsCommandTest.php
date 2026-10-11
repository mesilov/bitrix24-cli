<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:checklist:list')]
final class ListTaskChecklistsCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathListsOnlyChecklistRoots(): void
    {
        $id = $this->createTask();
        $root = $this->checklists->add($id, 0, 'Root');
        $other = $this->checklists->add($id, 0, 'Other root');
        $this->checklists->add($id, $root, 'Item');
        $items = $this->invoke(['task:checklist:list', $id])['data']['items'];
        self::assertCount(2, $items);
        $byId = array_column($items, null, 'id');
        self::assertSame('Root', $byId[$root]['title']);
        self::assertSame('Other root', $byId[$other]['title']);
    }
}
