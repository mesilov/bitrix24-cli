<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\Task;

use Bitrix24\CLI\Tests\Support\LiveTaskCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('command:task:file:attach')]
final class AttachTaskFilesCommandTest extends LiveTaskCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathConfirmsDiskFileAttachment(): void
    {
        $fileId = $this->diskFile();
        $id = $this->createTask();
        $result = $this->invoke(['task:file:attach', $id, '--file-id', $fileId]);
        self::assertSame($fileId, $result['data']['items'][0]['resourceId']);
        self::assertSame($id, $result['data']['items'][0]['taskId']);
        self::assertTrue($result['data']['items'][0]['confirmed']);
        // Membership is not independently proven by the REST3 fileIds projection.
        self::assertNull($this->card($id, ['fileIds'])['fileIds']);
    }
}
