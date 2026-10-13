<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use Bitrix24\CLI\Tests\Support\TaskResultFixtures;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:result:list')]
final class ListTaskResultsCommandTest extends LiveTaskCommandTestCase
{
    use TaskResultFixtures;

    #[Group('happy-path')]
    public function testHappyPathReturnsOnlySelectedTaskResults(): void
    {
        $taskId = $this->createTask();
        $text = $this->fixtureTitle();
        $id = $this->createResult($taskId, $text);
        $otherTaskId = $this->createTask();
        $otherId = $this->createResult($otherTaskId, $text . '-foreign');
        $result = $this->invoke(['task:result:list', $taskId, '--all', '--api-policy', 'strict-rest3']);
        $rows = array_column($result['data']['items'], null, 'id');
        self::assertArrayHasKey($id, $rows);
        self::assertArrayNotHasKey($otherId, $rows);
        self::assertSame($text, $rows[$id]['text']);
        self::assertSame($taskId, $rows[$id]['taskId']);
        self::assertSame($this->taskResults($taskId), $rows);
    }

    public function testEmptyTaskReturnsAnEmptyCompleteList(): void
    {
        $result = $this->invoke(['task:result:list', $this->createTask()]);
        self::assertSame([], $result['data']['items']);
    }
}
