<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:add')]
final class AddTaskCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathCreatesTaskWithRequestedFields(): void
    {
        $title = $this->fixtureTitle();
        $result = $this->invoke(['task:add', '--title', $title, '--creator', $this->userId, '--responsible', $this->userId, '--description', 'original']);
        $id = $result['data']['operation']['resourceId'];
        self::assertIsInt($id);
        self::assertGreaterThan(0, $id);
        self::assertTrue($result['data']['operation']['confirmed']);
        $card = $this->card($id);
        self::assertSame($id, $card['id']);
        self::assertSame($title, $card['title']);
        self::assertSame('original', $card['description']);
        self::assertSame($this->userId, $card['creatorId']);
        self::assertSame($this->userId, $card['responsibleId']);
    }
    #[Group('happy-path')]
    public function testHappyPathAddsConfiguredObserverWithIndependentMembershipReadback(): void
    {
        $this->withProfile(['observerId' => $this->userId, 'autoAddObserver' => true], function (string $directory): void {
            $result = $this->invoke(['task:add', '--title', $this->fixtureTitle(), '--profile', 'fixture', '--config-dir', $directory]);
            $id = $result['data']['operation']['taskId'];
            self::assertSame($this->userId, $result['meta']['observerId']);
            self::assertSame('confirmed', $result['meta']['ledger'][0]['status']);
            $readback = $this->api->call('tasks.task.get', 1, ['taskId' => $id, 'select' => ['ID', 'AUDITORS']])->result['task'];
            self::assertSame($id, (int) $readback['id']);
            self::assertContains($this->userId, array_map(intval(...), $readback['auditors']));
        });
    }

}
