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
use Bitrix24\CLI\Tests\Support\CapturedOutput;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Console\Input\ArgvInput;

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

    public function testRoleIdentityFollowsTheSelectedConnectionRatherThanTaskDefaults(): void
    {
        $this->repository->add($this->directory, 'work', 'https://work.invalid/rest/7/RecognizableFixtureSecret/', null, ['creatorId' => 9, 'responsibleId' => 9]);
        $this->repository->use($this->directory, 'work');
        putenv('BITRIX24_WEBHOOK=https://ambient.invalid/rest/2/AmbientFixtureSecret/');
        $path = $this->directory . '/explicit.env';
        file_put_contents($path, "BITRIX24_WEBHOOK=https://file.invalid/rest/8/FileFixtureSecret/\n");
        $fakeApiTransport = new FakeApiTransport(static fn (): \Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse => new \Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse(['items' => array_map(static fn (int $id): array => ['id' => $id, 'title' => 'task', 'responsibleId' => $id], [2, 7, 8, 9])]));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, packaged: true);
        foreach ([[['--profile', 'work'], 7], [['--env-file', $path], 8], [[], 2]] as [$options, $id]) {
            $run = ConsoleHarness::run($app, ['--json', 'task:list', '--role', 'responsible', ...$options]);
            self::assertSame(0, $run->status, $run->stdout);
            self::assertSame(['role' => 'responsible', 'userId' => $id], $run->json()['meta']['roleFilter']);
            self::assertSame([$id], array_column($run->json()['data']['items'], 'id'));
            foreach (['RecognizableFixtureSecret', 'AmbientFixtureSecret', 'FileFixtureSecret'] as $secret) {
                self::assertStringNotContainsString($secret, $run->stdout . $run->stderr);
            }
        }

        putenv('BITRIX24_WEBHOOK');
        $run = ConsoleHarness::run($app, ['--json', 'task:list', '--role', 'responsible']);
        self::assertSame(7, $run->json()['meta']['roleFilter']['userId']);
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
        $connectionNames = array_values(array_filter(array_keys(CommandCatalog::LOCAL_COMMANDS), static fn (string $name): bool => str_starts_with($name, 'connection:')));
        self::assertSame($connectionNames, array_keys($app->all('connection')));
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

    public function testDuplicateAddRejectsBeforeWebhookInputAndPreservesFiles(): void
    {
        $work = $this->add('work', ['groupId' => 187, 'responsibleId' => 2, 'allowsTimeTracking' => false]);
        $pet = $this->add('pet');
        $this->repository->use($this->directory, 'work');
        $config = file_get_contents($this->directory . '/config.json');
        $credentials = [];
        foreach ([$work, $pet] as $profile) {
            $path = $this->directory . '/' . $profile['connection']['credentialFile'];
            $credentials[$path] = file_get_contents($path);
        }

        $app = $this->app();
        foreach (['--no-ansi', '--plain', '--json'] as $mode) {
            foreach ([false, true] as $stdin) {
                $input = new ArgvInput(['b24cli', 'connection:add', 'work', '--creator-id', '3', '--group-id', '99', '--responsible-id', '4', '--allows-time-tracking', 'true', '-n', $mode, ...($stdin ? ['--webhook-stdin'] : [])]);
                $input->setInteractive(false);
                $stream = fopen('php://memory', 'r+');
                fwrite($stream, 'https://replacement.invalid/rest/1/ReplacementFixtureSecret/');
                rewind($stream);
                $input->setStream($stream);
                $output = new CapturedOutput();
                try {
                    self::assertSame(1, $app->run($input, $output));
                    self::assertSame(0, ftell($stream), 'Duplicate add must not consume webhook stdin.');
                } finally {
                    fclose($stream);
                }

                $stdout = $output->fetch();
                $stderr = $output->getErrorOutput()->fetch();
                $combined = $stdout . $stderr;
                self::assertStringContainsString('configuration-error', $combined);
                self::assertStringContainsString('Parameters were not applied', $combined);
                foreach (['show', 'remove', 'add', 'use'] as $command) {
                    self::assertStringContainsString('connection:' . $command . ' work', $combined);
                }

                self::assertStringNotContainsString('HTTPS incoming webhook:', $combined);
                self::assertStringNotContainsString('RecognizableFixtureSecret', $combined);
                self::assertStringNotContainsString('ReplacementFixtureSecret', $combined);
                if ($mode === '--json') {
                    $json = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
                    self::assertNull($json['data']);
                    self::assertSame('configuration-error', $json['error']['code']);
                    self::assertSame([], $json['meta']['apiCalls']);
                } else {
                    self::assertSame('', $stdout);
                }

                self::assertSame($config, file_get_contents($this->directory . '/config.json'));
                self::assertEqualsCanonicalizing(array_keys($credentials), glob($this->directory . '/credentials/*.env'));
                foreach ($credentials as $path => $bytes) {
                    self::assertSame($bytes, file_get_contents($path));
                }
            }
        }

        // Missing credential must not hide the metadata-only duplicate diagnostic.
        unlink($this->directory . '/' . $work['connection']['credentialFile']);
        $run = ConsoleHarness::run($app, ['connection:add', 'work', '--json']);
        self::assertSame(1, $run->status);
        self::assertStringContainsString('already exists', $run->json()['error']['message']);
    }

    public function testRepositoryDuplicateUsesTheSameDiagnosticAndCreatesNoCredential(): void
    {
        $this->add();
        $before = file_get_contents($this->directory . '/config.json');
        $messages = [];
        foreach (['preflight', 'locked-add'] as $operation) {
            try {
                if ($operation === 'preflight') {
                    $this->repository->assertCanAdd($this->directory, 'work');
                } else {
                    $this->add('work', ['creatorId' => 3]);
                }

                self::fail('Expected duplicate rejection.');
            } catch (Failure $failure) {
                self::assertSame('configuration-error', $failure->errorCode);
                $messages[] = $failure->getMessage();
            }
        }

        self::assertSame($messages[0], $messages[1]);
        self::assertSame($before, file_get_contents($this->directory . '/config.json'));
        self::assertCount(1, glob($this->directory . '/credentials/*.env'));
    }

    public function testHelpExplainsReplacementWithoutConfigurationOrCredentials(): void
    {
        $run = ConsoleHarness::run($this->app(), ['help', 'connection:add', '--no-ansi']);
        self::assertSame(0, $run->status);
        foreach (['Создаёт только новый профиль', 'переданные параметры не применяются', 'connection:show NAME', 'connection:remove NAME', 'connection:add NAME', 'connection:use NAME', 'не переносятся', 'webhook', 'defaultProfile', 'не отзывается'] as $text) {
            self::assertStringContainsString($text, $run->stdout);
        }

        self::assertDirectoryDoesNotExist($this->directory);
    }

    public function testDocumentedReplacementRestoresDefaultsAndSelectedProfile(): void
    {
        $work = $this->add('work', ['groupId' => 187, 'responsibleId' => 2]);
        $pet = $this->add('pet', ['creatorId' => 8]);
        $this->repository->use($this->directory, 'work');
        $petPath = $this->directory . '/' . $pet['connection']['credentialFile'];
        $petBytes = file_get_contents($petPath);
        $app = $this->app();
        $run = ConsoleHarness::run($app, ['connection:show', 'work', '--json']);
        self::assertSame(0, $run->status);
        self::assertArrayNotHasKey('creatorId', $run->json()['data']['items'][0]['default']['task']);
        $run = ConsoleHarness::run($app, ['connection:remove', 'work', '--json']);
        self::assertSame(0, $run->status);
        self::assertFileDoesNotExist($this->directory . '/' . $work['connection']['credentialFile']);
        self::assertNull($this->repository->load($this->directory)['defaultProfile']);
        $run = ConsoleHarness::run($app, ['connection:add', 'work', '--group-id', '187', '--creator-id', '3', '--responsible-id', '2', '--allows-time-tracking', 'true', '--webhook-stdin', '-n', '--json'], 'https://work.invalid/rest/1/RecognizableFixtureSecret/');
        self::assertSame(0, $run->status, $run->stdout);
        self::assertNull($this->repository->load($this->directory)['defaultProfile']);
        $run = ConsoleHarness::run($app, ['connection:show', 'work', '--json']);
        self::assertSame(['groupId' => 187, 'creatorId' => 3, 'responsibleId' => 2, 'allowsTimeTracking' => true], $run->json()['data']['items'][0]['default']['task']);
        $run = ConsoleHarness::run($app, ['connection:use', 'work', '--json']);
        self::assertSame(0, $run->status);
        self::assertSame('work', $this->repository->load($this->directory)['defaultProfile']);
        $run = ConsoleHarness::run($app, ['task:add', '--profile', 'work', '--title', 'Test', '--dry-run', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame(['title' => 'Test', 'groupId' => 187, 'creatorId' => 3, 'responsibleId' => 2, 'allowsTimeTracking' => true], $run->json()['data']['plan']['steps'][0]['fields']);
        self::assertSame([], $run->json()['meta']['apiCalls']);
        self::assertSame($pet, $this->repository->profile($this->directory, 'pet'));
        self::assertSame($petBytes, file_get_contents($petPath));
    }

    public function testConcurrentRepositoryAddsDoNotOverwriteTheWinner(): void
    {
        $script = <<<'PHP'
require 'vendor/autoload.php';
$protection = new \Bitrix24\CLI\Infrastructure\Connection\FileProtection();
$repository = new \Bitrix24\CLI\Infrastructure\Connection\ProfileRepository($protection, new \Bitrix24\CLI\Infrastructure\Connection\FileCredentialStore($protection));
$repository->assertCanAdd($argv[1], 'work');
fwrite(STDOUT, "READY\n");
fflush(STDOUT);
fgets(STDIN);
try {
    $repository->add($argv[1], 'work', 'https://work.invalid/rest/1/RecognizableFixtureSecret/', null, ['creatorId' => (int) $argv[2]]);
    exit(0);
} catch (\Bitrix24\CLI\Application\Failure $failure) {
    fwrite(STDOUT, $failure->errorCode . ': ' . $failure->getMessage());
    exit(1);
}
PHP;
        $workers = [];
        try {
            foreach ([3, 4] as $creator) {
                $process = proc_open([PHP_BINARY, '-r', $script, $this->directory, (string) $creator], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
                self::assertIsResource($process);
                $workers[] = [$process, $pipes];
                stream_set_timeout($pipes[1], 10);
                self::assertSame("READY\n", fgets($pipes[1]));
            }

            foreach ($workers as [$process, $pipes]) {
                fwrite($pipes[0], "GO\n");
                fclose($pipes[0]);
                stream_set_timeout($pipes[1], 120);
            }

            $statuses = [];
            foreach ($workers as [$process, $pipes]) {
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                $status = proc_close($process);
                $statuses[] = $status;
                self::assertSame('', $stderr);
                if ($status === 1) {
                    self::assertStringContainsString('configuration-error', $stdout);
                    self::assertStringContainsString('Parameters were not applied', $stdout);
                }

                self::assertStringNotContainsString('RecognizableFixtureSecret', $stdout);
            }

            sort($statuses);
            self::assertSame([0, 1], $statuses);
            $config = $this->repository->load($this->directory);
            self::assertCount(1, $config['profiles']);
            self::assertContains($config['profiles']['work']['default']['task']['creatorId'], [3, 4]);
            self::assertCount(1, glob($this->directory . '/credentials/*.env'));
        } finally {
            foreach ($workers as [$process, $pipes]) {
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }

                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
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
    public function testWorkingRolesRoundTripThroughLocalCommands(): void
    {
        $app = $this->app();
        $run = ConsoleHarness::run($app, ['connection:add', 'work', '--webhook-stdin', '--observer-id', '5', '--manager-id', '9', '--auto-add-observer', 'true', '--json'], 'https://work.invalid/rest/1/RecognizableFixtureSecret/');
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        $roles = ['observerId' => 5, 'managerId' => 9, 'autoAddObserver' => true];
        self::assertSame($roles, $this->repository->profile($this->directory, 'work')['default']['users']);
        $show = ConsoleHarness::run($app, ['connection:show', 'work', '--json']);
        self::assertSame($roles, $show->json()['data']['items'][0]['default']['users']);
        self::assertSame([], $show->json()['meta']['apiCalls']);
        self::assertStringNotContainsString('RecognizableFixtureSecret', $run->stdout . $show->stdout . $show->stderr);
    }

    public function testWorkingRolesAreScopedToActualSelectedProfile(): void
    {
        $this->repository->add($this->directory, 'work', 'https://work.invalid/rest/1/RecognizableFixtureSecret/', null, [], ['observerId' => 5, 'managerId' => 9, 'autoAddObserver' => true]);
        $this->repository->use($this->directory, 'work');
        putenv('BITRIX24_WEBHOOK=https://other.invalid/rest/1/OtherFixtureSecret/');
        $this->manager->configure(null, null, null);
        self::assertSame([], $this->manager->userDefaults());
        $this->manager->configure('work', null, null);
        self::assertSame(5, $this->manager->userDefaults()['observerId']);
        $this->manager->configure(null, '/fixture.env', null);
        self::assertSame([], $this->manager->userDefaults());
    }

    public function testAutoObserverFlagsValidateBeforeSavingOrReadingStdin(): void
    {
        foreach ([['--auto-add-observer', 'true'], ['--observer-id', '0'], ['--manager-id', 'false'], ['--observer-id', '5', '--auto-add-observer', 'Y']] as $flags) {
            $run = ConsoleHarness::run($this->app(), ['connection:add', 'work', '--webhook-stdin', ...$flags, '--json'], 'https://work.invalid/rest/1/RecognizableFixtureSecret/');
            self::assertSame(2, $run->status, $run->stdout . $run->stderr);
            self::assertFileDoesNotExist($this->directory . '/config.json');
        }
    }

    public function testWorkingRolesDefaultToDisabledAndRemainIntactOnMetadataWrites(): void
    {
        $this->repository->add($this->directory, 'work', 'https://work.invalid/rest/1/RecognizableFixtureSecret/', null, [], ['observerId' => 5]);
        self::assertFalse($this->manager->metadata(true)['default']['users']['autoAddObserver'] ?? false);
        $this->repository->use($this->directory, 'work');
        self::assertSame(['observerId' => 5], $this->repository->profile($this->directory, 'work')['default']['users']);
        $this->manager->configure('work', null, null);
        self::assertFalse($this->manager->metadata(true)['default']['users']['autoAddObserver']);
    }

    public function testConfiguredManagerUsesOnlySelectedProfileAndUserLookup(): void
    {
        $this->repository->add($this->directory, 'work', 'https://work.invalid/rest/1/RecognizableFixtureSecret/', null, [], ['managerId' => 9]);
        $fakeApiTransport = new FakeApiTransport(static fn (): \Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse => new \Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse([['ID' => '9', 'NAME' => 'Manager']]));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, packaged: true);
        $before = file_get_contents($this->directory . '/config.json');
        $run = ConsoleHarness::run($app, ['--profile', 'work', 'users:manager', '--source', 'config', '--json']);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame('9', $run->json()['data']['user']['ID']);
        self::assertSame('config', $run->json()['meta']['source']);
        self::assertSame(['user.get'], array_column($fakeApiTransport->calls, 'method'));
        self::assertSame(['ID' => 9], $fakeApiTransport->calls[0]['parameters']['FILTER']);
        self::assertSame($before, file_get_contents($this->directory . '/config.json'));
        $this->add('pet');
        $missing = ConsoleHarness::run($app, ['--profile', 'pet', 'users:manager', '--source', 'config', '--json']);
        self::assertSame(1, $missing->status);
        self::assertSame('configuration-error', $missing->json()['error']['code']);
        self::assertCount(1, $fakeApiTransport->calls);
    }

}
