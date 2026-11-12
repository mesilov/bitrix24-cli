<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:time:add')]
final class AddTaskTimeEntryCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathCreatesOwnTimeEntry(): void
    {
        $id = $this->createTask();
        $result = $this->invoke(['task:time:add', $id, '--seconds', 60, '--text', 'fixture']);
        $entry = $result['data']['operation']['resourceId'];
        self::assertIsInt($entry);
        self::assertGreaterThan(0, $entry);
        self::assertTrue($result['data']['operation']['confirmed']);
        $row = $this->timeEntries($id)[$entry];
        self::assertSame(60, $row['seconds']);
        self::assertSame('fixture', $row['text']);
        self::assertSame($this->userId, $row['userId']);
    }
    #[Group('happy-path')]
    public function testHappyPathBackdatesTimeEntry(): void
    {
        $id = $this->createTask();
        $result = $this->invoke(['task:time:add', $id, '--seconds', 600, '--created-date', '2026-10-22T10:00:00+03:00']);
        $entry = $result['data']['operation']['resourceId'];
        self::assertSame(['seconds', 'createdDate'], $result['data']['operation']['changedFields']);
        $row = $this->timeEntries($id)[$entry];
        self::assertSame(600, $row['seconds']);
        self::assertSame((new \DateTimeImmutable('2026-10-22T10:00:00+03:00'))->getTimestamp(), (new \DateTimeImmutable($row['createdDate']))->getTimestamp());
    }

    #[Group('portal-acceptance')]
    public function testSecondUserWithoutTaskRoleCannotAccessOwnedFixture(): void
    {
        [$second] = $this->secondUser();
        $id = $this->createTask();

        $before = $this->timeEntries($id);
        $this->assertSecondUserDenied(['task:time:add', $id, '--seconds', 60]);
        self::assertSame($before, $this->timeEntries($id));
    }

}
