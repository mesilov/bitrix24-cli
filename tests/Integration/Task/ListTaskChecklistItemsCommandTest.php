<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:checklist:item:list')]
final class ListTaskChecklistItemsCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathListsItemsInsideSelectedChecklist(): void
    {
        $id = $this->createTask();
        $root = $this->checklists->add($id, 0, 'Root');
        $item = $this->checklists->add($id, $root, 'Item');
        $nested = $this->checklists->add($id, $item, 'Nested');
        $other = $this->checklists->add($id, 0, 'Other root');
        $this->checklists->add($id, $other, 'Foreign');
        $items = $this->invoke(['task:checklist:item:list', $id, '--checklist', $root])['data']['items'];
        self::assertSame([$item, $nested], array_column($items, 'id'));
        self::assertSame(['Item', 'Nested'], array_column($items, 'title'));
        self::assertSame([$root, $item], array_column($items, 'parentId'));
        self::assertSame([false, false], array_column($items, 'isComplete'));
    }
}
