<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:checklist:item:renew')]
final class RenewTaskChecklistItemCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathRenewsItemWithoutChangingTaskStatus(): void
    {
        $id = $this->createTask();
        $status = $this->card($id)['status'];
        $root = $this->checklists->add($id, 0, 'Root');
        $item = $this->checklists->add($id, $root, 'Item');
        $this->checklists->complete($id, $item);
        $this->invoke(['task:checklist:item:renew', $id, '--item', $item]);
        self::assertFalse($this->checklistNodes($id)[$item]['isComplete']);
        self::assertSame($status, $this->card($id)['status']);
    }
    #[Group('portal-acceptance')]
    public function testSecondUserWithoutTaskRoleCannotAccessOwnedFixture(): void
    {
        [$second] = $this->secondUser();
        $id = $this->createTask();
        $root = $this->checklists->add($id, 0, 'Root');
        $item = $this->checklists->add($id, $root, 'Item');
        $this->checklists->complete($id, $item);
        $before = $this->checklistNodes($id);
        $this->assertSecondUserDenied(['task:checklist:item:renew', $id, '--item', $item]);
        self::assertSame($before, $this->checklistNodes($id));
    }

}
