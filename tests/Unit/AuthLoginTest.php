<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Console\B24Application;
use Bitrix24\CLI\Tests\Support\CapturedOutput;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\ConsoleRun;
use Bitrix24\CLI\Tests\Support\MutableDoctorClock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * auth:login verifies a webhook through the real SDK stack before saving it; the portal is a controlled HTTP fixture.
 */
final class AuthLoginTest extends TestCase
{
    private const WEBHOOK = 'https://portal.invalid/rest/1/LoginFixtureSecret/';
    private const ROTATED = 'https://portal.invalid/rest/1/RotatedLoginSecret/';
    private const ALL_SCOPES = ['task', 'im', 'disk', 'department', 'user', 'sonet_group'];
    private const OWNER = ['ID' => '1', 'ADMIN' => true, 'NAME' => 'Иван', 'LAST_NAME' => 'Иванов', 'PERSONAL_GENDER' => 'M', 'TIME_ZONE' => 'Europe/Moscow'];

    private string $directory;
    private array $environment = [];
    private B24Application $app;
    private array $calls = [];
    private mixed $owner = self::OWNER;
    private mixed $scopes = self::ALL_SCOPES;
    /** @var array{0: string, 1: int}|null */
    private ?array $ownerError = null;
    private bool $ownerTransportFailure = false;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/b24cli-auth-login-' . bin2hex(random_bytes(6));
        foreach (['BITRIX24_WEBHOOK', 'BITRIX24_PHP_SDK_PLAYGROUND_WEBHOOK', 'B24CLI_PROFILE', 'B24CLI_ENV_FILE', 'B24CLI_CONFIG_DIR', 'B24CLI_API_POLICY'] as $name) {
            $this->environment[$name] = [getenv($name), $_ENV[$name] ?? null];
            putenv($name);
            unset($_ENV[$name]);
        }

        putenv('B24CLI_CONFIG_DIR=' . $this->directory);
        $mockHttpClient = new MockHttpClient(function (string $verb, string $url): MockResponse {
            $method = basename((string) parse_url($url, PHP_URL_PATH));
            $this->calls[] = $method;
            if ($method === 'profile') {
                if ($this->ownerTransportFailure) {
                    throw new TransportException('Fixture timeout ' . $url);
                }

                return $this->ownerError === null
                    ? $this->response(['result' => $this->owner])
                    : $this->response(['error' => $this->ownerError[0], 'error_description' => 'Fixture rejection of ' . $url], $this->ownerError[1]);
            }

            if ($method === 'scope') {
                return $this->response(['result' => $this->scopes]);
            }

            return $this->response(['result' => match ($method) {
                'tasks.task.list' => ['items' => []],
                'tasks.task.get' => ['item' => ['id' => 1, 'title' => 'Fixture', 'chat' => ['id' => 3, 'entityId' => 1, 'entityType' => 'TASKS_TASK']]],
                default => [],
            }]);
        });
        $this->app = (new ApplicationFactory())->create(dirname(__DIR__, 2), packaged: true, httpClient: $mockHttpClient, clock: new MutableDoctorClock());
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

    public function testValidWebhookIsSavedWithItsOwnerAndTheFirstScopeSnapshot(): void
    {
        $run = $this->login(['work', '--json']);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        $json = $run->json();
        self::assertSame([
            'profile' => 'work', 'name' => 'portal.invalid', 'hostname' => 'portal.invalid', 'isDefault' => true, 'becameDefault' => true,
            'replaced' => false, 'previousHostname' => null, 'verified' => true,
            'owner' => ['ID' => '1', 'NAME' => 'Иван', 'LAST_NAME' => 'Иванов', 'ADMIN' => true],
        ], $json['data']);
        self::assertSame([['method' => 'profile', 'apiVersion' => '1.0', 'effect' => 'read'], ['method' => 'scope', 'apiVersion' => '1.0', 'effect' => 'read']], $json['meta']['apiCalls']);
        self::assertFalse($json['meta']['local']);
        self::assertSame('fresh', $json['meta']['scopeValidation']['status']);
        self::assertSame([], $json['meta']['scopeValidation']['missingScopes']);
        self::assertSame('work', $json['meta']['scopeValidation']['connection']['profile']);

        $config = $this->config();
        self::assertSame('work', $config['defaultProfile']);
        self::assertSame(['name', 'connection'], array_keys($config['profiles']['work']));
        self::assertSame('portal.invalid', $config['profiles']['work']['connection']['hostname']);
        $credential = $this->directory . '/' . $config['profiles']['work']['connection']['credentialFile'];
        self::assertStringContainsString(self::WEBHOOK, (string) file_get_contents($credential));
        if (PHP_OS_FAMILY !== 'Windows') {
            self::assertSame(0600, fileperms($credential) & 0777);
            self::assertSame(0600, fileperms($this->directory . '/config.json') & 0777);
            self::assertSame(0700, fileperms($this->directory . '/credentials') & 0777);
        }

        $state = $this->state();
        self::assertCount(1, $state);
        self::assertSame('fresh', $state[0]['status']);
        self::assertSame($this->sorted(self::ALL_SCOPES), $state[0]['grantedScopes']);
        foreach ([$run->stdout, $run->stderr, file_get_contents($this->directory . '/config.json'), file_get_contents($this->directory . '/state.json')] as $text) {
            self::assertStringNotContainsString('LoginFixtureSecret', (string) $text);
        }

        // The next command needs neither --profile nor another scope request.
        $this->calls = [];
        $list = ConsoleHarness::run($this->app, ['task:list', '--json']);
        self::assertSame(0, $list->status, $list->stdout);
        self::assertSame(['tasks.task.list'], $this->calls);
        self::assertSame('work', $list->json()['meta']['connection']['profile']);
        self::assertSame('fresh', $list->json()['meta']['scopeValidation']['status']);
    }

    public function testSecondPortalKeepsTheDefaultAndHumanOutputNamesTheOwner(): void
    {
        $this->login(['work', '--json']);
        foreach (['--no-ansi', '--plain'] as $index => $mode) {
            $alias = 'client' . $index;
            $run = $this->login([$alias, $mode], 'https://client.invalid/rest/7/LoginFixtureSecret/');
            self::assertSame(0, $run->status, $run->stdout . $run->stderr);
            foreach (['client.invalid', 'ID 1', 'Иван Иванов', 'auth:switch ' . $alias] as $text) {
                self::assertStringContainsString($text, $run->stdout . $run->stderr);
            }

            self::assertStringNotContainsString('LoginFixtureSecret', $run->stdout . $run->stderr);
            self::assertSame('work', $this->config()['defaultProfile']);
        }

        self::assertCount(3, $this->state());
    }

    public function testInvalidWebhookIsNotSaved(): void
    {
        $failures = [
            'invalid credentials' => fn () => $this->ownerError = ['INVALID_CREDENTIALS', 401],
            'no auth' => fn () => $this->ownerError = ['NO_AUTH_FOUND', 401],
            'rest unavailable' => fn () => $this->ownerError = ['ACCESS_DENIED', 403],
            'transport' => fn () => $this->ownerTransportFailure = true,
            'inactive user' => fn () => $this->owner = [],
        ];
        foreach ($failures as $name => $arrange) {
            $this->ownerError = null;
            $this->ownerTransportFailure = false;
            $this->owner = self::OWNER;
            $arrange();
            foreach (['--json', '--plain', '--no-ansi'] as $mode) {
                $this->calls = [];
                $run = $this->login(['work', $mode]);
                $combined = $run->stdout . $run->stderr;
                self::assertSame(1, $run->status, $name . ': ' . $combined);
                self::assertStringContainsString('the profile was not saved', $combined, $name);
                self::assertStringContainsString('--skip-check', $combined, $name);
                self::assertStringNotContainsString('LoginFixtureSecret', $combined, $name);
                self::assertSame(['profile'], $this->calls, $name);
                self::assertDirectoryDoesNotExist($this->directory, $name);
                if ($mode === '--json') {
                    self::assertNull($run->json()['data']);
                    self::assertSame(['profile'], array_column($run->json()['meta']['apiCalls'], 'method'));
                }
            }
        }

        $run = $this->login(['work', '--json']);
        self::assertSame('connection-unavailable', $run->json()['error']['code']);
        self::assertStringContainsString('inactive', $run->json()['error']['message']);
    }

    public function testFailedLoginLeavesExistingConfigurationUntouched(): void
    {
        $this->login(['main', '--json']);
        $before = $this->snapshot();
        $this->ownerError = ['INVALID_CREDENTIALS', 401];
        $run = $this->login(['work', '--json'], 'https://other.invalid/rest/1/OtherLoginSecret/');
        self::assertSame(1, $run->status, $run->stdout);
        self::assertSame($before, $this->snapshot());

        // A failed replacement keeps the old secret and every setting.
        $run = $this->login(['main', '--replace', '--json'], self::ROTATED);
        self::assertSame(1, $run->status, $run->stdout);
        self::assertSame($before, $this->snapshot());
        self::assertStringNotContainsString('RotatedLoginSecret', $run->stdout . $run->stderr);
    }

    public function testMissingScopesDoNotBlockLogin(): void
    {
        $this->scopes = ['task'];
        $run = $this->login(['work', '--no-ansi']);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        foreach (['im', 'disk', 'task:chat:list', 'auth:status work'] as $text) {
            self::assertStringContainsString($text, $run->stderr);
        }

        self::assertSame('work', $this->config()['defaultProfile']);
        self::assertSame(['task'], $this->state()[0]['grantedScopes']);

        $run = $this->login(['pet', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        $report = $run->json()['meta']['scopeValidation'];
        self::assertSame('fresh', $report['status']);
        self::assertContains('im', $report['missingScopes']);
        self::assertSame(['im'], $report['potentiallyAffectedCommands']['task:chat:list']);

        // The exact diagnostic with exit 4 stays with auth:status.
        $run = ConsoleHarness::run($this->app, ['auth:status', 'work', '--json']);
        self::assertSame(4, $run->status, $run->stdout);
        self::assertSame('scope-missing', $run->json()['error']['code']);
    }

    public function testUnreadableScopesSaveTheProfileWithAnUnknownSnapshot(): void
    {
        $this->scopes = ['bad' => 'response'];
        $run = $this->login(['work', '--no-ansi']);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertStringContainsString('auth:status work', $run->stderr);
        self::assertSame('work', $this->config()['defaultProfile']);
        self::assertSame('unknown', $this->state()[0]['status']);
        self::assertNull($this->state()[0]['lastSuccessfulCheckAt']);

        $run = $this->login(['pet', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame('unknown', $run->json()['meta']['scopeValidation']['status']);
        self::assertStringContainsString('auth:status', implode(' ', $run->json()['meta']['notices']));
    }

    public function testSkipCheckSavesTheProfileOffline(): void
    {
        $run = $this->login(['ci', '--skip-check', '-n', '--json']);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertFalse($run->json()['data']['verified']);
        self::assertNull($run->json()['data']['owner']);
        self::assertTrue($run->json()['meta']['local']);
        self::assertArrayNotHasKey('scopeValidation', $run->json()['meta']);
        self::assertSame([], $run->json()['meta']['apiCalls']);
        self::assertSame([], $this->calls);
        self::assertSame('ci', $this->config()['defaultProfile']);
        self::assertFileDoesNotExist($this->directory . '/state.json');
    }

    public function testStrictPolicyDeniesVerificationBeforeTheSecretIsRead(): void
    {
        foreach ([['--api-policy', 'strict-rest3'], []] as $policy) {
            if ($policy === []) {
                putenv('B24CLI_API_POLICY=strict-rest3');
            }

            $input = new ArgvInput(['b24cli', 'auth:login', 'work', '--webhook-stdin', '--json', ...$policy]);
            $input->setInteractive(false);
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, self::WEBHOOK);
            rewind($stream);
            $input->setStream($stream);
            $output = new CapturedOutput();
            self::assertSame(4, $this->app->run($input, $output));
            self::assertSame(0, ftell($stream), 'A denied login must not consume webhook stdin.');
            fclose($stream);
            $json = json_decode($output->fetch(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('policy-denied', $json['error']['code']);
            self::assertStringContainsString('--skip-check', $json['error']['message']);
            self::assertDirectoryDoesNotExist($this->directory);
        }

        $run = $this->login(['work', '--skip-check', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([], $this->calls);
        self::assertSame('work', $this->config()['defaultProfile']);
        self::assertFileDoesNotExist($this->directory . '/state.json');
    }

    public function testReplacementVerifiesTheNewWebhookAndNeverInheritsTheOldSnapshot(): void
    {
        $this->login(['work', '--json']);
        ConsoleHarness::run($this->app, ['config:set', 'default.task.groupId', '42', '--json']);
        ConsoleHarness::run($this->app, ['config:set', 'default.users.observerId', '5', '--json']);
        $old = $this->directory . '/' . $this->config()['profiles']['work']['connection']['credentialFile'];

        // The rotated webhook lost the im scope; the snapshot of the old secret must not hide that.
        $this->scopes = ['task'];
        $this->calls = [];
        $run = $this->login(['work', '--replace', '--json'], self::ROTATED);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertTrue($run->json()['data']['replaced']);
        self::assertSame('portal.invalid', $run->json()['data']['previousHostname']);
        self::assertSame(['profile', 'scope'], $this->calls);
        $config = $this->config();
        self::assertSame('work', $config['defaultProfile']);
        self::assertSame(['task' => ['groupId' => 42], 'users' => ['observerId' => 5]], $config['profiles']['work']['default']);
        self::assertFileDoesNotExist($old);
        self::assertStringContainsString(self::ROTATED, (string) file_get_contents($this->directory . '/' . $config['profiles']['work']['connection']['credentialFile']));
        self::assertCount(2, $this->state());

        $this->calls = [];
        $run = ConsoleHarness::run($this->app, ['task:chat:list', '1', '--json']);
        self::assertSame(4, $run->status, $run->stdout);
        self::assertSame('scope-missing', $run->json()['error']['code']);
        self::assertSame([], $this->calls);
        $settings = array_column(ConsoleHarness::run($this->app, ['config:list', '--profile', 'work', '--json'])->json()['data']['settings'], 'value', 'key');
        self::assertSame(42, $settings['default.task.groupId']);
        self::assertSame(5, $settings['default.users.observerId']);
    }

    public function testNonInteractiveLoginWithoutStdinIsAUsageErrorWithoutPartialProfile(): void
    {
        foreach ([['work', '-n'], ['work', '--webhook-stdin', '--config', '-']] as $arguments) {
            $run = ConsoleHarness::run($this->app, ['auth:login', ...$arguments, '--json'], self::WEBHOOK);
            self::assertSame(2, $run->status, $run->stdout);
            self::assertSame('usage-error', $run->json()['error']['code']);
        }

        self::assertDirectoryDoesNotExist($this->directory);
        $this->login(['work', '--skip-check', '--json']);
        $before = $this->snapshot();
        $run = ConsoleHarness::run($this->app, ['auth:login', 'work', '--replace', '-n', '--json']);
        self::assertSame(2, $run->status, $run->stdout);
        self::assertSame('usage-error', $run->json()['error']['code']);
        self::assertSame($before, $this->snapshot());
        self::assertSame([], $this->calls);
    }

    private function login(array $arguments, string $webhook = self::WEBHOOK): ConsoleRun
    {
        return ConsoleHarness::run($this->app, ['auth:login', ...$arguments, '--webhook-stdin'], $webhook . "\n");
    }

    private function config(): array
    {
        return json_decode((string) file_get_contents($this->directory . '/config.json'), true, 64, JSON_THROW_ON_ERROR);
    }

    private function state(): array
    {
        return array_values(json_decode((string) file_get_contents($this->directory . '/state.json'), true, 64, JSON_THROW_ON_ERROR)['connections']);
    }

    /** Bytes of the configuration and every credential file. */
    private function snapshot(): array
    {
        $files = [$this->directory . '/config.json', ...(glob($this->directory . '/credentials/*.env') ?: [])];
        sort($files);
        return array_combine($files, array_map(static fn (string $file): string => (string) file_get_contents($file), $files));
    }

    private function sorted(array $values): array
    {
        sort($values);
        return $values;
    }

    private function response(array $data, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($data, JSON_THROW_ON_ERROR), ['http_code' => $status, 'response_headers' => ['content-type: application/json']]);
    }
}
