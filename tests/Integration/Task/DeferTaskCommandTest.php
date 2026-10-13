<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Application\Task\LifecycleAction;
use Bitrix24\CLI\Tests\Support\LiveTaskLifecycleTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:defer')]
#[Group('lifecycle')]
final class DeferTaskCommandTest extends LiveTaskLifecycleTestCase
{
    #[Group('happy-path')]
    public function testHappyPathChangesTaskState(): void
    {
        $id = $this->createTask();
        $result = $this->invoke(['task:defer', $id]);
        $this->assertActualState($id, $result, 'deferred');
    }
    public function testObserverCannotPerformAction(): void
    {
        $this->assertObserverCannotTransition(LifecycleAction::Defer);
    }
}
