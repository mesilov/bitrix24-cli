<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Connection\ScopeManifest;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Bootstrap\CommandCatalog;
use Bitrix24\CLI\Infrastructure\Connection\FileProtection;
use Bitrix24\CLI\Infrastructure\Connection\ScopeStateStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class ScopeManifestAndStateTest extends TestCase
{
    private string $directory;
    private ScopeStateStore $store;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/b24cli-scope-store-' . bin2hex(random_bytes(6));
        $this->store = new ScopeStateStore(new FileProtection());
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testManifestCoversEveryCatalogRouteAndConditionalRequirements(): void
    {
        $scopeManifest = new ScopeManifest();
        foreach (CommandCatalog::ROUTES as $routes) {
            $scopeManifest->required(array_map(static fn (array $route): Route => new Route($route[0], $route[1]), $routes));
        }

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $scopeManifest->revision());
        $task = [new Route('tasks.task.file.attach', 3)];
        self::assertSame([], $scopeManifest->missing($scopeManifest->required($task), ['task']));
        self::assertSame([['disk']], $scopeManifest->missing($scopeManifest->required([...$task, new Route('disk.folder.uploadFile', 1)]), ['task']));
        self::assertSame([['im']], $scopeManifest->missing($scopeManifest->required([new Route('im.message.delete', 1)]), ['task', 'crm']));
        self::assertSame([], $scopeManifest->missing($scopeManifest->required([new Route('user.get', 1)]), ['user_brief']));
        self::assertSame([], $scopeManifest->missing($scopeManifest->required($task), ['tasks']));
        self::assertArrayHasKey('task:chat:list', $scopeManifest->affectedCommands(['task']));
        self::assertArrayNotHasKey('task:list', $scopeManifest->affectedCommands(['task']));
        $this->expectException(Failure::class);
        $scopeManifest->required([new Route('tasks.task.futureMethod', 3)]);
    }

    public function testStateWritesPrivateSnapshotsWithoutCreatingConfig(): void
    {
        self::assertSame(['version' => 1, 'connections' => []], $this->store->read($this->directory));
        self::assertDirectoryDoesNotExist($this->directory);
        $key = str_repeat('a', 64);
        $this->write($key);
        self::assertSame(['task'], $this->store->read($this->directory)['connections'][$key]['grantedScopes']);
        self::assertFileDoesNotExist($this->directory . '/config.json');
        self::assertDirectoryDoesNotExist($this->directory . '/credentials');
        if (PHP_OS_FAMILY !== 'Windows') {
            self::assertSame(0, fileperms($this->directory . '/state.json') & 0077);
        }

        $before = file_get_contents($this->directory . '/state.json');
        $this->store->transaction($this->directory, static fn (array $state): array => $state);
        self::assertSame($before, file_get_contents($this->directory . '/state.json'));
    }

    public function testMalformedStateIsPreserved(): void
    {
        (new FileProtection())->directory($this->directory);
        foreach (['{broken', '{"version":2,"connections":{}}', '{"version":1,"connections":[]}', '{"version":1,"connections":{},"extra":1}'] as $raw) {
            file_put_contents($this->directory . '/state.json', $raw);
            chmod($this->directory . '/state.json', 0600);
            try {
                $this->write(str_repeat('b', 64));
                self::fail('Expected malformed state rejection.');
            } catch (Failure) {
                self::assertSame($raw, file_get_contents($this->directory . '/state.json'));
            }
        }
    }

    public function testUnsafeStateAndSymlinksAreNotReadOrReplaced(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('Unix modes and symlinks; Windows uses FileProtection ACL checks.');
        }

        $key = str_repeat('a', 64);
        $this->write($key);
        $raw = file_get_contents($this->directory . '/state.json');
        chmod($this->directory . '/state.json', 0644);
        try {
            $this->store->read($this->directory);
            self::fail('Expected private permission rejection.');
        } catch (Failure) {
            self::assertSame($raw, file_get_contents($this->directory . '/state.json'));
        }

        chmod($this->directory . '/state.json', 0600);
        rename($this->directory . '/state.json', $this->directory . '/target');
        symlink($this->directory . '/target', $this->directory . '/state.json');
        try {
            $this->write($key);
            self::fail('Expected symlink rejection.');
        } catch (Failure) {
            self::assertSame($raw, file_get_contents($this->directory . '/target'));
            self::assertTrue(is_link($this->directory . '/state.json'));
        }
    }

    public function testInvalidSnapshotTypesAndDatesAreRejectedWithoutMutation(): void
    {
        $key = str_repeat('a', 64);
        $this->write($key);
        $original = $this->store->read($this->directory);
        foreach ([['grantedScopes', (object) []], ['grantedScopes', [1]], ['status', 'ready'], ['lastSuccessfulCheckAt', '2026-02-30T00:00:00Z'], ['nextCheckAt', null], ['scopeManifestRevision', 'bad'], ['checkedWithAppVersion', 3]] as [$field, $value]) {
            $invalid = $original;
            $invalid['connections'][$key][$field] = $value;
            $raw = json_encode($invalid, JSON_THROW_ON_ERROR);
            file_put_contents($this->directory . '/state.json', $raw);
            try {
                $this->store->read($this->directory);
                self::fail('Expected invalid snapshot rejection for ' . $field);
            } catch (Failure) {
                self::assertSame($raw, file_get_contents($this->directory . '/state.json'));
            }
        }
    }

    public function testConcurrentWritersPreserveBothConnections(): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('Process concurrency requires pcntl.');
        }

        $pid = pcntl_fork();
        self::assertNotSame(-1, $pid);
        if ($pid === 0) {
            $this->write(str_repeat('c', 64));
            exit(0);
        }

        $this->write(str_repeat('d', 64));
        pcntl_waitpid($pid, $status);
        self::assertSame(0, pcntl_wexitstatus($status));
        self::assertCount(2, $this->store->read($this->directory)['connections']);
    }

    private function write(string $key): void
    {
        $this->store->transaction($this->directory, static function (array &$state) use ($key): array {
            $state['connections'][$key] = [
                'grantedScopes' => ['task'], 'lastSuccessfulCheckAt' => '2026-10-15T00:00:00Z',
                'checkedWithAppVersion' => '0.3.0', 'scopeManifestRevision' => str_repeat('e', 64),
                'lastAttemptAt' => '2026-10-15T00:00:00Z', 'nextCheckAt' => '2026-10-16T00:00:00Z', 'status' => 'fresh',
            ];
            return $state;
        });
    }
}
