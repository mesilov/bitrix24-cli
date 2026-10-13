<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use Bitrix24\CLI\Tests\Support\TaskResultFixtures;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:result:add')]
final class AddTaskResultCommandTest extends LiveTaskCommandTestCase
{
    use TaskResultFixtures;

    #[Group('happy-path')]
    public function testHappyPathCreatesNativeResultWithoutCompletingTask(): void
    {
        $taskId = $this->createTask();
        $status = $this->card($taskId, ['status'])['status'];
        $text = $this->fixtureTitle();
        $result = $this->invoke(['task:result:add', $taskId, '--text', $text, '--api-policy', 'strict-rest3']);
        $id = $result['data']['operation']['resourceId'];
        self::assertIsInt($id);
        self::assertGreaterThan(0, $id);
        $row = $this->taskResults($taskId)[$id];
        self::assertSame($text, $row['text']);
        self::assertSame($taskId, $row['taskId']);
        self::assertSame($this->userId, $row['authorId']);
        self::assertSame($status, $this->card($taskId, ['status'])['status']);
    }
}
