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

    #[Group('happy-path')]
    public function testHappyPathUploadsBinaryLocalFileAndReadsDiskMetadata(): void
    {
        $folderId = $this->createDiskFolder();
        $taskId = $this->createTask();
        $bytes = "Disposable UTF-8 fixture\0\xff" . str_repeat('x', 100);
        $this->withLocalFile($bytes, function (string $path) use ($folderId, $taskId, $bytes): void {
            $result = $this->invoke(['task:file:attach', $taskId, '--file', $path, '--folder-id', $folderId]);
            self::assertSame(['upload', 'attach'], array_column($result['data']['items'], 'action'));
            self::assertSame(['1.0', '3.0'], $result['meta']['apiVersions']);
            $id = $result['data']['items'][0]['resourceId'];
            $file = $this->api->call('disk.file.get', 1, ['id' => $id])->result;
            self::assertSame($id, (int) $file['ID']);
            self::assertSame(basename($path), $file['NAME']);
            self::assertSame(strlen($bytes), (int) $file['SIZE']);
            self::assertSame($folderId, (int) $file['PARENT_ID']);
            self::assertTrue($result['data']['items'][1]['confirmed']);
            // Attach ACK is not independent task membership readback.
        });
    }

    #[Group('happy-path')]
    public function testStreamedUploadReadsBackFullDiskSize(): void
    {
        $folderId = $this->createDiskFolder();
        $taskId = $this->createTask();
        $bytes = str_repeat('x', \Bitrix24\CLI\Infrastructure\Filesystem\LocalFileSource::DIRECT_ENCODING_BYTES) . "\0\xff";
        $this->withLocalFile($bytes, function (string $path) use ($folderId, $taskId, $bytes): void {
            $result = $this->invoke(['task:file:attach', $taskId, '--file', $path, '--folder-id', $folderId, '--timeout', '120']);
            $id = $result['data']['items'][0]['resourceId'];
            $file = $this->api->call('disk.file.get', 1, ['id' => $id])->result;
            self::assertSame(strlen($bytes), (int) $file['SIZE']);
            self::assertSame($folderId, (int) $file['PARENT_ID']);
            self::assertSame(basename($path), $file['NAME']);
            self::assertTrue($result['data']['items'][1]['confirmed']);
            self::assertSame(['1.0', '3.0'], $result['meta']['apiVersions']);
        });
    }

    public function testCollisionGeneratesUniqueNameWithoutReplacingFile(): void
    {
        $folderId = $this->createDiskFolder();
        $taskId = $this->createTask();
        $this->withLocalFile('Disposable collision fixture', function (string $path) use ($folderId, $taskId): void {
            $first = $this->invoke(['task:file:attach', $taskId, '--file', $path, '--folder-id', $folderId, '--name', 'Отчёт.pdf']);
            $second = $this->invoke(['task:file:attach', $taskId, '--file', $path, '--folder-id', $folderId, '--name', 'Отчёт.pdf']);
            $firstId = $first['data']['items'][0]['resourceId'];
            $secondId = $second['data']['items'][0]['resourceId'];
            self::assertNotSame($firstId, $secondId);
            self::assertNotSame($first['data']['items'][0]['NAME'], $second['data']['items'][0]['NAME']);
            foreach ([$firstId, $secondId] as $id) {
                self::assertSame($id, (int) $this->api->call('disk.file.get', 1, ['id' => $id])->result['ID']);
            }
        });
    }

    public function testMissingTaskPreservesUploadedDiskFileAndAllowsExplicitRecovery(): void
    {
        $folderId = $this->createDiskFolder();
        $taskId = $this->createTask();
        $this->tasks->delete($taskId);
        $this->forgetDeletedTask($taskId);
        $validTask = $this->createTask();
        $this->withLocalFile('Disposable partial fixture', function (string $path) use ($folderId, $taskId, $validTask): void {
            $result = $this->invoke(['task:file:attach', $taskId, '--file', $path, '--folder-id', $folderId], [3]);
            self::assertFalse($result['meta']['complete']);
            self::assertSame(['confirmed', 'failed'], array_column($result['meta']['ledger'], 'status'));
            $id = $result['data']['items'][0]['resourceId'];
            self::assertSame($id, (int) $this->api->call('disk.file.get', 1, ['id' => $id])->result['ID']);
            $recovery = $this->invoke(['task:file:attach', $validTask, '--file-id', $id]);
            self::assertSame(['3.0'], $recovery['meta']['apiVersions']);
            self::assertSame($id, $recovery['data']['items'][0]['resourceId']);
        });
    }

    public function testDeletedFolderStopsBeforeAttachment(): void
    {
        $folderId = $this->createDiskFolder();
        $this->deleteCreatedDiskFolder($folderId);
        $taskId = $this->createTask();
        $this->withLocalFile('Disposable invalid folder fixture', function (string $path) use ($folderId, $taskId): void {
            $result = $this->invoke(['task:file:attach', $taskId, '--file', $path, '--folder-id', $folderId], [1]);
            self::assertCount(1, $result['meta']['apiCalls']);
            self::assertSame('disk.folder.uploadFile', $result['meta']['apiCalls'][0]['method']);
            self::assertSame(['failed', 'skipped'], array_column($result['error']['details']['ledger'], 'status'));
        });
    }

    public function testRestrictedRoleCannotUploadToOwnedFolder(): void
    {
        $webhook = $this->setting('B24CLI_TEST_RESTRICTED_WEBHOOK');
        if ($webhook === '') {
            self::markTestSkipped('Denied-role upload needs B24CLI_TEST_RESTRICTED_WEBHOOK without access to the owner folder.');
        }

        $folderId = $this->createDiskFolder();
        $taskId = $this->createTask();
        $resolver = new readonly class ($webhook) implements \Bitrix24\CLI\Infrastructure\Connection\ConnectionResolver {
            public function __construct(private string $webhook)
            {
            }
            public function webhook(): string
            {
                return $this->webhook;
            }
        };
        $app = (new \Bitrix24\CLI\Bootstrap\ApplicationFactory())->create($this->root(), null, $resolver);
        $this->withLocalFile('Disposable denied-role fixture', function (string $path) use ($folderId, $taskId, $app): void {
            $result = $this->invoke(['task:file:attach', $taskId, '--file', $path, '--folder-id', $folderId], [1], $app);
            self::assertSame('permission-denied', $result['error']['code']);
            self::assertCount(1, $result['meta']['apiCalls']);
            self::assertSame(['failed', 'skipped'], array_column($result['error']['details']['ledger'], 'status'));
        });
    }

    private function withLocalFile(string $bytes, \Closure $test): void
    {
        $filesystem = new \Symfony\Component\Filesystem\Filesystem();
        $directory = sys_get_temp_dir() . '/b24cli-live-' . bin2hex(random_bytes(6));
        $path = $directory . '/Локальный отчёт.bin';
        $filesystem->dumpFile($path, $bytes);
        try {
            $test($path);
        } finally {
            $filesystem->remove($directory);
        }
    }
}
