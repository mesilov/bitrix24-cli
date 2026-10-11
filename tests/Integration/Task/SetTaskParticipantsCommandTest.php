<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:participants:set')]
final class SetTaskParticipantsCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathSetsSelectedParticipantRoles(): void
    {
        $id = $this->createTask();
        $this->invoke(['task:participants:set', $id, '--auditor', $this->userId, '--accomplice', $this->userId]);
        self::assertSame(['auditors' => [$this->userId], 'accomplices' => [$this->userId]], $this->participants->ids($id, ['auditors', 'accomplices']));
    }

    public function testOmittedRoleStaysUntouchedWhenClearingAccomplices(): void
    {
        $id = $this->createTask();
        $this->participants->set($id, [$this->userId], [$this->userId]);
        $this->invoke(['task:participants:set', $id, '--clear-accomplices']);
        self::assertSame(['auditors' => [$this->userId], 'accomplices' => []], $this->participants->ids($id, ['auditors', 'accomplices']));
    }

    public function testExplicitClearRemovesAuditorsAndKeepsAccomplices(): void
    {
        $id = $this->createTask();
        $this->participants->set($id, [$this->userId], [$this->userId]);
        $this->invoke(['task:participants:set', $id, '--clear-auditors']);
        self::assertSame(['auditors' => [], 'accomplices' => [$this->userId]], $this->participants->ids($id, ['auditors', 'accomplices']));
    }
}
