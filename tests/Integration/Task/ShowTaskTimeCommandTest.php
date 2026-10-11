<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:time:show')]
final class ShowTaskTimeCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathReturnsTimeContextWithItsCompletenessBoundary(): void
    {
        $id = $this->createTask();
        $this->entries->add($id, 120, 'fixture');
        $result = $this->invoke(['task:time:show', $id], [0, 3]);
        $source = $this->api->call('tasks.task.get', 3, ['id' => $id, 'select' => ['id', 'elapsedTime']])->result['item'];
        self::assertSame(['taskId' => $id, 'elapsedTime' => $source['elapsedTime'] ?? null], $result['data']['time']);
        self::assertSame('task-elapsedTime-relation', $result['meta']['scope']);
        self::assertNull($result['meta']['historyComplete']);
        self::assertSame(array_key_exists('elapsedTime', $source), $result['meta']['complete']);
        if (!$result['meta']['complete']) {
            self::assertSame('Selected elapsedTime was not returned.', $result['meta']['reason']);
            self::assertSame('partial-result', $result['error']['code']);
        }
    }
}
