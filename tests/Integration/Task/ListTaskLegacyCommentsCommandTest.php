<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:legacy:comment:list')]
final class ListTaskLegacyCommentsCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathReadsLegacyFeedOfSelectedTask(): void
    {
        // A new task has no old card feed: the read must succeed as a complete, possibly empty legacy source.
        $id = $this->createTask();
        $json = $this->invoke(['task:legacy:comment:list', $id, '--all']);
        self::assertSame('legacy-comments', $json['meta']['source']);
        self::assertTrue($json['meta']['legacy']);
        self::assertSame(['1.0'], $json['meta']['apiVersions']);
        self::assertIsList($json['data']['items']);
    }

    #[Group('portal-acceptance')]
    public function testConfiguredTaskWithOldCardFeedReturnsComments(): void
    {
        // B24CLI_TEST_LEGACY_COMMENT_TASK_ID: an existing task whose discussion predates the task chat (read only).
        $taskId = (int) $this->setting('B24CLI_TEST_LEGACY_COMMENT_TASK_ID');
        if ($taskId < 1) {
            self::markTestSkipped('B24CLI_TEST_LEGACY_COMMENT_TASK_ID is not configured.');
        }

        $json = $this->invoke(['task:legacy:comment:list', $taskId, '--all']);
        self::assertNotEmpty($json['data']['items']);
        foreach ($json['data']['items'] as $item) {
            self::assertIsInt($item['id']);
            self::assertIsList($item['attachments']);
        }

        self::assertStringNotContainsString('auth%5Bap%5D', json_encode($json, JSON_THROW_ON_ERROR));
    }
}
