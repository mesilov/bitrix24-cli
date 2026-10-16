<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Console\B24Application;
use Bitrix24\CLI\Infrastructure\Connection\FileCredentialStore;
use Bitrix24\CLI\Infrastructure\Connection\FileProtection;
use Bitrix24\CLI\Infrastructure\Connection\ProfileRepository;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\MutableDoctorClock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ScopeValidationCommandTest extends TestCase
{
    private const WEBHOOK = 'https://portal.invalid/rest/1/ScopeFixtureSecret/';
    private string $directory;
    private array $environment = [];
    private MutableDoctorClock $clock;
    private B24Application $app;
    private array $calls = [];
    private mixed $scopes = ['task'];
    private ?string $businessError = null;
    private bool $scopeTransportFailure = false;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/b24cli-scope-command-' . bin2hex(random_bytes(6));
        foreach (['BITRIX24_WEBHOOK', 'BITRIX24_PHP_SDK_PLAYGROUND_WEBHOOK', 'B24CLI_PROFILE', 'B24CLI_ENV_FILE', 'B24CLI_CONFIG_DIR'] as $name) {
            $this->environment[$name] = [getenv($name), $_ENV[$name] ?? null];
            putenv($name);
            unset($_ENV[$name]);
        }

        putenv('BITRIX24_WEBHOOK=' . self::WEBHOOK);
        putenv('B24CLI_CONFIG_DIR=' . $this->directory);
        $this->clock = new MutableDoctorClock();
        $mockHttpClient = new MockHttpClient(function (string $verb, string $url, array $options): MockResponse {
            $method = basename(parse_url($url, PHP_URL_PATH));
            $this->calls[] = $method;
            if ($method === 'scope') {
                self::assertSame([], json_decode($options['body'], true));
                if ($this->scopeTransportFailure) {
                    throw new \Symfony\Component\HttpClient\Exception\TransportException('Fixture timeout ' . self::WEBHOOK);
                }

                return $this->response(['result' => $this->scopes]);
            }

            if ($this->businessError !== null) {
                return $this->response(['error' => $this->businessError, 'error_description' => 'Fixture permission error'], 403);
            }

            return $this->response(['result' => match ($method) {
                'sonet_group.user.get' => [['USER_ID' => 1, 'ROLE' => 'A'], ['USER_ID' => 2, 'ROLE' => 'K']],
                'tasks.task.list' => ['items' => []],
                'tasks.task.get' => ['item' => ['id' => 1, 'title' => 'Fixture', 'chat' => ['id' => 3, 'entityId' => 1, 'entityType' => 'TASKS_TASK']]],
                'im.dialog.messages.get' => ['chat_id' => 3, 'messages' => []],
                'tasks.task.file.attach' => [true],
                default => [],
            }]);
        });
        $this->app = (new ApplicationFactory())->create(dirname(__DIR__, 2), packaged: true, httpClient: $mockHttpClient, clock: $this->clock);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
        foreach ($this->environment as $name => [$process, $value]) {
            putenv($process === false ? $name : $name . '=' . $process);
            if ($value === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $value;
            }
        }
    }

    public function testMissingImBlocksBeforeTaskReadButIndependentCommandUsesCache(): void
    {
        $run = ConsoleHarness::run($this->app, ['task:chat:list', '1', '--json']);
        self::assertSame(4, $run->status, $run->stdout);
        self::assertSame('scope-missing', $run->json()['error']['code']);
        self::assertSame(['im'], $run->json()['error']['details']['scopeValidation']['missingScopes']);
        self::assertSame(['scope'], $this->calls);
        $run = ConsoleHarness::run($this->app, ['task:list', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame(['scope', 'tasks.task.list'], $this->calls);
        self::assertSame('fresh', $run->json()['meta']['scopeValidation']['status']);
        self::assertStringNotContainsString('ScopeFixtureSecret', file_get_contents($this->directory . '/state.json'));
        self::assertFileDoesNotExist($this->directory . '/config.json');
    }

    public function testHandoverUsesActualPortalProjectScopeAndNeverSubstitutesSonet(): void
    {
        $this->scopes = ['task', 'sonet'];
        $args = ['task:handover', '--group-id', '42', '--from', '1', '--responsible', '2', '--json'];
        $run = ConsoleHarness::run($this->app, $args);
        self::assertSame(4, $run->status, $run->stdout);
        self::assertSame(['sonet_group'], $run->json()['error']['details']['scopeValidation']['missingScopes']);
        self::assertSame(['scope'], $this->calls);

        $this->scopes = ['task', 'sonet_group'];
        $this->clock->time = $this->clock->time->modify('+1 day');
        $run = ConsoleHarness::run($this->app, $args);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame('fresh', $run->json()['meta']['scopeValidation']['status']);
        self::assertSame([], $run->json()['data']['items']);
        self::assertSame(['scope', 'scope', 'sonet_group.user.get', 'tasks.task.list'], $this->calls);
    }

    public function testMissingTaskScopeBlocksEveryTemplateCommandBeforeBusinessApi(): void
    {
        $this->scopes = [];
        foreach ([
            ['task:template:show', '139'], ['task:template:fields:list'],
            ['task:template:add', '--title', 'x', '--creator', '1', '--responsible', '2'],
            ['task:template:update', '139', '--description', 'x'],
            ['task:template:delete', '139', '--force'],
        ] as $args) {
            $run = ConsoleHarness::run($this->app, [...$args, '--json']);
            self::assertSame(4, $run->status, $run->stdout);
            self::assertSame('scope-missing', $run->json()['error']['code']);
        }

        self::assertSame(['scope'], $this->calls);
    }

    public function testExplicitCheckForcesRefreshAndAllOutputModesExplainMissingScopes(): void
    {
        $report = null;
        foreach ([['--json'], ['--plain'], []] as $mode) {
            $run = ConsoleHarness::run($this->app, ['connection:check', ...$mode]);
            self::assertSame(4, $run->status, $run->stdout . $run->stderr);
            self::assertStringContainsString('im', $run->stdout . $run->stderr);
            self::assertStringContainsString('connection:check', $run->stdout . $run->stderr);
            self::assertStringNotContainsString('ScopeFixtureSecret', $run->stdout . $run->stderr);
            if ($mode === ['--json']) {
                $report = $run->json()['error']['details']['scopeValidation'];
                self::assertNull($run->json()['data']);
                self::assertSame('scope-missing', $run->json()['error']['code']);
                self::assertSame(['task'], $report['grantedScopes']);
                self::assertSame(['im'], $report['potentiallyAffectedCommands']['task:chat:list']);
            } else {
                self::assertIsArray($report);
                self::assertSame('', $run->stdout);
                $separator = $mode === ['--plain'] ? "\t" : ': ';
                foreach (['grantedScopes', 'lastSuccessfulCheckAt', 'scopeManifestRevision', 'potentiallyAffectedCommands', 'connection'] as $field) {
                    $value = is_array($report[$field]) ? json_encode($report[$field], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $report[$field];
                    self::assertStringContainsString($field . $separator . $value, $run->stderr);
                }
            }
        }

        self::assertCount(3, $this->calls);
        $this->scopes = ['task', 'im', 'disk', 'department', 'user', 'sonet_group'];
        $run = ConsoleHarness::run($this->app, ['connection:check', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([], $run->json()['data']['potentiallyAffectedCommands']);
        self::assertSame([], $run->json()['data']['missingScopes']);
        $run = ConsoleHarness::run($this->app, ['task:chat:list', '1', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame(4, count(array_filter($this->calls, static fn (string $method): bool => $method === 'scope')));
    }

    public function testCheckUsageExamplePerformsTheDiagnosticInsteadOfDryRun(): void
    {
        $examples = \Bitrix24\CLI\Console\TaskUsage::examples($this->app->get('connection:check'));
        self::assertStringContainsString('b24cli connection:check', $examples[0]);
        self::assertStringNotContainsString('--dry-run', $examples[0]);
    }

    public function testTtlAndManifestRevisionTriggerRefreshButAppVersionAloneDoesNot(): void
    {
        ConsoleHarness::run($this->app, ['task:list', '--json']);
        $this->editEntry(static function (array &$entry): void {
            $entry['checkedWithAppVersion'] = 'older-cli';
        });
        $this->clock->time = $this->clock->time->modify('+86399 seconds');
        ConsoleHarness::run($this->app, ['task:list', '--json']);
        self::assertSame(1, $this->scopeCalls());
        $this->clock->time = $this->clock->time->modify('+1 second');
        ConsoleHarness::run($this->app, ['task:list', '--json']);
        self::assertSame(2, $this->scopeCalls());
        $this->editEntry(static function (array &$entry): void {
            $entry['scopeManifestRevision'] = str_repeat('0', 64);
        });
        ConsoleHarness::run($this->app, ['task:list', '--json']);
        self::assertSame(3, $this->scopeCalls());
    }

    public function testFailedRefreshRetainsSuccessfulEvidenceAndBacksOff(): void
    {
        ConsoleHarness::run($this->app, ['task:list', '--json']);
        $original = $this->entry();
        $this->clock->time = $this->clock->time->modify('+1 day');
        $this->scopes = ['invalid' => 'not a list'];
        $run = ConsoleHarness::run($this->app, ['task:chat:list', '1', '--json']);
        self::assertNotSame('scope-missing', $run->json()['error']['code'] ?? null);
        self::assertSame('stale', $this->entry()['status']);
        self::assertSame($original['grantedScopes'], $this->entry()['grantedScopes']);
        self::assertSame($original['lastSuccessfulCheckAt'], $this->entry()['lastSuccessfulCheckAt']);
        $this->clock->time = $this->clock->time->modify('+299 seconds');
        $run = ConsoleHarness::run($this->app, ['task:list', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame('stale', $run->json()['meta']['scopeValidation']['status']);
        self::assertSame(2, $this->scopeCalls());
        $this->clock->time = $this->clock->time->modify('+1 second');
        $this->scopes = ['task', 'im'];
        ConsoleHarness::run($this->app, ['task:list', '--json']);
        self::assertSame(3, $this->scopeCalls());
        self::assertSame('fresh', $this->entry()['status']);
    }

    public function testUnknownAndEmptyScopesAreDifferentAndManualCheckIgnoresBackoff(): void
    {
        $this->scopes = ['bad' => 'response'];
        $run = ConsoleHarness::run($this->app, ['task:list', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame('unknown', $run->json()['meta']['scopeValidation']['status']);
        $run = ConsoleHarness::run($this->app, ['connection:check', '--json']);
        self::assertSame(1, $run->status);
        self::assertSame('scope-check-unavailable', $run->json()['error']['code']);
        self::assertSame(2, $this->scopeCalls());
        $this->scopes = [];
        ConsoleHarness::run($this->app, ['connection:check', '--json']);
        $run = ConsoleHarness::run($this->app, ['task:list', '--json']);
        self::assertSame(4, $run->status, $run->stdout);
        self::assertSame('scope-missing', $run->json()['error']['code']);
        self::assertSame([], $this->entry()['grantedScopes']);
    }

    public function testOfflineAndPolicyRejectionDoNotAccessStateOrNetwork(): void
    {
        foreach ([['--help'], ['--version'], ['list'], ['connection:list', '--json'], ['task:add', '--title', 'Preview', '--creator', '1', '--responsible', '1', '--dry-run', '--json'], ['connection:check', '--dry-run', '--json']] as $argv) {
            $run = ConsoleHarness::run($this->app, $argv);
            self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        }

        self::assertSame([], $this->calls);
        self::assertDirectoryDoesNotExist($this->directory);
        $run = ConsoleHarness::run($this->app, ['connection:check', '--api-policy', 'strict-rest3', '--json']);
        self::assertSame(4, $run->status);
        self::assertSame('policy-denied', $run->json()['error']['code']);
        self::assertSame([], $this->calls);
        $run = ConsoleHarness::run($this->app, ['task:list', '--api-policy', 'strict-rest3', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame(['tasks.task.list'], $this->calls);
        self::assertDirectoryDoesNotExist($this->directory);
    }

    public function testCredentialRotationAndSourceSelectionDoNotReuseOtherSnapshots(): void
    {
        ConsoleHarness::run($this->app, ['task:list', '--json']);
        putenv('BITRIX24_WEBHOOK=https://portal.invalid/rest/1/RotatedFixtureSecret/');
        ConsoleHarness::run($this->app, ['task:list', '--json']);
        self::assertSame(2, $this->scopeCalls());
        $fileProtection = new FileProtection();
        $profileRepository = new ProfileRepository($fileProtection, new FileCredentialStore($fileProtection));
        $profileRepository->add($this->directory, 'work', self::WEBHOOK, null, []);
        $profileRepository->add($this->directory, 'pet', self::WEBHOOK, null, []);

        $config = file_get_contents($this->directory . '/config.json');
        foreach (['work', 'pet'] as $alias) {
            $run = ConsoleHarness::run($this->app, ['task:list', '--profile', $alias, '--json']);
            self::assertSame(0, $run->status, $run->stdout);
        }

        self::assertSame(4, $this->scopeCalls());
        self::assertCount(4, json_decode(file_get_contents($this->directory . '/state.json'), true)['connections']);
        self::assertSame($config, file_get_contents($this->directory . '/config.json'));
        $run = ConsoleHarness::run($this->app, ['connection:check', 'work', '--json']);
        self::assertSame('work', $run->json()['error']['details']['scopeValidation']['connection']['profile']);
        $run = ConsoleHarness::run($this->app, ['connection:check', 'work', '--profile', 'pet', '--json']);
        self::assertSame(2, $run->status);
    }

    public function testObjectDenialKeepsItsErrorAndNeverRetriesMutation(): void
    {
        $this->businessError = 'ACCESS_DENIED';
        $run = ConsoleHarness::run($this->app, ['task:update', '1', '--title', 'Changed', '--json']);
        self::assertSame('permission-denied', $run->json()['error']['code']);
        self::assertSame(['scope', 'tasks.task.update'], $this->calls);
        self::assertSame('fresh', $this->entry()['status']);
        $this->businessError = 'insufficient_scope';
        $run = ConsoleHarness::run($this->app, ['task:update', '1', '--title', 'Changed', '--json']);
        self::assertSame('permission-denied', $run->json()['error']['code']);
        self::assertSame('insufficient_scope', $run->json()['error']['details']['apiErrorCode']);
        self::assertSame(['scope', 'tasks.task.update', 'tasks.task.update'], $this->calls);
        self::assertSame('stale', $this->entry()['status']);
    }

    public function testMalformedStateIsPreservedAndDoesNotCreateFalseMissingScopes(): void
    {
        (new FileProtection())->directory($this->directory);
        file_put_contents($this->directory . '/state.json', 'broken');
        chmod($this->directory . '/state.json', 0600);
        $run = ConsoleHarness::run($this->app, ['task:list', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame('unknown', $run->json()['meta']['scopeValidation']['status']);
        self::assertSame(['tasks.task.list'], $this->calls);
        $run = ConsoleHarness::run($this->app, ['connection:check', '--json']);
        self::assertSame(1, $run->status);
        self::assertSame('broken', file_get_contents($this->directory . '/state.json'));
    }

    public function testOptionalDiskRequirementOnlyBlocksLocalUpload(): void
    {
        $run = ConsoleHarness::run($this->app, ['task:file:attach', '1', '--file-id', '7', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        (new Filesystem())->dumpFile($this->directory . '/upload.txt', 'test');
        $run = ConsoleHarness::run($this->app, ['task:file:attach', '1', '--file', $this->directory . '/upload.txt', '--folder-id', '7', '--json']);
        self::assertSame(4, $run->status, $run->stdout);
        self::assertSame(['disk'], $run->json()['error']['details']['scopeValidation']['missingScopes']);
        self::assertSame(['scope', 'tasks.task.file.attach'], $this->calls);
    }

    public function testNetworkFailureIsUnknownAndDoesNotProbeAgainDuringBackoff(): void
    {
        $this->scopeTransportFailure = true;
        foreach ([['--json'], ['--plain']] as $mode) {
            $run = ConsoleHarness::run($this->app, ['task:list', ...$mode]);
            self::assertSame(0, $run->status, $run->stdout . $run->stderr);
            self::assertStringNotContainsString('ScopeFixtureSecret', $run->stdout . $run->stderr);
        }

        self::assertSame(1, $this->scopeCalls());
        self::assertNull($this->entry()['lastSuccessfulCheckAt']);
        self::assertSame('unknown', $this->entry()['status']);
    }

    public function testRawScalarAndObjectResponsesCannotBecomeConfirmedScopeLists(): void
    {
        foreach (['task', (object) [], 1, true, null] as $result) {
            $this->scopes = $result;
            $run = ConsoleHarness::run($this->app, ['connection:check', '--json']);
            self::assertSame(1, $run->status, $run->stdout);
            self::assertSame('scope-check-unavailable', $run->json()['error']['code']);
            self::assertNull($this->entry()['lastSuccessfulCheckAt']);
        }
    }

    public function testExplicitEnvFilesAndConfigDirectoriesAreIndependent(): void
    {
        (new FileProtection())->directory($this->directory);
        foreach (['a', 'b'] as $name) {
            $file = $this->directory . '/' . $name . '.env';
            file_put_contents($file, 'BITRIX24_WEBHOOK=' . self::WEBHOOK . "\n");
            chmod($file, 0600);
            $run = ConsoleHarness::run($this->app, ['task:list', '--env-file', $file, '--json']);
            self::assertSame(0, $run->status, $run->stdout);
        }

        self::assertSame(2, $this->scopeCalls());
        $run = ConsoleHarness::run($this->app, ['task:list', '--env-file', $this->directory . '/a.env', '--config-dir', $this->directory . '/other', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame(3, $this->scopeCalls());
        self::assertFileExists($this->directory . '/other/state.json');
    }

    public function testStrictRest3UsesFreshCacheToBlockWithoutSendingRest1(): void
    {
        $this->scopes = [];
        ConsoleHarness::run($this->app, ['connection:check', '--json']);
        $run = ConsoleHarness::run($this->app, ['task:list', '--api-policy', 'strict-rest3', '--json']);
        self::assertSame(4, $run->status, $run->stdout);
        self::assertSame('scope-missing', $run->json()['error']['code']);
        self::assertSame(['scope'], $this->calls);
        self::assertSame([], $run->json()['meta']['apiCalls']);
    }

    public function testConcurrentCommandsShareOneScopeProbe(): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('Process concurrency requires pcntl.');
        }

        (new FileProtection())->directory($this->directory);
        $log = $this->directory . '/probe.log';
        $mockHttpClient = new MockHttpClient(static function (string $verb, string $url) use ($log): MockResponse {
            if (basename(parse_url($url, PHP_URL_PATH)) === 'scope') {
                file_put_contents($log, "probe\n", FILE_APPEND | LOCK_EX);
                usleep(20000);
                return new MockResponse('{"result":["task"]}');
            }

            return new MockResponse('{"result":{"items":[]}}');
        });
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), packaged: true, httpClient: $mockHttpClient, clock: $this->clock);
        $pid = pcntl_fork();
        self::assertNotSame(-1, $pid);
        if ($pid === 0) {
            exit(ConsoleHarness::run($app, ['task:list', '--json'])->status);
        }

        $run = ConsoleHarness::run($app, ['task:list', '--json']);
        pcntl_waitpid($pid, $status);
        self::assertSame(0, pcntl_wexitstatus($status));
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame("probe\n", file_get_contents($log));
    }

    private function scopeCalls(): int
    {
        return count(array_filter($this->calls, static fn (string $method): bool => $method === 'scope'));
    }

    private function entry(): array
    {
        return array_values(json_decode(file_get_contents($this->directory . '/state.json'), true)['connections'])[0];
    }

    private function editEntry(\Closure $edit): void
    {
        $state = json_decode(file_get_contents($this->directory . '/state.json'), true);
        $key = array_key_first($state['connections']);
        $edit($state['connections'][$key]);
        file_put_contents($this->directory . '/state.json', json_encode($state, JSON_THROW_ON_ERROR));
    }

    private function response(array $data, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($data, JSON_THROW_ON_ERROR), ['http_code' => $status, 'response_headers' => ['content-type: application/json']]);
    }
}
