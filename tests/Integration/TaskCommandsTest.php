<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Console\B24Application;
use Bitrix24\CLI\Infrastructure\Bitrix24\SdkApiTransport;
use Bitrix24\CLI\Infrastructure\Connection\B24ClientProvider;
use Bitrix24\CLI\Infrastructure\Connection\EnvConnectionResolver;
use Bitrix24\CLI\Infrastructure\Connection\ConnectionResolver;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;

/** Live tests create disposable tasks under the webhook identity and read back changes. */
final class TaskCommandsTest extends TestCase
{
    private B24Application $app;
    private int $userId;
    private array $createdTasks = [];
    private array $settings = [];
    private SdkApiTransport $api;
    private array $createdDiskFiles = [];
    private array $ownedTaskIds = [];
    private array $observedCommands = [];

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $envConnectionResolver = new EnvConnectionResolver($root);
        try {
            $envConnectionResolver->webhook();
        } catch (Failure $failure) {
            if ($failure->errorCode === 'connection-unavailable') {
                self::markTestSkipped('Live portal test needs BITRIX24_WEBHOOK in root .env or process environment.');
            }

            throw $failure;
        }

        $path = $envConnectionResolver->environmentFile();
        if (is_file($path)) {
            $this->settings = (new Dotenv())->parse(file_get_contents($path));
        }

        $runtimeState = new RuntimeState();
        $this->api = new SdkApiTransport(new B24ClientProvider($envConnectionResolver, $runtimeState), $runtimeState);
        $this->userId = (int) ($this->api->call('profile', 1, [])->result['ID'] ?? 0);
        self::assertGreaterThan(0, $this->userId, 'Webhook profile must return the acting user ID.');
        $this->app = (new ApplicationFactory())->create($root);
    }

    protected function tearDown(): void
    {
        $failed = [];
        foreach (array_reverse($this->createdTasks) as $id) {
            $run = ConsoleHarness::run($this->app, ['--json', 'task:delete', (string) $id, '--force']);
            if ($run->status !== 0) {
                $failed[] = $id;
            }
        }

        $failedFiles = [];
        foreach ($this->createdDiskFiles as $fileId) {
            try {
                \Bitrix24\CLI\Infrastructure\Bitrix24\ResponseNormalizer::ack($this->api->call('disk.file.delete', 1, ['id' => $fileId], 'delete')->result);
            } catch (Failure) {
                $failedFiles[] = $fileId;
            }
        }

        if (isset($this->userId)) {
            $evidence = ['test' => $this->name(), 'actingUserId' => $this->userId, 'ownedTaskIds' => $this->ownedTaskIds, 'ownedDiskFileIds' => $this->createdDiskFiles, 'commands' => $this->observedCommands, 'cleanupFailures' => ['taskIds' => $failed, 'diskFileIds' => $failedFiles]];
            self::assertNotFalse(file_put_contents(dirname(__DIR__, 2) . '/var/cache/portal-evidence.jsonl', json_encode($evidence, JSON_THROW_ON_ERROR) . "\n", FILE_APPEND | LOCK_EX));
        }

        if ($failed !== [] || $failedFiles !== []) {
            self::fail('Could not remove owned test fixtures: task IDs [' . implode(', ', $failed) . '], Disk file IDs [' . implode(', ', $failedFiles) . ']. Remove them manually.');
        }
    }

    private function invoke(array $argv, array $statuses = [0]): array
    {
        $run = ConsoleHarness::run($this->app, ['--json', ...array_map(strval(...), $argv)]);
        $json = $run->json();
        $this->observedCommands[] = ['command' => $argv[0], 'status' => $run->status, 'apiCalls' => $json['meta']['apiCalls'] ?? [], 'errorCode' => $json['error']['code'] ?? null, 'apiErrorCode' => $json['error']['details']['apiErrorCode'] ?? null];
        self::assertContains($run->status, $statuses, $run->stdout . $run->stderr);
        return $json;
    }

    private function newTask(): int
    {
        $result = $this->invoke(['task:add', '--title', 'b24cli-test-' . bin2hex(random_bytes(8)), '--creator', $this->userId, '--responsible', $this->userId, '--description', 'original']);
        $id = $result['data']['operation']['resourceId'];
        self::assertIsInt($id);
        $this->createdTasks[] = $id;
        $this->ownedTaskIds[] = $id;
        return $id;
    }

    private function card(int $taskId, array $select = []): array
    {
        $argv = ['task:show', $taskId];
        foreach ($select as $field) {
            $argv[] = '--select';
            $argv[] = $field;
        }

        return $this->invoke($argv)['data']['task'];
    }

    public function testTaskCardSchemaSearchAndSparseUpdates(): void
    {
        $id = $this->newTask();
        $title = 'b24cli-ёж-' . bin2hex(random_bytes(8));
        $this->invoke(['task:update', $id, '--title', $title]);
        self::assertSame($title, $this->card($id)['title']);
        self::assertSame('original', $this->card($id)['description']);
        $this->invoke(['task:update', $id, '--description', '']);
        self::assertSame('', $this->card($id)['description']);
        $this->invoke(['task:assign', $id, '--responsible', $this->userId]);
        self::assertSame($this->userId, $this->card($id)['responsibleId']);
        $at = '2027-01-10T12:00:00+06:00';
        $this->invoke(['task:deadline:set', $id, '--at', $at]);
        self::assertSame(strtotime($at), strtotime($this->card($id)['deadline']));
        $items = $this->invoke(['task:list', '--id', $id])['data']['items'];
        self::assertContains($id, array_column($items, 'id'));
        $search = $this->invoke(['task:find', '--title', mb_strtoupper($title), '--all'], [0, 3]);
        // A finite scan on a large portal may not reach this task; preserve that boundary.
        if ($search['meta']['complete']) {
            self::assertContains($id, array_column($search['data']['items'], 'id'));
        } else {
            self::assertSame('partial-result', $search['error']['code']);
        }

        self::assertNotEmpty($this->invoke(['task:fields:list'])['data']['items']);
        self::assertSame('title', $this->invoke(['task:fields:list', '--name', 'title'])['data']['items'][0]['name']);
        self::assertIsArray($this->invoke(['task:access:show', $id])['data']['access']);
        $this->invoke(['task:delete', $id, '--force']);
        $this->createdTasks = array_values(array_diff($this->createdTasks, [$id]));
        $run = ConsoleHarness::run($this->app, ['--json', 'task:show', (string) $id]);
        self::assertSame(1, $run->status);
    }

    public function testTaskChatSendReadUpdateDeleteAndWrongTaskBinding(): void
    {
        $id = $this->newTask();
        $text = 'b24cli-message-' . bin2hex(random_bytes(8));
        $sent = $this->invoke(['task:chat:send', $id, '--text', $text]);
        self::assertNull($sent['data']['operation']['resourceId']);
        $messages = $this->invoke(['task:chat:list', $id, '--all'])['data']['items'];
        $own = array_values(array_filter($messages, static fn (array $row): bool => $row['text'] === $text));
        self::assertCount(1, $own, 'Find the unique fixture message ID by reading its own task chat.');
        $messageId = $own[0]['id'];
        $other = $this->newTask();
        $rejected = $this->invoke(['task:chat:update', $other, '--message', $messageId, '--text', 'foreign'], [4]);
        self::assertSame('message-binding-unverified', $rejected['error']['code']);
        $this->invoke(['task:chat:update', $id, '--message', $messageId, '--text', $text . '-changed']);
        $messages = $this->invoke(['task:chat:list', $id, '--all'])['data']['items'];
        self::assertContains($text . '-changed', array_column($messages, 'text'));
        $this->invoke(['task:chat:delete', $id, '--message', $messageId, '--force']);
        $messages = $this->invoke(['task:chat:list', $id, '--all'])['data']['items'];
        self::assertNotContains($text . '-changed', array_column($messages, 'text'));
    }

    public function testOwnTimeEntriesReadBackSecondsAndKeepOmittedText(): void
    {
        $id = $this->newTask();
        $entry = $this->invoke(['task:time:add', $id, '--seconds', '60', '--text', 'fixture'])['data']['operation']['resourceId'];
        $entries = $this->invoke(['task:time:list', $id])['data']['items'];
        self::assertContains($entry, array_column($entries, 'id'));
        $other = $this->newTask();
        $this->invoke(['task:time:update', $other, '--entry', $entry, '--seconds', '120'], [4]);
        $this->invoke(['task:time:update', $id, '--entry', $entry, '--seconds', '120']);
        $rows = array_values(array_filter($this->invoke(['task:time:list', $id])['data']['items'], static fn (array $row): bool => $row['id'] === $entry));
        self::assertSame(120, $rows[0]['seconds']);
        self::assertSame('fixture', $rows[0]['text']);
        self::assertArrayHasKey('elapsedTime', $this->invoke(['task:time:show', $id], [0, 3])['data']['time']);
        $this->invoke(['task:time:delete', $id, '--entry', $entry, '--force']);
        self::assertNotContains($entry, array_column($this->invoke(['task:time:list', $id])['data']['items'], 'id'));
    }

    public function testChecklistRootsNestedItemsAndStateDoNotChangeTaskStatus(): void
    {
        $id = $this->newTask();
        $status = $this->card($id)['status'];
        $root = $this->invoke(['task:checklist:add', $id, '--title', 'Root'])['data']['operation']['resourceId'];
        $secondRoot = $this->invoke(['task:checklist:add', $id, '--title', 'Other root'])['data']['operation']['resourceId'];
        self::assertContains($root, array_column($this->invoke(['task:checklist:list', $id])['data']['items'], 'id'));
        $item = $this->invoke(['task:checklist:item:add', $id, '--checklist', $root, '--title', 'Item'])['data']['operation']['resourceId'];
        $nested = $this->invoke(['task:checklist:item:add', $id, '--checklist', $root, '--parent', $item, '--title', 'Nested'])['data']['operation']['resourceId'];
        $this->invoke(['task:checklist:item:add', $id, '--checklist', $secondRoot, '--parent', $item, '--title', 'Wrong parent'], [4]);
        $this->invoke(['task:checklist:item:update', $id, '--item', $item, '--title', 'Changed']);
        $this->invoke(['task:checklist:item:complete', $id, '--item', $item]);
        $items = $this->invoke(['task:checklist:item:list', $id, '--checklist', $root])['data']['items'];
        $byId = array_column($items, null, 'id');
        self::assertSame('Changed', $byId[$item]['title']);
        self::assertTrue($byId[$item]['isComplete']);
        self::assertSame($item, $byId[$nested]['parentId']);
        $this->invoke(['task:checklist:item:renew', $id, '--item', $item]);
        $items = $this->invoke(['task:checklist:item:list', $id, '--checklist', $root])['data']['items'];
        self::assertFalse(array_column($items, null, 'id')[$item]['isComplete']);
        self::assertSame($status, $this->card($id)['status']);
        $this->invoke(['task:checklist:item:delete', $id, '--item', $item, '--force']);
        self::assertSame([], $this->invoke(['task:checklist:item:list', $id, '--checklist', $root])['data']['items']);
    }

    public function testParticipantsOmissionClearAndHistoryCompletenessBoundary(): void
    {
        $id = $this->newTask();
        $this->invoke(['task:participants:set', $id, '--auditor', $this->userId]);
        // Filled REST3 auditor projections currently return INTERNAL_SERVER_ERROR.
        // Explicit legacy readback verifies the legacy write; CLI has no fallback.
        $before = $this->api->call('tasks.task.get', 1, ['taskId' => $id])->result['task'];
        self::assertContains((string) $this->userId, $before['auditors']);
        $this->invoke(['task:participants:set', $id, '--clear-accomplices']);
        $after = $this->api->call('tasks.task.get', 1, ['taskId' => $id])->result['task'];
        self::assertSame($before['auditors'], $after['auditors']);
        self::assertSame([], $after['accomplices']);
        $this->invoke(['task:participants:set', $id, '--clear-auditors']);
        self::assertSame([], $this->card($id, ['auditors'])['auditors']);
        $this->invoke(['task:update', $id, '--title', 'history-fixture']);
        $history = $this->invoke(['task:history:list', $id, '--params', '{"filter":{"FIELD":"TITLE"},"order":{"createdDate":"asc"}}'], [0, 3]);
        self::assertIsArray($history['data']['items']);
        self::assertNotEmpty($history['data']['items']);
        if (!$history['meta']['complete']) {
            self::assertSame('partial-result', $history['error']['code']);
        }
    }

    public function testAttachExistingDiskFileWhenFixtureIsConfigured(): void
    {
        $fileId = getenv('B24CLI_TEST_DISK_FILE_ID') ?: ($this->settings['B24CLI_TEST_DISK_FILE_ID'] ?? null);
        if ($fileId === null || $fileId === '') {
            $rows = $this->api->call('disk.storage.getlist', 1, ['filter' => ['ENTITY_TYPE' => 'user', 'ENTITY_ID' => $this->userId]])->result;
            $owned = array_values(array_filter($rows, fn (array $row): bool => ($row['ENTITY_TYPE'] ?? null) === 'user' && (int) ($row['ENTITY_ID'] ?? 0) === $this->userId));
            self::assertCount(1, $owned, 'Auto fixture needs exactly one storage belonging to the webhook user.');
            $name = 'b24cli-test-' . bin2hex(random_bytes(8)) . '.txt';
            $file = $this->api->call('disk.folder.uploadfile', 1, [
                'id' => (int) $owned[0]['ROOT_OBJECT_ID'], 'data' => ['NAME' => $name],
                'fileContent' => [$name, base64_encode("Disposable b24cli integration fixture\n")],
            ], 'write')->result;
            $fileId = (string) ($file['ID'] ?? '');
            if (ctype_digit($fileId) && (int) $fileId > 0) {
                $this->createdDiskFiles[] = (int) $fileId;
            }
        }

        self::assertMatchesRegularExpression('/^[1-9][0-9]*$/', $fileId);
        $id = $this->newTask();
        $result = $this->invoke(['task:file:attach', $id, '--file-id', $fileId]);
        self::assertSame((int) $fileId, $result['data']['items'][0]['resourceId']);
        // REST 3 get does not populate fileIds; attachment verification is ACK only.
        self::assertNull($this->card($id, ['fileIds'])['fileIds']);
    }

    public function testFilledAuditorProjectionReportsPortalCapabilityWithoutFallback(): void
    {
        $id = $this->newTask();
        $this->invoke(['task:participants:set', $id, '--auditor', $this->userId]);
        $result = $this->invoke(['task:show', $id, '--select', 'auditors.id'], [0, 1]);
        self::assertSame(['3.0'], array_column($result['meta']['apiCalls'], 'apiVersion'));
        if ($result['error'] === null) {
            self::assertContains($this->userId, array_column($result['data']['task']['auditors'], 'id'));
        } else {
            self::assertSame('api-error', $result['error']['code']);
            self::assertSame('INTERNAL_SERVER_ERROR', $result['error']['details']['apiErrorCode']);
        }
    }

    public function testRestrictedRoleCannotEditFixtureTask(): void
    {
        $webhook = getenv('B24CLI_TEST_RESTRICTED_WEBHOOK') ?: ($this->settings['B24CLI_TEST_RESTRICTED_WEBHOOK'] ?? '');
        if ($webhook === '') {
            self::markTestSkipped('Denied-role case needs B24CLI_TEST_RESTRICTED_WEBHOOK for a non-admin without edit access.');
        }

        $id = $this->newTask();
        $before = $this->card($id)['title'];
        $resolver = new readonly class ($webhook) implements ConnectionResolver {
            public function __construct(private string $webhook)
            {
            }
            public function webhook(): string
            {
                return $this->webhook;
            }
        };
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), null, $resolver);
        $run = ConsoleHarness::run($app, ['--json', 'task:update', (string) $id, '--title', 'unauthorized-change']);
        self::assertSame(1, $run->status, $run->stdout);
        self::assertSame('permission-denied', $run->json()['error']['code']);
        self::assertSame($before, $this->card($id)['title']);
    }
}
