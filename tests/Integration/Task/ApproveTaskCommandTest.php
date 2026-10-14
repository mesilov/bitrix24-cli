<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Application\Task\LifecycleAction;
use Bitrix24\CLI\Tests\Support\LiveTaskLifecycleTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:approve')]
#[Group('lifecycle')]
final class ApproveTaskCommandTest extends LiveTaskLifecycleTestCase
{
    #[Group('happy-path')]
    public function testHappyPathChangesTaskState(): void
    {
        $id = $this->awaitingReview();
        $result = $this->invoke(['task:approve', $id]);
        $this->assertActualState($id, $result, 'completed');
    }

    public function testWrongStateDoesNotApprove(): void
    {
        $id = $this->createTask();
        $this->invoke(['task:approve', $id], [1, 4]);
        self::assertSame('pending', $this->card($id, ['status'])['status']);
    }
    public function testObserverCannotPerformAction(): void
    {
        $this->assertObserverCannotTransition(LifecycleAction::Approve);
    }
}
