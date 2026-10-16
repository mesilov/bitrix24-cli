<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Application\Task\LifecycleAction;
use Bitrix24\CLI\Tests\Support\LiveTaskLifecycleTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:report')]
final class ReportTasksCommandTest extends LiveTaskLifecycleTestCase
{
    #[Group('happy-path')]
    public function testHappyPathReportsProjectAndResponsibleSubset(): void
    {
        $group = $this->setting('B24CLI_TEST_GROUP_ID');
        if ($group === '') {
            self::markTestSkipped('Weekly report acceptance needs B24CLI_TEST_GROUP_ID.');
        }

        $id = $this->createTask(fields: ['groupId' => (int) $group]);
        $this->transition($id, LifecycleAction::Start);
        $project = $this->invoke(['task:report', '--group-id', $group, '--week', 'this-week', '--timezone', 'UTC']);
        self::assertNull($project['meta']['scope']['responsibleId']);
        self::assertGreaterThanOrEqual(1, $project['data']['newTasks']['count']);
        self::assertGreaterThanOrEqual(1, $project['data']['inProgressTasks']['count']);
        $this->transition($id, LifecycleAction::Complete);
        self::assertSame('completed', $this->card($id, ['status'])['status']);
        $subset = $this->invoke(['task:report', '--group-id', $group, '--responsible', $this->userId, '--week', 'this-week', '--timezone', 'UTC']);
        self::assertContains($id, array_column($subset['data']['closedTasks']['items'], 'id'));
        self::assertTrue($subset['meta']['complete']);
        self::assertSame($this->userId, $subset['meta']['scope']['responsibleId']);
        self::assertNotContains('write', array_column($subset['meta']['apiCalls'], 'effect'));
    }
}
