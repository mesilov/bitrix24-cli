<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:assign')]
final class AssignTaskCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathSetsResponsibleUser(): void
    {
        $id = $this->createTask();
        $result = $this->invoke(['task:assign', $id, '--responsible', $this->userId]);
        self::assertTrue($result['data']['operation']['confirmed']);
        self::assertSame($this->userId, $this->card($id)['responsibleId']);
    }
}
