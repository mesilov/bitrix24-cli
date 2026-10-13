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
abstract class LiveTaskCommandTestCase extends TestCase
{
    protected B24Application $app;
    protected int $userId;
    protected SdkApiTransport $api;
    protected Rest3TaskAdapter $tasks;
    protected TaskChatAdapter $chat;
    protected Rest1TimeEntryAdapter $entries;
    protected Rest1ChecklistAdapter $checklists;
    protected Rest1ParticipantAdapter $participants;
    private array $settings = [];
    private array $createdTasks = [];
    private array $ownedTaskIds = [];
    private array $createdDiskFiles = [];
    private array $observedCommands = [];

    protected function setUp(): void
    {
        $envConnectionResolver = new EnvConnectionResolver($this->root());
        try {
            $envConnectionResolver->webhook();
        } catch (Failure $failure) {
            if ($failure->errorCode === 'connection-unavailable') {
                self::markTestSkipped('Live portal tests need BITRIX24_WEBHOOK in the selected root environment file.');
            }

            throw $failure;
        }

        $path = $envConnectionResolver->environmentFile();
        if (is_file($path)) {
            $this->settings = (new Dotenv())->parse(file_get_contents($path));
        }

        $runtimeState = new RuntimeState();
        $this->api = new SdkApiTransport(new B24ClientProvider($envConnectionResolver, $runtimeState), $runtimeState);
        $this->tasks = new Rest3TaskAdapter($this->api);
        $this->chat = new TaskChatAdapter($this->api);
        $this->entries = new Rest1TimeEntryAdapter($this->api);
        $this->checklists = new Rest1ChecklistAdapter($this->api);
        $this->participants = new Rest1ParticipantAdapter($this->api);
        $this->userId = (int) ($this->api->call('profile', 1, [])->result['ID'] ?? 0);
        self::assertGreaterThan(0, $this->userId, 'Webhook profile must return the acting user ID.');
        $this->app = (new ApplicationFactory())->create($this->root());
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

        if (isset($this->userId)) {
            $evidence = ['class' => static::class, 'test' => $this->name(), 'command' => $this->commandName(), 'happyPath' => $this->isHappyPath(), 'actingUserId' => $this->userId, 'ownedTaskIds' => $this->ownedTaskIds, 'ownedDiskFileIds' => $this->createdDiskFiles, 'commands' => $this->observedCommands, 'cleanupFailures' => ['taskIds' => $failed, 'diskFileIds' => $failedFiles]];
            self::assertNotFalse(file_put_contents($this->root() . '/var/cache/portal-evidence.jsonl', json_encode($evidence, JSON_THROW_ON_ERROR) . "\n", FILE_APPEND | LOCK_EX));
        }

        if ($failed !== [] || $failedFiles !== []) {
            self::fail('Could not remove owned test fixtures: task IDs [' . implode(', ', $failed) . '], Disk file IDs [' . implode(', ', $failedFiles) . ']. Remove them manually.');
        }

        if (isset($this->userId) && $this->isHappyPath() && !$this->status()->isSkipped()) {
            $expectedPartial = match ($this->commandName()) {
                'task:time:show' => 'Selected elapsedTime was not returned.',
                'task:history:list' => 'history-navigation-unverified',
                default => null,
            };
            $happyCalls = array_filter($this->observedCommands, static fn (array $call): bool => $call['status'] === 0 || ($expectedPartial !== null && $call['status'] === 3 && $call['errorCode'] === 'partial-result' && $call['complete'] === false && $call['reason'] === $expectedPartial));
            self::assertNotEmpty($happyCalls, 'The happy-path case must execute its own CLI command successfully.');
        }
    }

    protected function root(): string
    {
        return dirname(__DIR__, 2);
    }

    protected function setting(string $name): string
    {
        return getenv($name) ?: ($this->settings[$name] ?? '');
    }

    private function commandName(): string
    {
        $groups = array_map(static fn (\ReflectionAttribute $attribute): string => $attribute->newInstance()->name(), (new \ReflectionClass($this))->getAttributes(Group::class));
        $commands = array_values(array_filter($groups, static fn (string $group): bool => str_starts_with($group, 'command:')));
        if (count($commands) !== 1) {
            throw new \LogicException('Each live test class must declare exactly one command group.');
        }

        return substr($commands[0], strlen('command:'));
    }

    private function isHappyPath(): bool
    {
        return array_any((new \ReflectionMethod($this, $this->name()))->getAttributes(Group::class), static fn (\ReflectionAttribute $attribute): bool => $attribute->newInstance()->name() === 'happy-path');
    }

    protected function invoke(array $argv, array $statuses = [0], ?B24Application $app = null): array
    {
        self::assertSame($this->commandName(), $argv[0], 'Fixtures and readback must use SDK helpers, not another CLI command.');
        $run = ConsoleHarness::run($app ?? $this->app, ['--json', ...array_map(strval(...), $argv)]);
        $json = $run->json();
        $this->observedCommands[] = ['command' => $argv[0], 'status' => $run->status, 'apiCalls' => $json['meta']['apiCalls'] ?? [], 'complete' => $json['meta']['complete'] ?? null, 'reason' => $json['meta']['reason'] ?? null, 'errorCode' => $json['error']['code'] ?? null, 'apiErrorCode' => $json['error']['details']['apiErrorCode'] ?? null,
            'taskStatus' => $json['data']['task']['status'] ?? null, 'confirmed' => $json['data']['operation']['confirmed'] ?? null, 'outcomeUnknown' => $json['meta']['outcomeUnknown'] ?? null];
        if ($argv[0] === 'task:add' && is_int($id = $json['data']['operation']['resourceId'] ?? null) && $id > 0) {
            $this->ownTask($id);
        }

        self::assertContains($run->status, $statuses, $run->stdout . $run->stderr);
        if ($run->status === 0) {
            self::assertNull($json['error']);
            if (array_key_exists('complete', $json['meta'])) {
                self::assertTrue($json['meta']['complete']);
            }
        }

        return $json;
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
