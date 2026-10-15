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
    #[Group('portal-acceptance')]
    public function testAssignmentIsReadBackByTheSecondResponsibleUser(): void
    {
        [$second, $api] = $this->secondUser();
        $id = $this->createTask();
        $result = $this->invoke(['task:assign', $id, '--responsible', $second]);
        self::assertTrue($result['data']['operation']['confirmed']);
        self::assertSame($second, $this->card($id)['responsibleId']);
        $independent = (new \Bitrix24\CLI\Infrastructure\Bitrix24\Rest3TaskAdapter($api))->get($id, ['responsible.id']);
        self::assertSame($second, $independent['responsibleId']);
        $this->acceptanceRecords[] = ['case' => 'second-user-assignment', 'taskId' => $id, 'ownerId' => $this->userId, 'actingUserId' => $second, 'expected' => $second, 'observed' => $independent['responsibleId'], 'readbackApiVersion' => '3.0'];
    }

}
