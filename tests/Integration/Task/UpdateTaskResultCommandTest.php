<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use Bitrix24\CLI\Tests\Support\TaskResultFixtures;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:result:update')]
final class UpdateTaskResultCommandTest extends LiveTaskCommandTestCase
{
    use TaskResultFixtures;

    #[Group('happy-path')]
    public function testHappyPathChangesOwnResultText(): void
    {
        $taskId = $this->createTask();
        $text = $this->fixtureTitle();
        $id = $this->createResult($taskId, $text);
        $this->invoke(['task:result:update', $taskId, '--result', $id, '--text', $text . '-fixed', '--api-policy', 'strict-rest3']);
        self::assertSame($text . '-fixed', $this->taskResults($taskId)[$id]['text']);
    }

    public function testForeignResultIsRejectedWithoutChangingIt(): void
    {
        $taskId = $this->createTask();
        $text = $this->fixtureTitle();
        $id = $this->createResult($taskId, $text);
        $result = $this->invoke(['task:result:update', $this->createTask(), '--result', $id, '--text', 'foreign'], [4]);
        self::assertSame('resource-binding-unverified', $result['error']['code']);
        self::assertSame($text, $this->taskResults($taskId)[$id]['text']);
    }
}
