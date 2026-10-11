<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:checklist:item:complete')]
final class CompleteTaskChecklistItemCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathCompletesItemWithoutChangingTaskStatus(): void
    {
        $id = $this->createTask();
        $status = $this->card($id)['status'];
        $root = $this->checklists->add($id, 0, 'Root');
        $item = $this->checklists->add($id, $root, 'Item');
        $this->invoke(['task:checklist:item:complete', $id, '--item', $item]);
        self::assertTrue($this->checklistNodes($id)[$item]['isComplete']);
        self::assertSame($status, $this->card($id)['status']);
    }
}
