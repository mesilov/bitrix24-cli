<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:time:delete')]
final class DeleteTaskTimeEntryCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathDeletesOwnTimeEntry(): void
    {
        $id = $this->createTask();
        $entry = $this->entries->add($id, 60, 'fixture');
        $this->invoke(['task:time:delete', $id, '--entry', $entry, '--force']);
        self::assertArrayNotHasKey($entry, $this->timeEntries($id));
    }
    #[Group('portal-acceptance')]
    public function testSecondUserWithoutTaskRoleCannotAccessOwnedFixture(): void
    {
        [$second] = $this->secondUser();
        $id = $this->createTask();
        $entry = $this->entries->add($id, 60, 'owned');
        $before = $this->timeEntries($id);
        $this->assertSecondUserDenied(['task:time:delete', $id, '--entry', $entry, '--force']);
        self::assertSame($before, $this->timeEntries($id));
    }

}
