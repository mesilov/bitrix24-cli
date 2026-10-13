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
    #[Group('task-tags')]
    #[Group('happy-path')]
    public function testCreatesTaskWithTagsAndRest3Readback(): void
    {
        $result = $this->invoke(['task:add', '--title', $this->fixtureTitle(), '--creator', $this->userId, '--responsible', $this->userId, '--tag', '#плановая', '--tag', 'Этап 1']);
        $id = $result['data']['operation']['taskId'];
        self::assertSame(['3.0', '1.0'], $result['meta']['apiVersions']);
        self::assertSame(['confirmed', 'confirmed'], array_column($result['meta']['ledger'], 'status'));
        $names = array_column($this->card($id, ['id', 'tags.id', 'tags.name'])['tags'], 'name');
        sort($names);
        self::assertSame(['#плановая', 'Этап 1'], $names);
    }
}
