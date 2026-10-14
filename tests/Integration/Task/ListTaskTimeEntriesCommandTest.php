<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:time:list')]
final class ListTaskTimeEntriesCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathListsOwnTimeEntry(): void
    {
        $id = $this->createTask();
        $entry = $this->entries->add($id, 60, 'fixture');
        $items = $this->invoke(['task:time:list', $id])['data']['items'];
        self::assertCount(1, $items);
        self::assertSame($entry, $items[0]['id']);
        self::assertSame(60, $items[0]['seconds']);
        self::assertSame('fixture', $items[0]['text']);
        self::assertSame($this->userId, $items[0]['userId']);
    }
    #[Group('portal-acceptance')]
    public function testSecondUserWithoutTaskRoleCannotAccessOwnedFixture(): void
    {
        [$second] = $this->secondUser();
        $id = $this->createTask();

        $before = $this->timeEntries($id);
        $this->assertSecondUserDenied(['task:time:list', $id]);
        self::assertSame($before, $this->timeEntries($id));
    }

    #[Group('portal-acceptance')]
    public function testKnownTimeEntriesAcrossDocumentedNavigation(): void
    {
        $id = $this->createTask();
        $expected = [];
        foreach ([60, 120, 180] as $seconds) {
            $expected[] = $this->entries->add($id, $seconds, 'navigation-fixture');
        }

        $params = '{"order":{"ID":"ASC"},"params":{"NAV_PARAMS":{"nPageSize":2,"iNumPage":1}}}';
        $result = $this->invoke(['task:time:list', $id, '--params', $params], [0, 3]);
        $this->acceptanceRecords[] = ['case' => 'known-time-navigation', 'taskId' => $id, 'apiVersion' => '1.0', 'expected' => $expected, 'observed' => array_column($result['data']['items'], 'id'), 'pageSize' => 2, 'complete' => $result['meta']['complete']];
        self::assertSame($expected, array_column($result['data']['items'], 'id'), 'Known navigation regression: incomplete controlled fixture must never be reported complete.');
        self::assertSame([60, 120, 180], array_column($result['data']['items'], 'seconds'));
        self::assertSame([$this->userId, $this->userId, $this->userId], array_column($result['data']['items'], 'userId'));
        self::assertTrue($result['meta']['complete']);
        self::assertCount(2, $result['meta']['apiCalls']);
        $this->acceptanceRecords[] = ['case' => 'known-time-navigation', 'taskId' => $id, 'apiVersion' => '1.0', 'expected' => $expected, 'observed' => array_column($result['data']['items'], 'id'), 'pageSize' => 2, 'complete' => $result['meta']['complete']];
    }

}
