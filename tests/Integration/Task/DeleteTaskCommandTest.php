<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:delete')]
final class DeleteTaskCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathDeletesTask(): void
    {
        $id = $this->createTask();
        $result = $this->invoke(['task:delete', $id, '--force']);
        $this->forgetDeletedTask($id);
        self::assertTrue($result['data']['operation']['confirmed']);
        self::assertSame($id, $result['data']['operation']['taskId']);
        self::assertSame([], $this->tasks->page([['id', 'in', [$id]]], ['id', 'title'], ['id' => 'ASC'], 0, 50)->items);
    }
}
