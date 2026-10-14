<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Application\Task\FieldSchema;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Console\B24Application;
use Bitrix24\CLI\Infrastructure\Bitrix24\ResponseNormalizer;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest1ChecklistAdapter;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest1ParticipantAdapter;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest1TimeEntryAdapter;
use Bitrix24\CLI\Infrastructure\Bitrix24\Rest3TaskAdapter;
use Bitrix24\CLI\Infrastructure\Bitrix24\SdkApiTransport;
use Bitrix24\CLI\Infrastructure\Bitrix24\TaskChatAdapter;
use Bitrix24\CLI\Infrastructure\Connection\B24ClientProvider;
use Bitrix24\CLI\Infrastructure\Connection\EnvConnectionResolver;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;

/** Disposable SDK fixtures isolate each CLI command's integration cases. */
abstract class LiveTaskCommandTestCase extends LiveCommandTestCase
{
    use SecondUserFixtures;

    protected array $acceptanceRecords = [];
    protected Rest3TaskAdapter $tasks;
    protected TaskChatAdapter $chat;
    protected Rest1TimeEntryAdapter $entries;
    protected Rest1ChecklistAdapter $checklists;
    protected Rest1ParticipantAdapter $participants;
    private array $createdTasks = [];
    private array $ownedTaskIds = [];
    private array $createdDiskFiles = [];
    private array $createdDiskFolders = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tasks = new Rest3TaskAdapter($this->api);
        $this->chat = new TaskChatAdapter($this->api);
        $this->entries = new Rest1TimeEntryAdapter($this->api);
        $this->checklists = new Rest1ChecklistAdapter($this->api);
        $this->participants = new Rest1ParticipantAdapter($this->api);
    }

    protected function tearDown(): void
    {
        $failed = [];
        foreach (array_reverse($this->createdTasks) as $id) {
            try {
                $this->tasks->delete($id);
            } catch (Failure) {
                $failed[] = $id;
            }
        }

        $failedFiles = [];
        foreach ($this->createdDiskFiles as $fileId) {
            try {
                ResponseNormalizer::ack($this->api->call('disk.file.delete', 1, ['id' => $fileId], 'delete')->result);
            } catch (Failure) {
                $failedFiles[] = $fileId;
            }
        }

        $failedFolders = [];
        foreach ($this->createdDiskFolders as $folderId) {
            try {
                ResponseNormalizer::ack($this->api->call('disk.folder.deleteTree', 1, ['id' => $folderId], 'delete')->result);
            } catch (Failure) {
                $failedFolders[] = $folderId;
            }
        }

        if (isset($this->userId)) {
            $evidence = ['class' => static::class, 'test' => $this->nameWithDataSet(), 'command' => $this->commandName(), 'happyPath' => $this->isHappyPath(), 'incomplete' => $this->status()->isIncomplete(), 'actingUserId' => $this->userId, 'ownedTaskIds' => $this->ownedTaskIds, 'ownedDiskFileIds' => $this->createdDiskFiles, 'ownedDiskFolderIds' => $this->createdDiskFolders, 'commands' => $this->observedCommands, 'cleanupFailures' => ['taskIds' => $failed, 'diskFileIds' => $failedFiles, 'diskFolderIds' => $failedFolders]];
            $evidence['acceptance'] = $this->acceptanceRecords;
            $evidence['passed'] = !$this->status()->isFailure() && !$this->status()->isError() && !$this->status()->isSkipped() && !$this->status()->isIncomplete();
            $directory = $this->root() . '/var/cache';
            if (!is_dir($directory)) {
                mkdir($directory, 0700, true);
            }

            self::assertNotFalse(file_put_contents($directory . '/portal-evidence.jsonl', json_encode($evidence, JSON_THROW_ON_ERROR) . "\n", FILE_APPEND | LOCK_EX));
        }

        if ($failed !== [] || $failedFiles !== [] || $failedFolders !== []) {
            self::fail('Could not remove owned test fixtures: task IDs [' . implode(', ', $failed) . '], Disk file IDs [' . implode(', ', $failedFiles) . '], Disk folder IDs [' . implode(', ', $failedFolders) . ']. Remove them manually.');
        }

        if (isset($this->userId) && $this->isHappyPath() && !$this->status()->isSkipped() && !$this->status()->isIncomplete()) {
            $expectedPartial = match ($this->commandName()) {
                'task:time:show' => 'Selected elapsedTime was not returned.',
                'task:history:list' => 'history-navigation-unverified',
                default => null,
            };
            $happyCalls = array_filter($this->observedCommands, static fn (array $call): bool => $call['status'] === 0 || ($expectedPartial !== null && $call['status'] === 3 && $call['errorCode'] === 'partial-result' && $call['complete'] === false && $call['reason'] === $expectedPartial));
            self::assertNotEmpty($happyCalls, 'The happy-path case must execute its own CLI command successfully.');
        }
    }

    protected function afterInvoke(array $argv, array $json): void
    {
        if ($argv[0] === 'task:add') {
            foreach ($json['data']['items'] ?? [$json['data']['operation'] ?? []] as $operation) {
                $id = $operation['resourceId'] ?? null;
                if (($operation['action'] ?? null) === 'add' && is_int($id) && $id > 0) {
                    $this->ownTask($id);
                }
            }
        }

        if ($argv[0] === 'task:file:attach') {
            foreach ($json['data']['items'] ?? [] as $item) {
                if (($item['action'] ?? '') === 'upload' && is_int($id = $item['resourceId'] ?? null) && $id > 0) {
                    $this->createdDiskFiles[] = $id;
                }
            }
        }

    }

    protected function fixtureTitle(): string
    {
        return 'b24cli-test-' . bin2hex(random_bytes(8));
    }

    protected function createTask(?string $title = null, array $fields = []): int
    {
        $id = $this->tasks->add(['title' => $title ?? $this->fixtureTitle(), 'creatorId' => $this->userId, 'responsibleId' => $this->userId, 'description' => 'original', ...$fields]);
        $this->ownTask($id);
        return $id;
    }

    private function ownTask(int $id): void
    {
        $this->createdTasks[] = $id;
        $this->ownedTaskIds[] = $id;
    }

    protected function forgetDeletedTask(int $id): void
    {
        $this->createdTasks = array_values(array_diff($this->createdTasks, [$id]));
    }

    protected function card(int $taskId, array $select = FieldSchema::CARD): array
    {
        return $this->tasks->get($taskId, $select);
    }

    protected function chatMessages(int $taskId): array
    {
        $chatId = $this->card($taskId, ['chat.id'])['chat']['id'];
        $page = $this->chat->messages($chatId, null, null, 50);
        self::assertNull($page->next, 'Disposable fixture chat must fit the SDK readback window.');
        return $page->items;
    }

    protected function createMessage(int $taskId, string $text): int
    {
        $this->chat->send($taskId, $text);
        $matches = array_values(array_filter($this->chatMessages($taskId), static fn (array $row): bool => $row['text'] === $text));
        self::assertCount(1, $matches, 'Find the unique SDK fixture message in its own task chat.');
        return $matches[0]['id'];
    }

    protected function timeEntries(int $taskId): array
    {
        $page = $this->entries->page($taskId, [], 1);
        self::assertNull($page->next, 'Disposable time entries must fit the SDK readback page.');
        return array_column($page->items, null, 'id');
    }

    protected function checklistNodes(int $taskId): array
    {
        return array_column($this->checklists->nodes($taskId), null, 'id');
    }

    /** SDK TemporaryDiskFixture approach: create and clean up only a unique owned child. */
    protected function createDiskFolder(): int
    {
        $rows = $this->api->call('disk.storage.getlist', 1, ['filter' => ['ENTITY_TYPE' => 'user', 'ENTITY_ID' => $this->userId]])->result;
        $owned = array_values(array_filter($rows, fn (array $row): bool => ($row['ENTITY_TYPE'] ?? null) === 'user' && (int) ($row['ENTITY_ID'] ?? 0) === $this->userId));
        self::assertCount(1, $owned, 'Fixture needs the webhook user personal storage.');
        $folder = $this->api->call('disk.folder.addSubFolder', 1, ['id' => (int) $owned[0]['ROOT_OBJECT_ID'], 'data' => ['NAME' => $this->fixtureTitle()]], 'write')->result;
        $id = (int) ($folder['ID'] ?? 0);
        self::assertGreaterThan(0, $id);
        $this->createdDiskFolders[] = $id;
        return $id;
    }

    protected function deleteCreatedDiskFolder(int $id): void
    {
        self::assertContains($id, $this->createdDiskFolders);
        ResponseNormalizer::ack($this->api->call('disk.folder.deleteTree', 1, ['id' => $id], 'delete')->result);
        $this->createdDiskFolders = array_values(array_diff($this->createdDiskFolders, [$id]));
    }

    protected function diskFile(): int
    {
        $configured = $this->setting('B24CLI_TEST_DISK_FILE_ID');
        if ($configured !== '') {
            self::assertMatchesRegularExpression('/^[1-9][0-9]*$/', $configured);
            return (int) $configured;
        }

        $rows = $this->api->call('disk.storage.getlist', 1, ['filter' => ['ENTITY_TYPE' => 'user', 'ENTITY_ID' => $this->userId]])->result;
        $owned = array_values(array_filter($rows, fn (array $row): bool => ($row['ENTITY_TYPE'] ?? null) === 'user' && (int) ($row['ENTITY_ID'] ?? 0) === $this->userId));
        self::assertCount(1, $owned, 'Auto fixture needs exactly one storage belonging to the webhook user.');
        $name = $this->fixtureTitle() . '.txt';
        $file = $this->api->call('disk.folder.uploadfile', 1, ['id' => (int) $owned[0]['ROOT_OBJECT_ID'], 'data' => ['NAME' => $name], 'fileContent' => [$name, base64_encode("Disposable b24cli integration fixture\n")]], 'write')->result;
        $id = (int) ($file['ID'] ?? 0);
        if ($id > 0) {
            $this->createdDiskFiles[] = $id;
        }

        self::assertGreaterThan(0, $id);
        return $id;
    }
}
