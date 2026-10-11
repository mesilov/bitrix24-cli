<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:deadline:set')]
final class SetTaskDeadlineCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathSetsDeadlineWithOffset(): void
    {
        $id = $this->createTask();
        $at = '2027-01-10T12:00:00+06:00';
        $result = $this->invoke(['task:deadline:set', $id, '--at', $at]);
        self::assertTrue($result['data']['operation']['confirmed']);
        self::assertSame(strtotime($at), strtotime($this->card($id)['deadline']));
    }
}
