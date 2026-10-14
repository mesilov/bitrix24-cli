<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:time:update')]
final class UpdateTaskTimeEntryCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathUpdatesSecondsAndKeepsOmittedText(): void
    {
        $id = $this->createTask();
        $entry = $this->entries->add($id, 60, 'fixture');
        $this->invoke(['task:time:update', $id, '--entry', $entry, '--seconds', 120]);
        $row = $this->timeEntries($id)[$entry];
        self::assertSame(120, $row['seconds']);
        self::assertSame('fixture', $row['text']);
    }

    public function testEntryFromOtherTaskIsRejected(): void
    {
        $id = $this->createTask();
        $entry = $this->entries->add($id, 60, 'fixture');
        $other = $this->createTask();
        $result = $this->invoke(['task:time:update', $other, '--entry', $entry, '--seconds', 120], [4]);
        self::assertSame('resource-binding-unverified', $result['error']['code']);
        self::assertSame(60, $this->timeEntries($id)[$entry]['seconds']);
    }
    #[Group('portal-acceptance')]
    public function testSecondUserWithoutTaskRoleCannotAccessOwnedFixture(): void
    {
        [$second] = $this->secondUser();
        $id = $this->createTask();
        $entry = $this->entries->add($id, 60, 'owned');
        $before = $this->timeEntries($id);
        $this->assertSecondUserDenied(['task:time:update', $id, '--entry', $entry, '--seconds', 120]);
        self::assertSame($before, $this->timeEntries($id));
    }

}
