<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:access:show')]
final class ShowTaskAccessCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathReturnsCurrentConnectionPermissions(): void
    {
        $id = $this->createTask();
        $access = $this->invoke(['task:access:show', $id])['data']['access'];
        self::assertNotEmpty($access);
        self::assertSame($this->tasks->access($id), $access);
        self::assertContains(true, $access, 'The acting user must have access to its own editable task.');
    }
}
