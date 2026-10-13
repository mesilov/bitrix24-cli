<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Application\Task\LifecycleAction;
use Bitrix24\CLI\Tests\Support\LiveTaskLifecycleTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:complete')]
#[Group('lifecycle')]
final class CompleteTaskCommandTest extends LiveTaskLifecycleTestCase
{
    #[Group('happy-path')]
    public function testHappyPathChangesTaskState(): void
    {
        $id = $this->createTask();
        $result = $this->invoke(['task:complete', $id]);
        $this->assertActualState($id, $result, 'completed');
    }

    public function testRequiredResultCannotBeInventedAndExistingResultIsPreserved(): void
    {
        $id = $this->createTask(fields: ['requireResult' => true]);
        $denied = $this->invoke(['task:complete', $id], [4]);
        self::assertSame('required-result-unverified', $denied['error']['code']);
        self::assertSame('pending', $this->card($id, ['status'])['status']);
        $this->nativeResult($id);
        $this->assertActualState($id, $this->invoke(['task:complete', $id]), 'completed');
        $this->transition($id, LifecycleAction::Renew);
        $this->assertActualState($id, $this->invoke(['task:complete', $id]), 'completed');
    }

    public function testExecutorCompletionAwaitsReview(): void
    {
        [$id] = $this->controlledTask();
        $result = $this->invoke(['task:complete', $id], app: $this->completingApp());
        $this->assertActualState($id, $result, 'supposedly_completed');
    }

    public function testDryRunAndCreatorCompletionReportActualState(): void
    {
        $id = $this->createTask(fields: ['needsControl' => true]);
        $before = $this->card($id, ['status']);
        $preview = $this->invoke(['task:complete', $id, '--dry-run']);
        self::assertSame($before['status'], $this->card($id, ['status'])['status']);
        self::assertTrue($preview['meta']['dryRun']);
        self::assertTrue($preview['data']['plan']['steps'][0]['fields']['needsControl']);
        self::assertNotContains('write', array_column($preview['meta']['apiCalls'], 'effect'));
        $result = $this->invoke(['task:complete', $id]);
        self::assertSame($this->card($id, ['status'])['status'], $result['data']['task']['status']);
    }

    public function testCompletionMayCompleteOwnedSubtasks(): void
    {
        $parent = $this->createTask(fields: ['autocompleteSubTasks' => true, 'allowsTimeTracking' => true]);
        $child = $this->createTask(fields: ['parentId' => $parent]);
        $this->assertActualState($parent, $this->invoke(['task:complete', $parent]), 'completed');
        self::assertSame('completed', $this->card($child, ['status'])['status']);
    }
    public function testObserverCannotPerformAction(): void
    {
        $this->assertObserverCannotTransition(LifecycleAction::Complete);
    }
}
