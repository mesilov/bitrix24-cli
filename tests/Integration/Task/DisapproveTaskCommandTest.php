<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Application\Task\LifecycleAction;
use Bitrix24\CLI\Tests\Support\LiveTaskLifecycleTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:disapprove')]
#[Group('lifecycle')]
final class DisapproveTaskCommandTest extends LiveTaskLifecycleTestCase
{
    #[Group('happy-path')]
    public function testHappyPathChangesTaskState(): void
    {
        $id = $this->awaitingReview();
        $result = $this->invoke(['task:disapprove', $id]);
        $this->assertActualState($id, $result, 'pending');
    }

    public function testWrongStateDoesNotDisapprove(): void
    {
        $id = $this->createTask();
        $this->invoke(['task:disapprove', $id], [1, 4]);
        self::assertSame('pending', $this->card($id, ['status'])['status']);
    }
    public function testObserverCannotPerformAction(): void
    {
        $this->assertObserverCannotTransition(LifecycleAction::Disapprove);
    }
}
