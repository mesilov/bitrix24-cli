<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Bootstrap\CommandCatalog;
use Bitrix24\CLI\Infrastructure\Connection\B24ClientProvider;
use Bitrix24\CLI\Infrastructure\Connection\ConfigDirectoryResolver;
use Bitrix24\CLI\Infrastructure\Connection\ConnectionManager;
use Bitrix24\CLI\Infrastructure\Connection\FileCredentialStore;
use Bitrix24\CLI\Infrastructure\Connection\FileProtection;
use Bitrix24\CLI\Infrastructure\Connection\ProfileRepository;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ConnectionProfilesTest extends TestCase
{
    private string $directory;
    private array $environment = [];
    private ProfileRepository $repository;
    private ConnectionManager $manager;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/b24cli-Конфиг space-' . bin2hex(random_bytes(6));
        foreach (['BITRIX24_WEBHOOK', 'BITRIX24_PHP_SDK_PLAYGROUND_WEBHOOK', 'B24CLI_ENV_FILE', 'B24CLI_PROFILE', 'B24CLI_CONFIG_DIR'] as $key) {
            $this->environment[$key] = [getenv($key), $_ENV[$key] ?? null];
            putenv($key);
            unset($_ENV[$key]);
        }

        putenv('B24CLI_CONFIG_DIR=' . $this->directory);
        $fileProtection = new FileProtection();
        $fileCredentialStore = new FileCredentialStore($fileProtection);
        $this->repository = new ProfileRepository($fileProtection, $fileCredentialStore);
        $this->manager = new ConnectionManager(new ConfigDirectoryResolver(), $this->repository, $fileCredentialStore, dirname(__DIR__, 2), true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }

            rmdir($this->directory);
        }

        foreach ($this->environment as $key => [$process, $value]) {
            putenv($process === false ? $key : $key . '=' . $process);
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
    }

    private function add(string $alias = 'work', array $task = []): array
    {
        return $this->repository->add($this->directory, $alias, 'https://' . $alias . '.invalid/rest/1/RecognizableFixtureSecret/', 'descriptive.invalid', $task);
    }

    private function app(): \Bitrix24\CLI\Console\B24Application
    {
        return (new ApplicationFactory())->create(dirname(__DIR__, 2), new FakeApiTransport(static fn () => throw new \LogicException('Unexpected remote call.')), packaged: true);
    }

    public function testPlatformPathsAndOverrides(): void
    {
        $configDirectoryResolver = new ConfigDirectoryResolver();
        self::assertSame('/xdg/b24cli', $configDirectoryResolver->resolve(null, ['HOME' => '/home/u', 'XDG_CONFIG_HOME' => '/xdg'], 'Linux'));
        self::assertSame('/home/u/.config/b24cli', $configDirectoryResolver->resolve(null, ['HOME' => '/home/u', 'XDG_CONFIG_HOME' => 'relative'], 'Darwin'));
        self::assertSame('/cwd/Конфиг space', $configDirectoryResolver->resolve('Конфиг space', [], 'Linux', '/cwd'));
        self::assertSame('C:\\Users\\u\\AppData\\Roaming' . DIRECTORY_SEPARATOR . 'b24cli', $configDirectoryResolver->resolve(null, ['APPDATA' => 'C:\\Users\\u\\AppData\\Roaming'], 'Windows'));
        self::assertSame('/explicit', $configDirectoryResolver->resolve('/explicit', ['B24CLI_CONFIG_DIR' => '/ambient'], 'Linux'));
        foreach (['Windows', 'Linux'] as $os) {
            try {
                $configDirectoryResolver->resolve(null, [], $os);
                self::fail('Expected absent home error.');
            } catch (Failure $failure) {
                self::assertSame('configuration-error', $failure->errorCode);
            }
        }
    }

    public function testFourProfileLifecycleAndPrivateFiles(): void
    {
        foreach (['work', 'pet', 'clientA', 'clientB'] as $alias) {
            $this->add($alias);
        }

        $config = $this->repository->load($this->directory);
        self::assertNull($config['defaultProfile']);
        self::assertCount(4, array_unique(array_column(array_column($config['profiles'], 'connection'), 'credentialFile')));
        $before = file_get_contents($this->directory . '/' . $config['profiles']['work']['connection']['credentialFile']);
        $this->repository->use($this->directory, 'work');
        self::assertSame($before, file_get_contents($this->directory . '/' . $config['profiles']['work']['connection']['credentialFile']));
        $this->repository->remove($this->directory, 'work');
        self::assertNull($this->repository->load($this->directory)['defaultProfile']);
        self::assertCount(3, $this->repository->load($this->directory)['profiles']);
        self::assertStringNotContainsString('RecognizableFixtureSecret', file_get_contents($this->directory . '/config.json'));
        if (PHP_OS_FAMILY !== 'Windows') {
            self::assertSame(0700, fileperms($this->directory) & 0777);
            self::assertSame(0600, fileperms($this->directory . '/config.json') & 0777);
            foreach (glob($this->directory . '/credentials/*') as $file) {
                self::assertSame(0600, fileperms($file) & 0777);
            }
        }
    }

    public function testSelectorsNeverMixWebhooksAndProviderResetsBetweenInvocations(): void
    {
        $this->add();
        $this->add('pet');
        $this->repository->use($this->directory, 'work');
        putenv('BITRIX24_WEBHOOK=https://ambient.invalid/rest/9/AmbientSecret/');
        $this->manager->configure('work', null, null);
        self::assertSame('https://work.invalid/rest/1/RecognizableFixtureSecret/', $this->manager->webhook());
        $runtimeState = new RuntimeState();
        $b24ClientProvider = new B24ClientProvider($this->manager, $runtimeState, new MockHttpClient(new MockResponse('{}')));
        $first = $b24ClientProvider->get();
        self::assertSame($first, $b24ClientProvider->get());
        $this->manager->configure('pet', null, null);
        $runtimeState->begin(30);
        self::assertNotSame($first, $b24ClientProvider->get());
        $this->manager->configure(null, null, null);
        self::assertSame('process-env', $this->manager->metadata()['source']);
        self::assertSame([], $this->manager->defaults(false));
        putenv('B24CLI_PROFILE=pet');
        putenv('B24CLI_ENV_FILE=/missing');
        $this->manager->configure(null, null, null);
        $this->expectException(Failure::class);
        $this->manager->webhook();
    }

    public function testOfflineDefaultPriorityByPresenceAndNoCredentialReads(): void
    {
        $profile = $this->add('work', ['groupId' => 42, 'creatorId' => 1, 'responsibleId' => 2, 'allowsTimeTracking' => true]);
        unlink($this->directory . '/' . $profile['connection']['credentialFile']);
        $app = $this->app();
        $run = ConsoleHarness::run($app, ['--profile', 'work', 'task:add', '--title', 'Fixture', '--dry-run', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        $fields = $run->json()['data']['plan']['steps'][0]['fields'];
        self::assertSame(['title' => 'Fixture', 'groupId' => 42, 'creatorId' => 1, 'responsibleId' => 2, 'allowsTimeTracking' => true], $fields);
        self::assertSame('profile-default', $run->json()['meta']['fieldSources']['groupId']);
        $run = ConsoleHarness::run($app, ['task:add', '--profile', 'work', '--title', 'Explicit', '--group-id', '77', '--creator', '3', '--responsible', '4', '--dry-run', '--json']);
        self::assertSame(0, $run->status);
        self::assertSame(77, $run->json()['data']['plan']['steps'][0]['fields']['groupId']);
        // Basic advanced validation has a documented remote metadata preflight; test the mapper independently here.
        $inputSourceReader = new \Bitrix24\CLI\Console\InputSourceReader();
        $taskInputMapper = new \Bitrix24\CLI\Console\TaskInputMapper($inputSourceReader, $this->manager);
        $this->manager->configure('work', null, null);
        $arrayInput = new \Symfony\Component\Console\Input\ArrayInput(['command' => 'task:add', '--fields' => '{"title":"Advanced","allowsTimeTracking":false}', '--dry-run' => true], $app->find('task:add')->getDefinition());
        self::assertFalse($taskInputMapper->addTask($arrayInput)->fields['allowsTimeTracking']);
        self::assertSame('explicit', $this->manager->fieldSources['allowsTimeTracking']);
        self::assertSame([], $run->json()['meta']['apiCalls']);
    }

    public function testLocalCommandsAreRegisteredAndHaveSafeOutputInAllModes(): void
    {
        $app = $this->app();
        self::assertSame(array_keys(CommandCatalog::LOCAL_COMMANDS), array_keys($app->all('connection')));
        foreach (['--json', '--plain', '--no-ansi'] as $mode) {
            $alias = 'profile' . substr($mode, 2);
            $run = ConsoleHarness::run($app, ['connection:add', $alias, '--webhook-stdin', '--group-id', '42', $mode], "https://fixture.invalid/rest/1/RecognizableFixtureSecret/\r\n");
            self::assertSame(0, $run->status, $run->stdout . $run->stderr);
            self::assertStringNotContainsString('RecognizableFixtureSecret', $run->stdout . $run->stderr);
            foreach (['connection:list', 'connection:show', 'connection:use', 'connection:remove'] as $command) {
                $run = ConsoleHarness::run($app, [$command, ...($command === 'connection:list' ? [] : [$alias]), $mode]);
                self::assertSame(0, $run->status, $run->stdout . $run->stderr);
                self::assertStringNotContainsString('RecognizableFixtureSecret', $run->stdout . $run->stderr);
            }
        }
    }

    public function testInvalidInputAndSelectorFailuresAreSafeAndDoNotWrite(): void
    {
        $app = $this->app();
        foreach ([['connection:add', 'work'], ['connection:add', 'work', '--webhook-stdin', '--config', '-'], ['connection:add', 'work', '--webhook-stdin', '--allows-time-tracking', 'Y'], ['task:add', '--profile', 'work', '--env-file', '/missing'], ['task:add', '--profile', 'missing']] as $argv) {
            $run = ConsoleHarness::run($app, [...$argv, '--json'], 'https://fixture.invalid/rest/1/RecognizableFixtureSecret/');
            self::assertNotSame(0, $run->status, $run->stdout);
            self::assertStringNotContainsString('RecognizableFixtureSecret', $run->stdout . $run->stderr);
            self::assertSame([], $run->json()['meta']['apiCalls']);
        }

        self::assertDirectoryDoesNotExist($this->directory);
    }

    public function testMalformedSchemaNeverMutatesAndRejectsObjectArraysIdsBooleansAndTraversal(): void
    {
        $this->add();
        $original = $this->repository->load($this->directory);
        $invalid = [];
        foreach ([null, 0, -1, '42', true, 1.5] as $value) {
            $copy = $original;
            $copy['profiles']['work']['default']['task']['groupId'] = $value;
            $invalid[] = $copy;
        }

        foreach ([null, 'false', 0, 1] as $value) {
            $copy = $original;
            $copy['profiles']['work']['default']['task']['allowsTimeTracking'] = $value;
            $invalid[] = $copy;
        }

        foreach (['name' => '', 'connection' => [], 'default' => [], 'groupId' => 42] as $field => $value) {
            $copy = $original;
            $copy['profiles']['work'][$field] = $value;
            $invalid[] = $copy;
        }

        foreach (['../secret.env', '/secret.env', 'credentials/link.env'] as $value) {
            $copy = $original;
            $copy['profiles']['work']['connection']['credentialFile'] = $value;
            $invalid[] = $copy;
        }

        foreach ($invalid as $copy) {
            $bytes = json_encode($copy, JSON_THROW_ON_ERROR);
            file_put_contents($this->directory . '/config.json', $bytes);
            try {
                $this->repository->load($this->directory);
                self::fail('Expected malformed metadata to fail.');
            } catch (Failure $error) {
                self::assertSame('configuration-error', $error->errorCode);
                self::assertSame($bytes, file_get_contents($this->directory . '/config.json'));
            }
        }
    }

    public function testHostMismatchAndPrivatePermissionFailureDoNotFallBack(): void
    {
        $profile = $this->add();
        $path = $this->directory . '/' . $profile['connection']['credentialFile'];
        file_put_contents($path, "BITRIX24_WEBHOOK=https://different.invalid/rest/1/RecognizableFixtureSecret/\n");
        putenv('BITRIX24_WEBHOOK=https://ambient.invalid/rest/1/AmbientSecret/');
        $this->manager->configure('work', null, null);
        try {
            $this->manager->webhook();
            self::fail('Expected mismatch.');
        } catch (Failure $failure) {
            self::assertStringContainsString('hostname', $failure->getMessage());
            self::assertStringNotContainsString('RecognizableFixtureSecret', $failure->getMessage());
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($path, 0644);
            $this->expectException(Failure::class);
            $this->manager->webhook();
        }
    }

    public function testRuntimeSelectorsAndFailurePrecedence(): void
    {
        $this->add('work', ['creatorId' => 1, 'responsibleId' => 2]);
        $this->add('pet', ['creatorId' => 3, 'responsibleId' => 4]);
        $app = $this->app();
        foreach ([['--config', '-', 'task:add', '--title', 'Fixture'], ['task:add', '--title', 'Fixture', '--config', '-', '--profile', 'pet']] as $argv) {
            $run = ConsoleHarness::run($app, [...$argv, '--dry-run', '--json'], '{"profile":"work"}');
            self::assertSame(0, $run->status, $run->stdout);
            self::assertSame(in_array('pet', $argv, true) ? 'pet' : 'work', $run->json()['meta']['connection']['profile']);
        }

        $run = ConsoleHarness::run($app, ['task:list', '--config', '-', '--env-file', '/missing', '--json'], '{"profile":"work"}');
        self::assertSame(2, $run->status);
        self::assertSame([], $run->json()['meta']['apiCalls']);
        foreach ([0, 'false', null] as $tracking) {
            $run = ConsoleHarness::run($app, ['task:add', '--profile', 'work', '--fields', json_encode(['title' => 'Fixture', 'allowsTimeTracking' => $tracking], JSON_THROW_ON_ERROR), '--json']);
            self::assertSame(2, $run->status);
            self::assertSame([], $run->json()['meta']['apiCalls']);
        }
    }

    public function testExplicitFilesSdkAliasAndPackagedSourceIsolation(): void
    {
        $this->add();
        $this->repository->use($this->directory, 'work');
        $file = $this->directory . '/external.env';
        file_put_contents($file, "BITRIX24_WEBHOOK=https://file.invalid/rest/1/RecognizableFixtureSecret/\r\n");
        putenv('BITRIX24_PHP_SDK_PLAYGROUND_WEBHOOK=https://sdk.invalid/rest/1/SdkSecret/');
        $this->manager->configure(null, $file, null);
        self::assertSame('https://file.invalid/rest/1/RecognizableFixtureSecret/', $this->manager->webhook());
        $this->manager->configure(null, null, null);
        self::assertSame('https://sdk.invalid/rest/1/SdkSecret/', $this->manager->webhook());
        putenv('BITRIX24_PHP_SDK_PLAYGROUND_WEBHOOK');
        $this->repository->remove($this->directory, 'work');
        file_put_contents($this->directory . '/.env', "BITRIX24_WEBHOOK=https://untrusted.invalid/rest/1/UntrustedSecret/\n");
        $this->manager->configure(null, null, null);
        self::assertSame('none', $this->manager->metadata()['source']);
        foreach (['BITRIX24_WEBHOOK=$(touch unexpected)', 'BITRIX24_WEBHOOK=${BITRIX24_WEBHOOK}', 'not dotenv'] as $content) {
            file_put_contents($file, $content);
            $this->manager->configure(null, $file, null);
            try {
                $this->manager->webhook();
                self::fail('Expected literal dotenv failure.');
            } catch (Failure $error) {
                self::assertSame('configuration-error', $error->errorCode);
            }
        }
    }

    public function testMissingRequiredFieldsAndInvalidExplicitValuesNeverUseFallback(): void
    {
        $this->add('work', ['creatorId' => 1, 'responsibleId' => 2, 'groupId' => 42]);
        $this->add('pet');
        $app = $this->app();
        foreach ([
            ['--profile', 'pet', '--title', 'Missing participants'],
            ['--profile', 'work'],
            ['--profile', 'work', '--title', 'Bad group', '--group-id', '0'],
            ['--profile', 'work', '--fields', '{"title":"Bad creator","creatorId":null}'],
        ] as $options) {
            $run = ConsoleHarness::run($app, ['task:add', ...$options, '--dry-run', '--json']);
            self::assertSame(2, $run->status, $run->stdout);
            self::assertSame([], $run->json()['meta']['apiCalls']);
        }

        $run = ConsoleHarness::run($app, ['task:add', '--env-file', '/unopened-secret.env', '--title', 'Isolated', '--creator', '3', '--responsible', '4', '--dry-run', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        $fields = $run->json()['data']['plan']['steps'][0]['fields'];
        self::assertArrayNotHasKey('groupId', $fields);
        self::assertFalse($fields['allowsTimeTracking']);
        self::assertSame('built-in-default', $run->json()['meta']['fieldSources']['allowsTimeTracking']);
    }

    public function testRawWebhookControlCharactersAreRejectedWithoutDisclosure(): void
    {
        $app = $this->app();
        $fileProtection = new FileProtection();
        $fileProtection->directory($this->directory);
        foreach (["\t", "\0", "\n", "\r", ' ', "\x7f"] as $control) {
            $webhook = 'https://fixture.invalid/rest/1/RecognizableFixtureSecret' . $control . 'Test/';
            $run = ConsoleHarness::run($app, ['connection:add', 'work', '--webhook-stdin', '--json'], $webhook);
            self::assertSame(1, $run->status, $run->stdout);
            self::assertStringNotContainsString('RecognizableFixtureSecret', $run->stdout . $run->stderr);
            self::assertFileDoesNotExist($this->directory . '/config.json');
            $file = $this->directory . '/external.env';
            file_put_contents($file, "BITRIX24_WEBHOOK='" . $webhook . "'\n");
            $this->manager->configure(null, $file, null);
            try {
                $this->manager->webhook();
                self::fail('Expected malformed dotenv URL to fail.');
            } catch (Failure $error) {
                self::assertSame('configuration-error', $error->errorCode);
            }

            if ($control !== "\0") {
                putenv('BITRIX24_WEBHOOK=' . $webhook);
                $this->manager->configure(null, null, null);
                try {
                    $this->manager->webhook();
                    self::fail('Expected malformed process URL to fail.');
                } catch (Failure $error) {
                    self::assertSame('configuration-error', $error->errorCode);
                }
            }
        }
    }

    public function testEnvironmentSourcesRejectRemoteAndVirtualStreamsBeforeReading(): void
    {
        $fileProtection = new FileProtection();
        $fileProtection->directory($this->directory);

        $file = $this->directory . '/external.env';
        $webhook = 'https://fixture.invalid/rest/1/RecognizableFixtureSecret/';
        file_put_contents($file, "BITRIX24_WEBHOOK=" . $webhook . "\n");
        self::assertSame($webhook, FileCredentialStore::environmentFile($file));
        self::assertSame($webhook, FileCredentialStore::environmentFile('file://' . $file));
        $app = $this->app();
        foreach (['https://fixture.invalid/credential.env', 'ftp://fixture.invalid/credential.env', 'php://filter/resource=' . $file,
            'data://text/plain;base64,' . base64_encode("BITRIX24_WEBHOOK=" . $webhook . "\n")] as $path) {
            foreach ([true, false] as $explicit) {
                putenv('B24CLI_ENV_FILE');
                if (!$explicit) {
                    putenv('B24CLI_ENV_FILE=' . $path);
                }

                $run = ConsoleHarness::run($app, ['task:add', '--title', 'Offline', '--creator', '1', '--responsible', '2', '--dry-run', '--json', ...($explicit ? ['--env-file', $path] : [])]);
                self::assertSame(1, $run->status, $run->stdout);
                self::assertSame('configuration-error', $run->json()['error']['code']);
                self::assertSame([], $run->json()['meta']['apiCalls']);
                self::assertStringNotContainsString('RecognizableFixtureSecret', $run->stdout . $run->stderr);
                try {
                    FileCredentialStore::environmentFile($path);
                    self::fail('Expected a non-filesystem credential source to fail.');
                } catch (Failure $error) {
                    self::assertSame('configuration-error', $error->errorCode);
                }
            }
        }
    }

    public function testSourceDotenvIsOnlyTheFinalProviderAndNeverUsedByPackagedMode(): void
    {
        $this->add();
        file_put_contents($this->directory . '/.env', "BITRIX24_WEBHOOK=https://source.invalid/rest/1/SourceSecret/\n");
        $fileProtection = new FileProtection();
        $fileCredentialStore = new FileCredentialStore($fileProtection);
        $connectionManager = new ConnectionManager(new ConfigDirectoryResolver(), $this->repository, $fileCredentialStore, $this->directory, false);
        self::assertSame('https://source.invalid/rest/1/SourceSecret/', $connectionManager->webhook());
        file_put_contents($this->directory . '/.env.local', "BITRIX24_WEBHOOK=https://local.invalid/rest/1/LocalSecret/\n");
        $connectionManager->configure(null, null, null);
        self::assertSame('https://local.invalid/rest/1/LocalSecret/', $connectionManager->webhook());
        $this->repository->use($this->directory, 'work');
        $connectionManager->configure(null, null, null);
        self::assertSame('https://work.invalid/rest/1/RecognizableFixtureSecret/', $connectionManager->webhook());
        $this->repository->remove($this->directory, 'work');
        $this->manager->configure(null, null, null);
        self::assertSame('none', $this->manager->metadata()['source']);
    }

    public function testPublicationFailureCleansNewCredentialAndPreservesMetadata(): void
    {
        if (PHP_OS_FAMILY === 'Windows' || (function_exists('posix_geteuid') && posix_geteuid() === 0)) {
            self::markTestSkipped('Unix unprivileged permission fault fixture.');
        }

        $this->add();
        $before = file_get_contents($this->directory . '/config.json');
        $fileCredentialStore = new FileCredentialStore(new FileProtection());
        $store = new readonly class ($fileCredentialStore) implements \Bitrix24\CLI\Infrastructure\Connection\CredentialStore {
            public function __construct(private FileCredentialStore $real)
            {
            }
            public function read(string $directory, string $reference): string
            {
                return $this->real->read($directory, $reference);
            }
            public function write(string $directory, string $webhook): string
            {
                $reference = $this->real->write($directory, $webhook);
                chmod($directory, 0500);
                return $reference;
            }
            public function delete(string $directory, string $reference): void
            {
                $this->real->delete($directory, $reference);
            }
        };
        try {
            (new ProfileRepository(new FileProtection(), $store))->add($this->directory, 'pet', 'https://pet.invalid/rest/1/RecognizableFixtureSecret/', null, []);
            self::fail('Expected protected publication failure.');
        } catch (Failure) {
            self::assertSame($before, file_get_contents($this->directory . '/config.json'));
            self::assertCount(1, glob($this->directory . '/credentials/*.env'));
        } finally {
            chmod($this->directory, 0700);
        }
    }

    public function testSymlinkCredentialsAreRejected(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('Windows symlink creation may require elevation.');
        }

        $profile = $this->add();
        $path = $this->directory . '/' . $profile['connection']['credentialFile'];
        rename($path, $this->directory . '/target.env');
        symlink($this->directory . '/target.env', $path);
        $this->manager->configure('work', null, null);
        $this->expectException(Failure::class);
        $this->manager->webhook();
    }
}
