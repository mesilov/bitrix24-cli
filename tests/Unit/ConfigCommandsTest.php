<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Console\B24Application;
use Bitrix24\CLI\Infrastructure\Connection\ConfigKeys;
use Bitrix24\CLI\Infrastructure\Connection\FileCredentialStore;
use Bitrix24\CLI\Infrastructure\Connection\FileProtection;
use Bitrix24\CLI\Infrastructure\Connection\ProfileRepository;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\ConsoleRun;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\ProfileFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * config:* commands change documented non-secret settings locally: no credentials, portal or state.json.
 */
final class ConfigCommandsTest extends TestCase
{
    private string $directory;
    private array $environment = [];
    private ProfileRepository $repository;
    private B24Application $app;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/b24cli-config-commands-' . bin2hex(random_bytes(6));
        foreach (['BITRIX24_WEBHOOK', 'BITRIX24_PHP_SDK_PLAYGROUND_WEBHOOK', 'B24CLI_ENV_FILE', 'B24CLI_PROFILE', 'B24CLI_CONFIG_DIR', 'TZ'] as $key) {
            $this->environment[$key] = [getenv($key), $_ENV[$key] ?? null];
            putenv($key);
            unset($_ENV[$key]);
        }

        putenv('B24CLI_CONFIG_DIR=' . $this->directory);
        $fileProtection = new FileProtection();
        $this->repository = new ProfileRepository($fileProtection, new FileCredentialStore($fileProtection));
        $this->app = (new ApplicationFactory())->create(dirname(__DIR__, 2), new FakeApiTransport(static fn () => throw new \LogicException('Unexpected remote call.')), packaged: true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
        foreach ($this->environment as $key => [$process, $value]) {
            putenv($process === false ? $key : $key . '=' . $process);
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
    }

    public function testChangesOneDefaultWithoutTouchingCredentialsOrOtherValues(): void
    {
        $work = $this->profile('work', ['creatorId' => 1]);
        $this->repository->use($this->directory, 'work');
        // Proof that the secret is never opened: the command works without the credential file.
        unlink($this->directory . '/' . $work['connection']['credentialFile']);

        $run = $this->cli(['config:set', 'default.task.groupId', '187', '--profile', 'work', '--json']);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame(['key' => 'default.task.groupId', 'value' => 187, 'source' => 'profile', 'profile' => 'work'], $run->json()['data']);
        self::assertSame(['schemaVersion' => 1, 'command' => 'config:set', 'apiCalls' => [], 'apiVersions' => [], 'local' => true], $run->json()['meta']);

        $run = $this->cli(['config:get', 'default.task.groupId', '--profile', 'work', '--json']);
        self::assertSame(187, $run->json()['data']['value']);
        self::assertStringContainsString('"groupId": 187', (string) file_get_contents($this->directory . '/config.json'));
        self::assertSame(['creatorId' => 1, 'groupId' => 187], $this->repository->profile($this->directory, 'work')['default']['task']);
        self::assertSame($work['connection'], $this->repository->profile($this->directory, 'work')['connection']);
        self::assertFileDoesNotExist($this->directory . '/state.json');

        $run = $this->cli(['task:add', '--profile', 'work', '--title', 'Defaults', '--responsible', '2', '--dry-run', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertEqualsCanonicalizing(['title' => 'Defaults', 'responsibleId' => 2, 'groupId' => 187, 'creatorId' => 1, 'allowsTimeTracking' => false], $run->json()['data']['plan']['steps'][0]['fields']);
        self::assertSame(187, $run->json()['data']['plan']['steps'][0]['fields']['groupId']);
    }

    public function testOutputModesOfSingleValues(): void
    {
        $this->profile('work', ['groupId' => 42]);
        $this->repository->use($this->directory, 'work');
        self::assertSame("42\n", $this->cli(['config:get', 'default.task.groupId', '--plain'])->stdout);
        self::assertSame("false\n", $this->cli(['config:get', 'default.task.allowsTimeTracking', '--plain'])->stdout);
        self::assertSame("\n", $this->cli(['config:get', 'default.task.creatorId', '--plain'])->stdout);
        self::assertSame("info\n", $this->cli(['config:get', 'logging.level', '--plain'])->stdout);
        self::assertSame("debug\n", $this->cli(['config:set', 'logging.level', 'debug', '--plain'])->stdout);
        self::assertSame("info\n", $this->cli(['config:unset', 'logging.level', '--plain'])->stdout);
        $human = $this->cli(['config:get', 'default.task.groupId', '--no-ansi']);
        foreach (['default.task.groupId', '42', 'profile', 'work'] as $text) {
            self::assertStringContainsString($text, $human->stdout);
        }

        self::assertSame(['key' => 'logging.level', 'value' => 'info', 'source' => 'default', 'profile' => null], $this->cli(['config:get', 'logging.level', '--json'])->json()['data']);
    }

    public function testProtectedUnknownAndReadOnlyKeysAreRejectedWithoutWriting(): void
    {
        $this->profile('work');
        $this->repository->use($this->directory, 'work');
        $before = file_get_contents($this->directory . '/config.json');
        foreach ([
            [['config:set', 'defaultProfile', 'work'], 'auth:switch'],
            [['config:unset', 'defaultProfile'], 'auth:switch'],
            [['config:set', 'connection.hostname', 'x.example'], 'auth:login NAME --replace'],
            [['config:get', 'connection.credentialFile'], 'auth:login NAME --replace'],
            [['config:set', 'default.task.authorId', '3'], 'default.task.creatorId'],
            [['config:set', 'groupId', '42'], 'default.task.groupId'],
            [['config:get', 'default.task'], 'default.task.groupId'],
            [['config:set', 'default.users.observer', '5'], 'default.users.observerId'],
            [['config:set', 'logging.Level', 'info'], 'logging.level'],
            [['config:set', 'telemetry.lastProblemTrajectoryProcessingAt', '2026-11-12T00:00:00Z'], 'read-only'],
            [['config:unset', 'telemetry.lastProblemTrajectoryAnalysisOfferedAt'], 'read-only'],
        ] as [$argv, $hint]) {
            $run = $this->cli([...$argv, '--json']);
            self::assertSame(2, $run->status, implode(' ', $argv) . $run->stdout);
            self::assertSame('usage-error', $run->json()['error']['code']);
            self::assertStringContainsString($hint, $run->json()['error']['message']);
            self::assertSame($before, file_get_contents($this->directory . '/config.json'));
        }

        $run = $this->cli(['config:get', 'telemetry.lastProblemTrajectoryProcessingAt', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame(['key' => 'telemetry.lastProblemTrajectoryProcessingAt', 'value' => null, 'source' => 'default', 'profile' => null], $run->json()['data']);
        self::assertNotContains('telemetry.lastProblemTrajectoryProcessingAt', ConfigKeys::names(true));
        self::assertContains('telemetry.lastProblemTrajectoryProcessingAt', ConfigKeys::names());
    }

    public static function invalidValues(): iterable
    {
        foreach (['0', '-1', '+1', '01', '1.0', '1e0', 'abc', '', ' 1', '9223372036854775808'] as $value) {
            yield 'ID ' . $value => ['default.task.groupId', $value];
        }

        foreach (['Y', '1', '"false"', 'TRUE', 'yes', ''] as $value) {
            yield 'boolean ' . $value => ['default.task.allowsTimeTracking', $value];
        }

        yield 'observer ID' => ['default.users.observerId', '0'];
        yield 'manager ID' => ['default.users.managerId', 'false'];
        yield 'auto observer' => ['default.users.autoAddObserver', 'Y'];
        yield 'name with scheme' => ['name', 'https://portal.example'];
        yield 'name with path' => ['name', 'portal.example/'];
        yield 'level case' => ['logging.level', 'Info'];
        yield 'logging flag' => ['logging.enabled', '1'];
        yield 'empty directory' => ['logging.directory', ''];
        yield 'mask without date' => ['logging.fileMask', 'plain.log'];
        yield 'mask with path' => ['logging.fileMask', '../{YYYY}{MM}{DD}.log'];
        yield 'retention zero' => ['logging.retentionDays', '0'];
        yield 'retention float' => ['logging.retentionDays', '7.0'];
        yield 'telemetry flag' => ['telemetry.collectProblemTrajectories', 'off'];
        yield 'telemetry count' => ['telemetry.maxProblemTrajectoryFiles', '0'];
    }

    #[DataProvider('invalidValues')]
    public function testStrictValuesAreRejectedWithTheExpectedFormat(string $key, string $value): void
    {
        $this->profile('work', ['groupId' => 42], ['observerId' => 5]);
        $this->repository->use($this->directory, 'work');
        $this->repository->set($this->directory, null, 'logging.level', 'debug');

        $before = file_get_contents($this->directory . '/config.json');
        $run = $this->cli(['config:set', $key, $value, '--json']);
        self::assertSame(2, $run->status, $run->stdout);
        self::assertSame('usage-error', $run->json()['error']['code']);
        self::assertStringContainsString(str_starts_with($key, 'logging.fileMask') ? 'logging.fileMask' : $key, $run->json()['error']['message']);
        self::assertSame($before, file_get_contents($this->directory . '/config.json'));
    }

    public function testEveryKeyTypeIsStoredAsItsJsonType(): void
    {
        $this->profile('work');
        $this->repository->use($this->directory, 'work');
        $values = [
            'name' => ['client.example', 'client.example'],
            'default.task.groupId' => ['187', 187],
            'default.task.creatorId' => ['3', 3],
            'default.task.responsibleId' => [(string) PHP_INT_MAX, PHP_INT_MAX],
            'default.task.allowsTimeTracking' => ['true', true],
            'default.users.observerId' => ['5', 5],
            'default.users.managerId' => ['9', 9],
            'default.users.autoAddObserver' => ['true', true],
            'logging.enabled' => ['true', true],
            'logging.level' => ['warning', 'warning'],
            'logging.directory' => ['var/log', 'var/log'],
            'logging.fileMask' => ['cli-{YYYY}-{MM}-{DD}.jsonl', 'cli-{YYYY}-{MM}-{DD}.jsonl'],
            'logging.retentionDays' => ['30', 30],
            'telemetry.collectProblemTrajectories' => ['false', false],
            'telemetry.maxProblemTrajectoryFiles' => ['5', 5],
        ];
        self::assertSame(array_keys($values), ConfigKeys::names(true));
        foreach ($values as $key => [$input, $expected]) {
            $result = $this->repository->set($this->directory, 'work', $key, $input);
            self::assertSame($expected, $result['value'], $key);
            self::assertSame(ConfigKeys::isRoot($key) ? 'root' : 'profile', $result['source'], $key);
            self::assertSame(ConfigKeys::isRoot($key) ? null : 'work', $result['profile'], $key);
        }

        $saved = json_decode((string) file_get_contents($this->directory . '/config.json'), true, 64, JSON_THROW_ON_ERROR);
        self::assertSame('client.example', $saved['profiles']['work']['name']);
        self::assertSame(['task' => ['groupId' => 187, 'creatorId' => 3, 'responsibleId' => PHP_INT_MAX, 'allowsTimeTracking' => true], 'users' => ['observerId' => 5, 'managerId' => 9, 'autoAddObserver' => true]], $saved['profiles']['work']['default']);
        self::assertSame(['enabled' => true, 'level' => 'warning', 'directory' => 'var/log', 'fileMask' => 'cli-{YYYY}-{MM}-{DD}.jsonl', 'retentionDays' => 30], $saved['logging']);
        self::assertSame(['collectProblemTrajectories' => false, 'maxProblemTrajectoryFiles' => 5], $saved['telemetry']);
        // The written document passes the same strict reader.
        self::assertSame($saved['profiles']['work']['default'], $this->repository->load($this->directory)['profiles']['work']['default']);
    }

    public function testCrossFieldRuleOfTheDefaultObserver(): void
    {
        $this->profile('work');
        $this->repository->use($this->directory, 'work');
        $before = file_get_contents($this->directory . '/config.json');
        $run = $this->cli(['config:set', 'default.users.autoAddObserver', 'true', '--json']);
        self::assertSame(2, $run->status, $run->stdout);
        self::assertStringContainsString('default.users.observerId', $run->json()['error']['message']);
        self::assertSame($before, file_get_contents($this->directory . '/config.json'));

        self::assertSame(0, $this->cli(['config:set', 'default.users.observerId', '5'])->status);
        self::assertSame(0, $this->cli(['config:set', 'default.users.autoAddObserver', 'true'])->status);
        $before = file_get_contents($this->directory . '/config.json');
        $run = $this->cli(['config:unset', 'default.users.observerId', '--json']);
        self::assertSame(2, $run->status, $run->stdout);
        self::assertStringContainsString('autoAddObserver', $run->json()['error']['message']);
        self::assertSame($before, file_get_contents($this->directory . '/config.json'));

        self::assertSame(0, $this->cli(['config:set', 'default.users.autoAddObserver', 'false'])->status);
        self::assertSame(0, $this->cli(['config:unset', 'default.users.observerId'])->status);
        self::assertSame(['autoAddObserver' => false], $this->repository->profile($this->directory, 'work')['default']['users']);
    }

    public function testUnsetRestoresBuiltInDefaultsAndRemovesEmptyContainers(): void
    {
        $this->profile('work', ['allowsTimeTracking' => true]);
        $this->repository->use($this->directory, 'work');
        $this->repository->set($this->directory, null, 'logging.level', 'debug');
        $this->repository->set($this->directory, null, 'telemetry.maxProblemTrajectoryFiles', '5');

        $run = $this->cli(['config:unset', 'default.task.allowsTimeTracking', '--json']);
        self::assertSame(['key' => 'default.task.allowsTimeTracking', 'value' => false, 'source' => 'default', 'profile' => 'work'], $run->json()['data']);
        $run = $this->cli(['config:unset', 'logging.level', '--json']);
        self::assertSame(['key' => 'logging.level', 'value' => 'info', 'source' => 'default', 'profile' => null], $run->json()['data']);
        $run = $this->cli(['config:unset', 'telemetry.maxProblemTrajectoryFiles', '--json']);
        self::assertSame(100, $run->json()['data']['value']);

        $saved = json_decode((string) file_get_contents($this->directory . '/config.json'), true, 64, JSON_THROW_ON_ERROR);
        self::assertSame(['version', 'defaultProfile', 'profiles'], array_keys($saved));
        self::assertSame(['name', 'connection'], array_keys($saved['profiles']['work']));

        $before = file_get_contents($this->directory . '/config.json');
        $run = $this->cli(['config:unset', 'name', '--json']);
        self::assertSame(2, $run->status, $run->stdout);
        self::assertStringContainsString('required', $run->json()['error']['message']);
        // Removing an absent key changes nothing.
        self::assertSame(0, $this->cli(['config:unset', 'default.task.groupId', '--json'])->status);
        self::assertSame($before, file_get_contents($this->directory . '/config.json'));
    }

    public function testProfileSelectionIgnoresWebhooksAndEnvironmentFiles(): void
    {
        $this->profile('work');
        $this->profile('pet');
        $this->repository->use($this->directory, 'work');
        putenv('BITRIX24_WEBHOOK=https://ambient.invalid/rest/1/AmbientFixtureSecret/');
        putenv('B24CLI_ENV_FILE=' . $this->directory . '/missing.env');
        $run = $this->cli(['config:set', 'default.task.groupId', '42', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame('work', $run->json()['data']['profile']);
        self::assertSame(42, $this->repository->profile($this->directory, 'work')['default']['task']['groupId']);
        self::assertArrayNotHasKey('default', $this->repository->profile($this->directory, 'pet'));

        putenv('B24CLI_PROFILE=pet');
        self::assertSame('pet', $this->cli(['config:set', 'default.task.groupId', '7', '--json'])->json()['data']['profile']);
        self::assertSame('pet', $this->cli(['config:list', '--json'])->json()['data']['profile']);
        self::assertSame('work', $this->cli(['config:set', 'default.task.creatorId', '3', '--profile', 'work', '--json'])->json()['data']['profile']);
        self::assertSame(['groupId' => 42, 'creatorId' => 3], $this->repository->profile($this->directory, 'work')['default']['task']);
        self::assertSame(['groupId' => 7], $this->repository->profile($this->directory, 'pet')['default']['task']);

        $before = file_get_contents($this->directory . '/config.json');
        foreach ([['config:set', 'default.task.groupId', '9', '--env-file', $this->directory . '/missing.env'], ['config:list', '--env-file', '/portal.env'], ['config:set', 'logging.enabled', 'true', '--profile', 'work'], ['config:get', 'logging.level', '--profile', 'work'], ['config:unset', 'telemetry.maxProblemTrajectoryFiles', '--profile', 'work']] as $argv) {
            $run = $this->cli([...$argv, '--json']);
            self::assertSame(2, $run->status, implode(' ', $argv) . $run->stdout);
            self::assertSame('usage-error', $run->json()['error']['code']);
        }

        $run = $this->cli(['config:set', 'default.task.groupId', '9', '--profile', 'ghost', '--json']);
        self::assertSame(1, $run->status, $run->stdout);
        self::assertSame('configuration-error', $run->json()['error']['code']);
        self::assertStringContainsString('auth:list', $run->json()['error']['message']);
        self::assertSame($before, file_get_contents($this->directory . '/config.json'));
        self::assertSame(0, $this->cli(['config:set', 'logging.enabled', 'true', '--json'])->status);
        self::assertStringNotContainsString('AmbientFixtureSecret', (string) file_get_contents($this->directory . '/config.json'));
    }

    public function testProfileKeyWithoutAnyProfileIsAUsageErrorAndCreatesNothing(): void
    {
        foreach ([['config:set', 'default.task.groupId', '42'], ['config:get', 'default.task.groupId'], ['config:unset', 'default.task.groupId'], ['config:set', 'name', 'portal.example']] as $argv) {
            $run = $this->cli([...$argv, '--json']);
            self::assertSame(2, $run->status, $run->stdout);
            self::assertStringContainsString('auth:login', $run->json()['error']['message']);
            self::assertStringContainsString('auth:switch', $run->json()['error']['message']);
        }

        self::assertDirectoryDoesNotExist($this->directory);
        try {
            $this->repository->set($this->directory, null, 'default.task.groupId', '42');
            self::fail('Expected a usage error without a profile.');
        } catch (Failure $failure) {
            self::assertSame('usage-error', $failure->errorCode);
        }

        self::assertDirectoryDoesNotExist($this->directory);
        $run = $this->cli(['config:list', '--no-ansi']);
        self::assertSame(0, $run->status, $run->stderr);
        self::assertStringContainsString('Профиль не выбран', $run->stderr);
        self::assertStringNotContainsString('default.task.groupId', $run->stdout);
        self::assertDirectoryDoesNotExist($this->directory);
    }

    public function testRootSettingBeforeAnyProfileCreatesAProtectedFile(): void
    {
        $run = $this->cli(['config:set', 'logging.enabled', 'true', '--json']);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame(['key' => 'logging.enabled', 'value' => true, 'source' => 'root', 'profile' => null], $run->json()['data']);
        $saved = json_decode((string) file_get_contents($this->directory . '/config.json'), false, 64, JSON_THROW_ON_ERROR);
        self::assertEquals((object) ['version' => 1, 'defaultProfile' => null, 'profiles' => new \stdClass(), 'logging' => (object) ['enabled' => true]], $saved);
        self::assertInstanceOf(\stdClass::class, $saved->profiles);
        self::assertDirectoryDoesNotExist($this->directory . '/credentials');
        self::assertFileDoesNotExist($this->directory . '/state.json');
        if (PHP_OS_FAMILY !== 'Windows') {
            self::assertSame(0700, fileperms($this->directory) & 0777);
            self::assertSame(0600, fileperms($this->directory . '/config.json') & 0777);
        }
    }

    public function testInvalidLoggingSurvivesUnrelatedWritesAndBlocksLoggingChanges(): void
    {
        $this->profile('work');
        $this->repository->use($this->directory, 'work');
        $config = json_decode((string) file_get_contents($this->directory . '/config.json'), true, 64, JSON_THROW_ON_ERROR);
        file_put_contents($this->directory . '/config.json', json_encode([...$config, 'logging' => ['level' => 'Info']], JSON_THROW_ON_ERROR));
        $logging = static fn (string $directory): mixed => json_decode((string) file_get_contents($directory . '/config.json'), true, 64, JSON_THROW_ON_ERROR)['logging'];

        foreach ([['config:set', 'default.task.groupId', '42'], ['config:set', 'telemetry.maxProblemTrajectoryFiles', '5'], ['config:unset', 'default.task.groupId'], ['auth:switch', 'work']] as $argv) {
            $run = $this->cli([...$argv, '--json']);
            self::assertSame(0, $run->status, $run->stdout);
            self::assertSame(['level' => 'Info'], $logging($this->directory));
        }

        $before = file_get_contents($this->directory . '/config.json');
        $run = $this->cli(['config:set', 'logging.enabled', 'true', '--json']);
        self::assertSame(2, $run->status, $run->stdout);
        self::assertStringContainsString('logging.level', $run->json()['error']['message']);
        self::assertSame($before, file_get_contents($this->directory . '/config.json'));

        // The invalid field itself can be repaired through the same commands.
        self::assertSame(0, $this->cli(['config:set', 'logging.level', 'info', '--json'])->status);
        self::assertSame(0, $this->cli(['config:set', 'logging.enabled', 'true', '--json'])->status);
        self::assertSame(['level' => 'info', 'enabled' => true], $logging($this->directory));
    }

    public function testListShowsEffectiveDefaultsWithSourcesAndWritesNothing(): void
    {
        $this->profile('work', ['groupId' => 42]);
        $this->repository->use($this->directory, 'work');
        $before = file_get_contents($this->directory . '/config.json');
        $run = $this->cli(['config:list', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame('work', $run->json()['data']['profile']);
        $settings = array_column($run->json()['data']['settings'], null, 'key');
        self::assertSame(ConfigKeys::names(), array_keys($settings));
        self::assertSame(['key' => 'default.task.groupId', 'value' => 42, 'source' => 'profile'], $settings['default.task.groupId']);
        self::assertSame(['key' => 'default.task.allowsTimeTracking', 'value' => false, 'source' => 'default'], $settings['default.task.allowsTimeTracking']);
        self::assertSame(['key' => 'default.users.autoAddObserver', 'value' => false, 'source' => 'default'], $settings['default.users.autoAddObserver']);
        self::assertSame(['key' => 'default.users.observerId', 'value' => null, 'source' => 'default'], $settings['default.users.observerId']);
        self::assertSame(['key' => 'telemetry.collectProblemTrajectories', 'value' => true, 'source' => 'default'], $settings['telemetry.collectProblemTrajectories']);
        self::assertSame('disabled', $run->json()['data']['logging']['status']);
        self::assertSame([], $run->json()['meta']['apiCalls']);
        self::assertSame($before, file_get_contents($this->directory . '/config.json'));
        self::assertDirectoryDoesNotExist($this->directory . '/logs');
        self::assertFileDoesNotExist($this->directory . '/state.json');

        $plain = $this->cli(['config:list', '--plain']);
        self::assertStringContainsString("default.task.groupId\t42\tprofile\n", $plain->stdout);
        self::assertStringContainsString("default.task.allowsTimeTracking\tfalse\tdefault\n", $plain->stdout);
        foreach (explode("\n", rtrim($plain->stdout, "\n")) as $line) {
            self::assertCount(3, explode("\t", $line));
        }
    }

    public function testWorkingRolesStayWithTheirPortalProfile(): void
    {
        // USR-04: each portal keeps its own working roles; saving them needs no webhook input and no portal call.
        $this->profile('work');
        $this->profile('clientA', [], ['observerId' => 11, 'managerId' => 12]);
        $this->repository->use($this->directory, 'clientA');
        $credentials = [];
        foreach (glob($this->directory . '/credentials/*.env') ?: [] as $file) {
            $credentials[$file] = [file_get_contents($file), filemtime($file)];
        }

        foreach ([['default.users.observerId', '5'], ['default.users.managerId', '9']] as [$key, $value]) {
            $run = $this->cli(['config:set', $key, $value, '--profile', 'work', '--json']);
            self::assertSame(0, $run->status, $run->stdout . $run->stderr);
            self::assertSame([], $run->json()['meta']['apiCalls']);
        }

        $roles = static fn (ConsoleRun $run): array => array_intersect_key(array_column($run->json()['data']['settings'], 'value', 'key'), ['default.users.observerId' => true, 'default.users.managerId' => true, 'default.users.autoAddObserver' => true]);
        self::assertSame(['default.users.observerId' => 5, 'default.users.managerId' => 9, 'default.users.autoAddObserver' => false], $roles($this->cli(['config:list', '--profile', 'work', '--json'])));
        self::assertSame(['default.users.observerId' => 11, 'default.users.managerId' => 12, 'default.users.autoAddObserver' => false], $roles($this->cli(['config:list', '--json'])));
        self::assertSame('clientA', $this->repository->load($this->directory)['defaultProfile']);
        foreach ($credentials as $file => [$bytes, $modified]) {
            self::assertSame($bytes, file_get_contents($file));
            self::assertSame($modified, filemtime($file));
        }

        // Absent roles never select a person automatically.
        $this->profile('pet');
        self::assertSame(['default.users.observerId' => null, 'default.users.managerId' => null, 'default.users.autoAddObserver' => false], $roles($this->cli(['config:list', '--profile', 'pet', '--json'])));
        self::assertFileDoesNotExist($this->directory . '/state.json');
    }

    public function testConcurrentWritersLoseNoChange(): void
    {
        $this->profile('work');
        $this->profile('pet');
        $script = <<<'PHP'
require 'vendor/autoload.php';
$protection = new \Bitrix24\CLI\Infrastructure\Connection\FileProtection();
$repository = new \Bitrix24\CLI\Infrastructure\Connection\ProfileRepository($protection, new \Bitrix24\CLI\Infrastructure\Connection\FileCredentialStore($protection));
[, $directory, $role] = $argv;
fwrite(STDOUT, "READY\n");
fflush(STDOUT);
fgets(STDIN);
for ($i = 1; $i <= 20; $i++) {
    match ($role) {
        'profile-setting' => $repository->set($directory, 'work', 'default.task.groupId', (string) $i),
        'root-setting' => $repository->set($directory, null, 'logging.retentionDays', (string) $i),
        'login' => $repository->login($directory, 'p' . $i, 'https://p' . $i . '.invalid/rest/1/RecognizableFixtureSecret/'),
        'switch' => $repository->use($directory, $i % 2 === 0 ? 'pet' : 'work'),
        'doctor' => (new \Bitrix24\CLI\Infrastructure\Doctor\ProblemTrajectoryStore($repository, $protection, new \Bitrix24\CLI\Infrastructure\Doctor\SystemClock()))->configure($directory, ['maxProblemTrajectoryFiles' => $i]),
    };
}
PHP;
        $workers = [];
        try {
            foreach (['profile-setting', 'root-setting', 'login', 'switch', 'doctor'] as $role) {
                $process = proc_open([PHP_BINARY, '-r', $script, $this->directory, $role], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
                self::assertIsResource($process);
                $workers[$role] = [$process, $pipes];
                stream_set_timeout($pipes[1], 10);
                self::assertSame("READY\n", fgets($pipes[1]));
            }

            foreach ($workers as [$process, $pipes]) {
                fwrite($pipes[0], "GO\n");
                fclose($pipes[0]);
            }

            foreach ($workers as $role => [$process, $pipes]) {
                $stderr = stream_get_contents($pipes[2]);
                self::assertSame(0, proc_close($process), $role . ': ' . $stderr);
            }
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

        $config = $this->repository->load($this->directory);
        self::assertSame(20, $config['profiles']['work']['default']['task']['groupId']);
        self::assertSame(20, $config['logging']->retentionDays);
        self::assertSame(20, $config['telemetry']['maxProblemTrajectoryFiles']);
        self::assertSame('pet', $config['defaultProfile']);
        self::assertCount(22, $config['profiles']);
        self::assertCount(22, glob($this->directory . '/credentials/*.env') ?: []);
        self::assertSame([], glob($this->directory . '/.config-*') ?: []);
        if (PHP_OS_FAMILY !== 'Windows') {
            self::assertSame(0600, fileperms($this->directory . '/config.json') & 0777);
            foreach (glob($this->directory . '/credentials/*.env') ?: [] as $file) {
                self::assertSame(0600, fileperms($file) & 0777);
            }
        }
    }

    private function profile(string $alias, array $task = [], array $users = []): array
    {
        return ProfileFixtures::add($this->repository, $this->directory, $alias, 'https://' . strtolower($alias) . '.invalid/rest/1/RecognizableFixtureSecret/', null, $task, $users);
    }

    private function cli(array $argv): ConsoleRun
    {
        $run = ConsoleHarness::run($this->app, $argv);
        self::assertStringNotContainsString('RecognizableFixtureSecret', $run->stdout . $run->stderr);
        return $run;
    }
}
