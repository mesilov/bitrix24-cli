<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Application\Task\LifecycleAction;
use Bitrix24\CLI\Tests\Support\LiveTaskLifecycleTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:pause')]
#[Group('lifecycle')]
final class PauseTaskCommandTest extends LiveTaskLifecycleTestCase
{
    #[Group('happy-path')]
    public function testHappyPathChangesTaskState(): void
    {
        $id = $this->createTask();
        $this->transition($id, LifecycleAction::Start);
        $result = $this->invoke(['task:pause', $id]);
        $this->assertActualState($id, $result, 'pending');
    }

    public function testPausePreservesExistingTrackedTime(): void
    {
        $id = $this->createTask(fields: ['allowsTimeTracking' => true]);
        $this->entries->add($id, 60, 'Disposable recorded time');
        $before = $this->timeEntries($id);
        $this->transition($id, LifecycleAction::Start);
        $this->assertActualState($id, $this->invoke(['task:pause', $id]), 'pending');
        $after = $this->timeEntries($id);
        foreach ($before as $entryId => $entry) {
            self::assertArrayHasKey($entryId, $after);
            self::assertSame($entry, $after[$entryId]);
        }

        self::assertGreaterThanOrEqual(array_sum(array_column($before, 'seconds')), array_sum(array_column($after, 'seconds')));
    }
    public function testObserverCannotPerformAction(): void
    {
        $this->assertObserverCannotTransition(LifecycleAction::Pause);
    }
}
