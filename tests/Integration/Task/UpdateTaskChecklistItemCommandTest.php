<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:checklist:item:update')]
final class UpdateTaskChecklistItemCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathChangesItemTitle(): void
    {
        $id = $this->createTask();
        $root = $this->checklists->add($id, 0, 'Root');
        $item = $this->checklists->add($id, $root, 'Item');
        $this->invoke(['task:checklist:item:update', $id, '--item', $item, '--title', 'Changed']);
        $node = $this->checklistNodes($id)[$item];
        self::assertSame('Changed', $node['title']);
        self::assertSame($root, $node['parentId']);
    }
}
