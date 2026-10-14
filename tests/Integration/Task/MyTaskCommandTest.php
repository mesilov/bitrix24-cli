<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:my')]
final class MyTaskCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathListsExecutorFixture(): void
    {
        $id = $this->createTask();
        $card = $this->card($id, ['title', 'responsible.id', 'status']);
        self::assertSame($this->userId, $card['responsible']['id']);
        $result = $this->invoke(['task:my', '--user', $this->userId, '--api-policy', 'strict-rest3']);
        $items = array_column($result['data']['items'], null, 'id');
        self::assertArrayHasKey($id, $items);
        self::assertSame($card['title'], $items[$id]['title']);
        self::assertSame($card['status'], $items[$id]['status']);
        self::assertSame($this->userId, $items[$id]['responsibleId']);
        self::assertSame(['3.0'], $result['meta']['apiVersions']);
        self::assertTrue($result['meta']['complete']);
    }

    public function testOpenAndDeadlineExcludeTerminalBoundaryAndNullFixtures(): void
    {
        $before = $this->createTask();
        $boundary = $this->createTask();
        $after = $this->createTask();
        $noDeadline = $this->createTask();
        $completed = $this->createTask();
        $due = '2026-10-20T00:00:00+00:00';
        foreach ([$before => '2026-10-19T23:59:59+00:00', $boundary => $due, $after => '2026-10-20T00:00:01+00:00', $completed => '2026-10-19T23:59:59+00:00'] as $id => $deadline) {
            $this->tasks->update($id, ['deadline' => $deadline]);
            self::assertSame(strtotime($deadline), strtotime($this->card($id, ['deadline'])['deadline']));
        }

        $this->api->call('tasks.task.update', 1, ['taskId' => $noDeadline, 'fields' => ['DEADLINE' => '']], 'write');
        self::assertNull($this->card($noDeadline, ['deadline'])['deadline']);
        $this->api->call('tasks.task.complete', 1, ['taskId' => $completed], 'write');
        self::assertSame('completed', $this->card($completed, ['status'])['status']);
        $owned = [$before, $boundary, $after, $noDeadline, $completed];
        foreach ([false, true] as $open) {
            $result = $this->invoke(['task:my', '--user', $this->userId, '--due-before', '2026-10-20T06:00:00+06:00', ...($open ? ['--open'] : [])]);
            $returned = array_values(array_intersect($owned, array_column($result['data']['items'], 'id')));
            self::assertSame($open ? [$before] : [$before, $completed], $returned);
        }

        $result = $this->invoke(['task:my', '--user', $this->userId, '--open']);
        $returned = array_values(array_intersect($owned, array_column($result['data']['items'], 'id')));
        self::assertSame([$before, $boundary, $after, $noDeadline], $returned);
    }

    public function testSelectedOtherExecutorDoesNotChangeCaller(): void
    {
        $otherId = filter_var($this->setting('B24CLI_TEST_OTHER_USER_ID'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($otherId === false || $otherId === $this->userId) {
            self::markTestSkipped('Other executor fixture needs B24CLI_TEST_OTHER_USER_ID for a different accessible user.');
        }

        $mine = $this->createTask();
        $other = $this->createTask();
        $this->tasks->update($other, ['responsibleId' => $otherId]);
        self::assertSame($otherId, $this->card($other, ['responsible.id'])['responsible']['id']);
        $result = $this->invoke(['task:my', '--user', $otherId, '--open', '--api-policy', 'strict-rest3']);
        self::assertContains($other, array_column($result['data']['items'], 'id'));
        self::assertNotContains($mine, array_column($result['data']['items'], 'id'));
        self::assertSame([$otherId], array_values(array_unique(array_column($result['data']['items'], 'responsibleId'))));
        self::assertArrayNotHasKey('roleFilter', $result['meta']);
    }

    public function testScanBudgetReportsPartial(): void
    {
        $this->createTask();
        $this->createTask();
        $result = $this->invoke(['task:my', '--user', $this->userId, '--max-scan', '1'], [3]);
        self::assertFalse($result['meta']['complete']);
        self::assertSame(1, $result['meta']['scanned']);
        self::assertSame('scan-budget-exhausted', $result['meta']['reason']);
    }
}
