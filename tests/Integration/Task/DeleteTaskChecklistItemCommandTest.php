<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:checklist:item:delete')]
final class DeleteTaskChecklistItemCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathDeletesItemAndItsSubtree(): void
    {
        $id = $this->createTask();
        $root = $this->checklists->add($id, 0, 'Root');
        $item = $this->checklists->add($id, $root, 'Item');
        $nested = $this->checklists->add($id, $item, 'Nested');
        $this->invoke(['task:checklist:item:delete', $id, '--item', $item, '--force']);
        $nodes = $this->checklistNodes($id);
        self::assertArrayNotHasKey($item, $nodes);
        self::assertArrayNotHasKey($nested, $nodes);
        self::assertSame('Root', $nodes[$root]['title']);
    }
    #[Group('portal-acceptance')]
    public function testSecondUserWithoutTaskRoleCannotAccessOwnedFixture(): void
    {
        [$second] = $this->secondUser();
        $id = $this->createTask();
        $root = $this->checklists->add($id, 0, 'Root');
        $item = $this->checklists->add($id, $root, 'Item');
        $before = $this->checklistNodes($id);
        $this->assertSecondUserDenied(['task:checklist:item:delete', $id, '--item', $item, '--force']);
        self::assertSame($before, $this->checklistNodes($id));
    }

}
