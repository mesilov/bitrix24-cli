<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:checklist:item:add')]
final class AddTaskChecklistItemCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathAddsItemToSelectedChecklist(): void
    {
        $id = $this->createTask();
        $root = $this->checklists->add($id, 0, 'Root');
        $result = $this->invoke(['task:checklist:item:add', $id, '--checklist', $root, '--title', 'Item']);
        $item = $result['data']['operation']['resourceId'];
        $node = $this->checklistNodes($id)[$item];
        self::assertSame('Item', $node['title']);
        self::assertSame($root, $node['parentId']);
        self::assertFalse($node['isComplete']);
    }

    public function testNestedItemBelongsToChosenParent(): void
    {
        $id = $this->createTask();
        $root = $this->checklists->add($id, 0, 'Root');
        $parent = $this->checklists->add($id, $root, 'Parent');
        $result = $this->invoke(['task:checklist:item:add', $id, '--checklist', $root, '--parent', $parent, '--title', 'Nested']);
        $item = $result['data']['operation']['resourceId'];
        $node = $this->checklistNodes($id)[$item];
        self::assertSame('Nested', $node['title']);
        self::assertSame($parent, $node['parentId']);
    }

    public function testParentFromOtherChecklistIsRejected(): void
    {
        $id = $this->createTask();
        $root = $this->checklists->add($id, 0, 'Root');
        $parent = $this->checklists->add($id, $root, 'Parent');
        $other = $this->checklists->add($id, 0, 'Other root');
        $result = $this->invoke(['task:checklist:item:add', $id, '--checklist', $other, '--parent', $parent, '--title', 'Wrong parent'], [4]);
        self::assertSame('resource-binding-unverified', $result['error']['code']);
        self::assertNotContains('Wrong parent', array_column($this->checklistNodes($id), 'title'));
    }
}
