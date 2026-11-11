<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Logging\LoggingSettings;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Connection\B24ClientProvider;
use Bitrix24\CLI\Infrastructure\Connection\ConfigDirectoryResolver;
use Bitrix24\CLI\Infrastructure\Connection\FileCredentialStore;
use Bitrix24\CLI\Infrastructure\Connection\FileProtection;
use Bitrix24\CLI\Infrastructure\Connection\ProfileRepository;
use Bitrix24\CLI\Infrastructure\Doctor\ProblemTrajectoryStore;
use Bitrix24\CLI\Infrastructure\Doctor\SystemClock;
use Bitrix24\CLI\Infrastructure\Logging\DailyMaskFileHandler;
use Bitrix24\CLI\Infrastructure\Logging\JsonLinesFormatter;
use Bitrix24\CLI\Infrastructure\Logging\LoggingRuntime;
use Bitrix24\CLI\Infrastructure\Logging\LoggingSettingsReader;
use Bitrix24\CLI\Infrastructure\Logging\SdkSummaryProcessor;
use Bitrix24\CLI\Infrastructure\Logging\SecretRedactingProcessor;
use Bitrix24\CLI\Infrastructure\Logging\SizeLimitProcessor;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\ConsoleRun;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FileLoggingTest extends TestCase
{
    private const WEBHOOK = 'https://portal.invalid/rest/7/LoggingFixtureSecret42/';
    private const ENVIRONMENT = ['BITRIX24_WEBHOOK', 'BITRIX24_PHP_SDK_PLAYGROUND_WEBHOOK', 'B24CLI_ENV_FILE', 'B24CLI_PROFILE', 'B24CLI_CONFIG_DIR', 'TZ'];

    private string $directory;
    private FileProtection $protection;
    private ProfileRepository $repository;
    private array $environment = [];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/b24cli-logging-' . bin2hex(random_bytes(6));
        foreach (self::ENVIRONMENT as $key) {
            $this->environment[$key] = getenv($key);
            putenv($key);
        }

        putenv('B24CLI_CONFIG_DIR=' . $this->directory);
        $this->protection = new FileProtection();
        $this->repository = new ProfileRepository($this->protection, new FileCredentialStore($this->protection));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                @chmod($file->getPathname(), 0700);
                $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }

            rmdir($this->directory);
        }

        foreach ($this->environment as $key => $value) {
            putenv($value === false ? $key : $key . '=' . $value);
        }
    }

    public function testDefaultsAndPartialSettingsDoNotWriteConfig(): void
    {
        self::assertSame(['settings' => LoggingSettings::DEFAULTS, 'status' => 'disabled', 'reason' => null], LoggingSettings::effective(null, false));
        $partial = LoggingSettings::effective((object) ['enabled' => true]);
        self::assertSame('ok', $partial['status']);
        self::assertSame([...LoggingSettings::DEFAULTS, 'enabled' => true], $partial['settings']);
        self::assertSame('b24cli-20261112.log', LoggingSettings::fileName(LoggingSettings::DEFAULTS['fileMask'], new \DateTimeImmutable('2026-11-12')));

        $this->config(['logging' => ['enabled' => true]]);
        $before = file_get_contents($this->directory . '/config.json');
        (new LoggingSettingsReader(new ConfigDirectoryResolver()))->read(null);
        self::assertSame($before, file_get_contents($this->directory . '/config.json'));
    }

    public function testProfileAndTelemetryWritesKeepValidAndInvalidLogging(): void
    {
        foreach ([['enabled' => true, 'level' => 'debug'], ['level' => 'Info', 'unknown' => [1]], []] as $logging) {
            $this->clean();
            $this->config(['logging' => $logging === [] ? new \stdClass() : $logging]);
            $expected = json_decode((string) file_get_contents($this->directory . '/config.json'), false)->logging;
            $this->repository->add($this->directory, 'work', self::WEBHOOK, null, ['allowsTimeTracking' => false]);
            $this->repository->use($this->directory, 'work');
            (new ProblemTrajectoryStore($this->repository, $this->protection, new SystemClock()))->configure($this->directory, ['collectProblemTrajectories' => false]);
            $this->repository->remove($this->directory, 'work');
            $saved = json_decode((string) file_get_contents($this->directory . '/config.json'), false);
            self::assertEquals($expected, $saved->logging);
            self::assertSame(json_encode($expected), json_encode($saved->logging));
            self::assertFalse($saved->telemetry->collectProblemTrajectories);
        }
    }

    public function testLoggingIsNotAcceptedInsideProfilesOrRuntimeConfig(): void
    {
        $this->config(['profiles' => ['work' => ['name' => 'portal.invalid', 'connection' => ['hostname' => 'portal.invalid', 'credentialFile' => 'credentials/0123456789abcdef.env'], 'logging' => ['enabled' => true]]]]);
        try {
            $this->repository->load($this->directory);
            self::fail('Profile logging must be rejected.');
        } catch (Failure $failure) {
            self::assertSame('configuration-error', $failure->errorCode);
        }

        $this->clean();
        putenv('BITRIX24_WEBHOOK=' . self::WEBHOOK);
        $run = $this->cli(['task:add', '--title', 'T', '--creator', '1', '--responsible', '2', '--dry-run', '--json', '--config', '-'], '{"logging":{"enabled":true}}');
        self::assertSame(2, $run->status, $run->stdout);
        self::assertDirectoryDoesNotExist($this->directory . '/logs');
    }

    public function testNoConfigOrDisabledLoggingCreatesNothing(): void
    {
        putenv('BITRIX24_WEBHOOK=' . self::WEBHOOK);
        self::assertSame(0, $this->dryRun()->status);
        self::assertDirectoryDoesNotExist($this->directory);
        $this->config(['logging' => ['enabled' => false]]);
        self::assertSame(0, $this->dryRun()->status);
        self::assertDirectoryDoesNotExist($this->directory . '/logs');
    }

    public function testProcessEnvironmentFileAndProfileConnectionsAllUseConfigDirectoryLogging(): void
    {
        $this->config(['logging' => ['enabled' => true]]);
        putenv('BITRIX24_WEBHOOK=' . self::WEBHOOK);
        self::assertSame(0, $this->dryRun()->status);
        putenv('BITRIX24_WEBHOOK');

        $envFile = $this->directory . '/external.env';
        file_put_contents($envFile, 'BITRIX24_WEBHOOK=' . self::WEBHOOK . "\n");
        self::assertSame(0, $this->dryRun(['--env-file', $envFile])->status);

        $this->repository->add($this->directory, 'work', self::WEBHOOK, null, ['allowsTimeTracking' => false]);
        self::assertSame(0, $this->dryRun(['--profile', 'work'])->status);

        $records = $this->records();
        $starts = array_values(array_filter($records, static fn (array $record): bool => $record['message'] === 'command.start'));
        self::assertCount(3, $starts);
        self::assertCount(3, array_unique(array_column($starts, 'runId')));
    }

    public function testInvalidLoggingWarnsOnceAndKeepsCommandResult(): void
    {
        putenv('BITRIX24_WEBHOOK=' . self::WEBHOOK);
        $plain = $this->dryRun();
        $this->config(['logging' => ['enabled' => true, 'level' => 'Info']]);
        $run = $this->dryRun();
        self::assertSame($plain->status, $run->status);
        self::assertSame($plain->stdout, $run->stdout);
        self::assertSame(1, substr_count($run->stderr, 'Warning:'));
        self::assertStringContainsString('logging.level', $run->stderr);
        self::assertDirectoryDoesNotExist($this->directory . '/logs');

        $this->repository->add($this->directory, 'work', self::WEBHOOK, null, ['allowsTimeTracking' => false]);
        $this->config([...json_decode((string) file_get_contents($this->directory . '/config.json'), true), 'logging' => ['retentionDays' => 0]]);
        putenv('BITRIX24_WEBHOOK');
        $run = $this->cli(['connection:use', 'work', '--json']);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame(['retentionDays' => 0], json_decode((string) file_get_contents($this->directory . '/config.json'), true)['logging']);
    }

    public function testUnwritableDirectoryWarnsOnceWithoutChangingOutput(): void
    {
        putenv('BITRIX24_WEBHOOK=' . self::WEBHOOK);
        $plain = $this->dryRun();
        $this->config(['logging' => ['enabled' => true, 'directory' => 'not-a-directory']]);
        file_put_contents($this->directory . '/not-a-directory', 'regular file');
        $run = $this->dryRun();
        self::assertSame($plain->status, $run->status);
        self::assertSame($plain->stdout, $run->stdout);
        self::assertSame(1, substr_count($run->stderr, 'Warning:'));
        self::assertStringNotContainsString('LoggingFixtureSecret42', $run->stderr);
    }

    public function testLifecycleRecordsAreJsonLinesWithoutArguments(): void
    {
        $this->config(['logging' => ['enabled' => true]]);
        putenv('BITRIX24_WEBHOOK=' . self::WEBHOOK);
        self::assertSame(0, ($ok = $this->cli(['task:add', '--title', 'Договор', '--creator', '1', '--responsible', '2', '--dry-run', '--json']))->status, $ok->stdout);
        $failed = $this->cli(['task:add', '--title', 'Договор', '--dry-run', '--json']);
        self::assertNotSame(0, $failed->status);
        foreach ([['--help'], ['--version'], ['list'], ['help', 'task:add']] as $argv) {
            $this->cli($argv);
        }

        $raw = (string) file_get_contents($this->logFile());
        self::assertStringNotContainsString('Договор', $raw);
        $records = $this->records();
        foreach ($records as $record) {
            self::assertSame(['datetime', 'level', 'channel', 'message', 'context', 'runId'], array_keys($record));
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}[+-]\d{2}:\d{2}$/', $record['datetime']);
        }

        self::assertSame(['command.start', 'command.finish', 'command.start', 'command.error', 'command.finish'], array_column($records, 'message'));
        self::assertSame(['command' => 'task:add', 'exitCode' => 0], array_intersect_key($records[1]['context'], ['command' => 1, 'exitCode' => 1]));
        self::assertIsInt($records[1]['context']['durationMs']);
        self::assertSame('error', $records[3]['level']);
        self::assertSame($failed->status, $records[4]['context']['exitCode']);
    }

    public function testJsonOutputIsTheSameWithAndWithoutLogging(): void
    {
        putenv('BITRIX24_WEBHOOK=' . self::WEBHOOK);
        $without = $this->dryRun();
        $this->config(['logging' => ['enabled' => true, 'level' => 'debug']]);
        $with = $this->dryRun();
        self::assertSame($without->stdout, $with->stdout);
        self::assertSame('', $with->stderr);
        self::assertFileExists($this->logFile());
    }

    public function testDailyFileNamesTimezoneAndMidnight(): void
    {
        $warnings = [];
        $handler = $this->handler($this->directory . '/logs', 7, $warnings, new \DateTimeZone('Asia/Omsk'));
        $handler->handle($this->record('2026-11-11T20:30:00+00:00'));
        $handler->handle($this->record('2026-11-12T17:59:59+00:00'));
        $handler->handle($this->record('2026-11-12T18:00:01+00:00'));
        $handler->close();
        self::assertSame(2, count(file($this->directory . '/logs/b24cli-20261112.log')));
        self::assertSame(1, count(file($this->directory . '/logs/b24cli-20261113.log')));
        self::assertSame([], $warnings);
        if (PHP_OS_FAMILY !== 'Windows') {
            self::assertSame(0700, fileperms($this->directory . '/logs') & 0777);
            self::assertSame(0600, fileperms($this->directory . '/logs/b24cli-20261112.log') & 0777);
        }
    }

    public function testExistingDailyFileSymlinkIsNeverFollowed(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('Symbolic links need extra privileges on Windows.');
        }

        $this->protection->directory($this->directory . '/logs');
        $target = $this->directory . '/victim.txt';
        file_put_contents($target, 'original');
        symlink($target, $this->directory . '/logs/b24cli-20261112.log');
        symlink($this->directory . '/missing.txt', $this->directory . '/logs/b24cli-20261113.log');

        foreach (['2026-11-12T10:00:00+00:00', '2026-11-13T10:00:00+00:00'] as $datetime) {
            $warnings = [];
            $handler = $this->handler($this->directory . '/logs', 7, $warnings);
            $handler->handle($this->record($datetime));
            $handler->handle($this->record($datetime));
            $handler->close();
            self::assertCount(1, $warnings);
        }

        self::assertSame('original', file_get_contents($target));
        self::assertFileDoesNotExist($this->directory . '/missing.txt');
    }

    public function testRetentionByDaysKeepsForeignFiles(): void
    {
        $this->protection->directory($this->directory . '/logs');
        foreach (['b24cli-20261101.log', 'b24cli-20261105.log', 'b24cli-20261106.log', 'b24cli-20261112.log', 'notes.txt', 'b24cli-latest.log', 'b24cli-20261399.log'] as $file) {
            touch($this->directory . '/logs/' . $file);
        }

        $warnings = [];
        $handler = $this->handler($this->directory . '/logs', 7, $warnings);
        $handler->handle($this->record('2026-11-12T10:00:00+00:00'));
        $handler->close();

        $files = array_values(array_diff(scandir($this->directory . '/logs'), ['.', '..']));
        sort($files);
        self::assertSame(['b24cli-20261106.log', 'b24cli-20261112.log', 'b24cli-20261399.log', 'b24cli-latest.log', 'notes.txt'], $files);

        foreach (['b24cli-20261001.log', 'b24cli-20261002.log', 'b24cli-20261003.log'] as $file) {
            touch($this->directory . '/logs/' . $file);
        }

        // A file already deleted by another process is not an error.
        $other = $this->handler($this->directory . '/logs', 7, $warnings);
        unlink($this->directory . '/logs/b24cli-20261001.log');
        $other->handle($this->record('2026-11-12T11:00:00+00:00'));
        $other->close();
        self::assertFileDoesNotExist($this->directory . '/logs/b24cli-20261002.log');
        self::assertSame([], $warnings);
    }

    public function testLevelFilteringAndSdkSummaryAboveDebug(): void
    {
        $this->config(['logging' => ['enabled' => true, 'level' => 'warning']]);
        $runtime = $this->runtime();
        $runtime->sdkLogger()->debug('call.start', ['apiMethod' => 'tasks.task.add']);
        $runtime->sdkLogger()->info('getResponse.start', ['apiMethod' => 'tasks.task.add']);
        $runtime->sdkLogger()->error('call.unknownException', ['message' => 'boom', 'trace' => [['file' => 'x']], 'responseInfo' => ['url' => 'x']]);
        $runtime->close();
        $records = $this->records();
        self::assertSame(['call.unknownException'], array_column($records, 'message'));
        self::assertSame(['message' => 'boom'], $records[0]['context']);

        $this->clean();
        $this->config(['logging' => ['enabled' => true]]);
        $runtime = $this->runtime();
        $logger = $runtime->sdkLogger();
        $logger->info('getResponse.start', ['apiMethod' => 'tasks.task.add', 'domainUrl' => 'https://portal.invalid', 'parameters' => ['fields' => ['TITLE' => 'T', 'DESCRIPTION' => 'Секретный договор']], 'apiVersion' => 1, 'requestId' => 'r1']);
        $logger->info('getResponse.end', ['apiMethod' => 'tasks.task.add', 'responseInfo' => ['http_code' => 200, 'url' => self::WEBHOOK . 'tasks.task.add'], 'requestId' => 'r1']);

        $runtime->close();
        $raw = (string) file_get_contents($this->logFile());
        self::assertStringNotContainsString('Секретный договор', $raw);
        self::assertStringNotContainsString('parameters', $raw);
        self::assertStringNotContainsString('responseInfo', $raw);
        $records = $this->records();
        self::assertSame(['apiMethod' => 'tasks.task.add', 'apiVersion' => 1, 'requestId' => 'r1'], $records[0]['context']);
        self::assertSame('tasks.task.add', $records[1]['context']['apiMethod']);
        self::assertSame(200, $records[1]['context']['httpStatus']);
        self::assertIsInt($records[1]['context']['durationMs']);
    }

    public function testSdkRequestThroughClientProviderIsSummarized(): void
    {
        $this->config(['logging' => ['enabled' => true]]);
        $runtime = $this->runtime();
        $runtimeState = new RuntimeState();
        $b24ClientProvider = new B24ClientProvider(new SpyConnectionResolver(), $runtimeState, new MockHttpClient(new MockResponse('{"result":{"ID":1},"time":{"start":1,"finish":1,"duration":0,"processing":0,"date_start":"2026-11-12T10:00:00+00:00","date_finish":"2026-11-12T10:00:00+00:00"}}', ['http_code' => 200])), $runtime);
        $b24ClientProvider->get()->core->call('user.current');
        $runtime->close();
        $raw = (string) file_get_contents($this->logFile());
        self::assertStringNotContainsString('secret-fixture', $raw);
        $messages = array_column($this->records(), 'message');
        self::assertContains('getResponse.start', $messages);
        self::assertContains('getResponse.end', $messages);
    }

    public function testDebugDataIsBoundedAndSecretsAreRedacted(): void
    {
        $this->config(['logging' => ['enabled' => true, 'level' => 'debug']]);
        $runtime = $this->runtime();
        $runtime->secret('https://old.invalid/rest/3/PreviousGenerationToken/');
        $runtime->secret(self::WEBHOOK);
        $runtime->secret('LoggingFixtureSecret42');

        $file = base64_encode(random_bytes(3 * 1024 * 1024));
        $runtime->sdkLogger()->debug('getResponse.requestPayload ' . self::WEBHOOK, [
            'url' => self::WEBHOOK . 'disk.folder.uploadfile',
            'requestOptions' => ['json' => ['fileContent' => [$file], 'note' => str_repeat('я', 5000), 'binary' => random_bytes(64)]],
            'access_token' => 'a', 'refreshToken' => 'b', 'authorId' => 5, 'client_secret' => 'c',
            'old' => 'see https://old.invalid/rest/3/PreviousGenerationToken/x',
            'other' => 'https://elsewhere.invalid/rest/9/UnknownToken99/user.get',
        ]);
        $runtime->close();
        $raw = (string) file_get_contents($this->logFile());
        self::assertLessThanOrEqual(JsonLinesFormatter::MAX_RECORD_BYTES, strlen($raw));
        foreach (['LoggingFixtureSecret42', 'PreviousGenerationToken', 'UnknownToken99', substr($file, 0, 64)] as $secret) {
            self::assertStringNotContainsString($secret, $raw);
        }

        $context = $this->records()[0]['context'];
        self::assertSame('[base64 ' . strlen($file) . ' bytes]', $context['requestOptions']['json']['fileContent'][0]);
        self::assertStringEndsWith('…[truncated ' . (10000 - SizeLimitProcessor::MAX_STRING_BYTES) . ' bytes]', $context['requestOptions']['json']['note']);
        self::assertSame('[binary 64 bytes]', $context['requestOptions']['json']['binary']);
        self::assertSame([SecretRedactingProcessor::MASK, SecretRedactingProcessor::MASK, 5, SecretRedactingProcessor::MASK], [$context['access_token'], $context['refreshToken'], $context['authorId'], $context['client_secret']]);
    }

    public function testParallelRunsAreSeparatedByRunId(): void
    {
        $this->config(['logging' => ['enabled' => true]]);
        $first = $this->runtime();
        $second = $this->runtime();
        for ($i = 0; $i < 20; $i++) {
            $first->logger()->info('first', ['i' => $i]);
            $second->logger()->info('second', ['i' => $i]);
        }

        $first->close();
        $second->close();
        $records = $this->records();
        self::assertCount(40, $records);
        $byRun = [];
        foreach ($records as $record) {
            $byRun[$record['runId']][] = $record['message'];
        }

        self::assertSame([$first->runId() => array_fill(0, 20, 'first'), $second->runId() => array_fill(0, 20, 'second')], $byRun);
    }

    public function testConnectionShowReportsEffectiveLogging(): void
    {
        putenv('BITRIX24_WEBHOOK=' . self::WEBHOOK);
        $logging = static fn (ConsoleRun $run): array => $run->json()['data']['items'][0]['logging'];
        self::assertSame('disabled', $logging($this->cli(['connection:show', '--json']))['status']);

        $this->config(['logging' => ['enabled' => true, 'directory' => 'var/log', 'retentionDays' => 3]]);
        putenv('TZ=UTC');
        $shown = $logging($this->cli(['connection:show', '--json']));
        self::assertSame(['status' => 'ok', 'reason' => null, 'enabled' => true, 'level' => 'info', 'directory' => $this->directory . '/var/log', 'fileMask' => 'b24cli-{YYYY}{MM}{DD}.log', 'retentionDays' => 3], array_diff_key($shown, ['currentFile' => true]));
        self::assertSame($this->directory . '/var/log/b24cli-' . gmdate('Ymd') . '.log', $shown['currentFile']);
        // Computing the block creates nothing; the command itself logs its lifecycle when logging is enabled.
        self::assertSame(['command.start', 'command.finish'], array_column(array_map(static fn (string $line): array => json_decode($line, true), file($shown['currentFile'], FILE_IGNORE_NEW_LINES)), 'message'));

        $this->config(['logging' => ['retentionDays' => 0]]);
        $run = $this->cli(['connection:show', '--plain']);
        self::assertSame(0, $run->status);
        self::assertStringContainsString('"status":"invalid"', $run->stdout);
        self::assertStringContainsString('retentionDays', $run->stdout);
    }

    private function dryRun(array $extra = []): ConsoleRun
    {
        return $this->cli([...$extra, 'task:add', '--title', 'T', '--creator', '1', '--responsible', '2', '--dry-run', '--json']);
    }

    private function cli(array $argv, ?string $stdin = null): ConsoleRun
    {
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), new FakeApiTransport(static fn () => throw new \LogicException('Unexpected remote call.')), packaged: true);
        return ConsoleHarness::run($app, $argv, $stdin);
    }

    private function runtime(): LoggingRuntime
    {
        $loggingRuntime = new LoggingRuntime(new LoggingSettingsReader(new ConfigDirectoryResolver()), $this->protection);
        $loggingRuntime->activate(null, static function (string $message): never {
            throw new \LogicException($message);
        });
        return $loggingRuntime;
    }

    /**
     * @param list<string> $warnings
     */
    private function handler(string $directory, int $days, array &$warnings, ?\DateTimeZone $timezone = null): DailyMaskFileHandler
    {
        $dailyMaskFileHandler = new DailyMaskFileHandler($directory, LoggingSettings::DEFAULTS['fileMask'], $days, $timezone ?? new \DateTimeZone('UTC'), $this->protection, static function (string $message) use (&$warnings): void {
            $warnings[] = $message;
        });
        $dailyMaskFileHandler->setFormatter(new JsonLinesFormatter('test'));
        return $dailyMaskFileHandler;
    }

    private function record(string $datetime): LogRecord
    {
        return new LogRecord(new \DateTimeImmutable($datetime), 'cli', Level::Info, 'm');
    }

    private function config(array $values): void
    {
        $this->protection->directory($this->directory);
        $path = $this->directory . '/config.json';
        if (file_exists($path)) {
            unlink($path);
        }

        $stream = $this->protection->create($path);
        fwrite($stream, (string) json_encode(['version' => 1, 'defaultProfile' => null, 'profiles' => new \stdClass(), ...$values], JSON_UNESCAPED_SLASHES));
        fclose($stream);
    }

    private function clean(): void
    {
        $this->tearDownDirectory();
    }

    private function tearDownDirectory(): void
    {
        $environment = $this->environment;
        $this->environment = [];
        $this->tearDown();
        $this->environment = $environment;
        putenv('B24CLI_CONFIG_DIR=' . $this->directory);
    }

    private function logFile(): string
    {
        $files = glob($this->directory . '/logs/b24cli-*.log') ?: [];
        self::assertCount(1, $files);
        return $files[0];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function records(): array
    {
        $lines = file($this->logFile(), FILE_IGNORE_NEW_LINES) ?: [];
        return array_map(static fn (string $line): array => json_decode($line, true, 64, JSON_THROW_ON_ERROR), $lines);
    }
}
