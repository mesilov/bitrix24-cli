<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use Bitrix24\CLI\Tests\Support\TaskResultFixtures;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:result:delete')]
final class DeleteTaskResultCommandTest extends LiveTaskCommandTestCase
{
    use TaskResultFixtures;

    #[Group('happy-path')]
    public function testHappyPathDeletesOwnResult(): void
    {
        $taskId = $this->createTask();
        $id = $this->createResult($taskId, $this->fixtureTitle());
        $this->invoke(['task:result:delete', $taskId, '--result', $id, '--force', '--api-policy', 'strict-rest3']);
        self::assertArrayNotHasKey($id, $this->taskResults($taskId));
    }

    public function testForceCannotDeleteResultInAnotherTask(): void
    {
        $taskId = $this->createTask();
        $id = $this->createResult($taskId, $this->fixtureTitle());
        $result = $this->invoke(['task:result:delete', $this->createTask(), '--result', $id, '--force'], [4]);
        self::assertSame('resource-binding-unverified', $result['error']['code']);
        self::assertArrayHasKey($id, $this->taskResults($taskId));
    }
}
