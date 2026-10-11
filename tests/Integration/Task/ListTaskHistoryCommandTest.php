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
}
