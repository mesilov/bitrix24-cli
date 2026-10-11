<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:checklist:add')]
final class AddTaskChecklistCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathCreatesChecklistRoot(): void
    {
        $id = $this->createTask();
        $result = $this->invoke(['task:checklist:add', $id, '--title', 'Root']);
        $root = $result['data']['operation']['resourceId'];
        self::assertGreaterThan(0, $root);
        $node = $this->checklistNodes($id)[$root];
        self::assertSame('Root', $node['title']);
        self::assertSame(0, $node['parentId']);
        self::assertFalse($node['isComplete']);
    }
}
