<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:history:list')]
final class ListTaskHistoryCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathReturnsTitleChangeWithItsCompletenessBoundary(): void
    {
        $before = $this->fixtureTitle();
        $id = $this->createTask($before);
        $after = $this->fixtureTitle();
        $this->tasks->update($id, ['title' => $after]);
        $result = $this->invoke(['task:history:list', $id, '--params', '{"filter":{"FIELD":"TITLE"},"order":{"createdDate":"asc"}}'], [0, 3]);
        $changes = array_values(array_filter($result['data']['items'], static fn (array $row): bool => $row['field'] === 'TITLE' && $row['to'] === $after));
        self::assertCount(1, $changes);
        self::assertSame($before, $changes[0]['from']);
        self::assertSame($this->userId, (int) $changes[0]['userId']);
        self::assertGreaterThan(0, $changes[0]['id']);
        self::assertSame('visible-task-change-history', $result['meta']['scope']);
        if (!$result['meta']['complete']) {
            self::assertSame('history-navigation-unverified', $result['meta']['reason']);
            self::assertSame('partial-result', $result['error']['code']);
        }
    }
    #[Group('portal-acceptance')]
    public function testKnownHistoryChainKeepsUnverifiedNavigationPartial(): void
    {
        $titles = [$this->fixtureTitle(), $this->fixtureTitle(), $this->fixtureTitle()];
        $id = $this->createTask($titles[0]);
        foreach (array_slice($titles, 1) as $title) {
            $this->tasks->update($id, ['title' => $title]);
        }

        $result = $this->invoke(['task:history:list', $id, '--params', '{"filter":{"FIELD":"TITLE"},"order":{"createdDate":"asc"}}'], [3]);
        foreach ([1, 2] as $index) {
            $matches = array_values(array_filter($result['data']['items'], static fn (array $row): bool => $row['from'] === $titles[$index - 1] && $row['to'] === $titles[$index]));
            self::assertCount(1, $matches);
        }

        self::assertFalse($result['meta']['complete']);
        self::assertSame('history-navigation-unverified', $result['meta']['reason']);
        $this->acceptanceRecords[] = ['case' => 'known-history-chain', 'taskId' => $id, 'apiVersion' => '1.0', 'expected' => 2, 'observed' => count($result['data']['items']), 'complete' => false, 'reason' => $result['meta']['reason']];
    }

}
