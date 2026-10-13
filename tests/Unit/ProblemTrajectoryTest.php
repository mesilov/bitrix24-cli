<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Doctor\FeedbackGateway;
use Bitrix24\CLI\Application\Doctor\FeedbackReceipt;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Console\B24Application;
use Bitrix24\CLI\Infrastructure\Connection\FileProtection;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\ConsoleRun;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\FakeDoctorFeedbackGateway;
use Bitrix24\CLI\Tests\Support\MutableDoctorClock;
use Bitrix24\CLI\Tests\Support\ProblemTrajectoryFixtures;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProblemTrajectoryTest extends TestCase
{
    private string $directory;
    private MutableDoctorClock $clock;
    private SpyConnectionResolver $resolver;
    private FakeApiTransport $transport;
    private B24Application $app;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/b24cli-doctor-Конфиг space-' . bin2hex(random_bytes(6));
        $this->clock = new MutableDoctorClock();
        $this->resolver = new SpyConnectionResolver();
        $this->transport = new FakeApiTransport(static fn () => throw new \LogicException('Doctor must remain offline.'));
        $this->app = $this->application();
    }

    protected function tearDown(): void
    {
        self::assertSame(0, $this->resolver->reads);
        self::assertSame([], $this->transport->calls);
        if (is_dir($this->directory)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }

            rmdir($this->directory);
        }
    }

    private function application(?FeedbackGateway $gateway = null): B24Application
    {
        return (new ApplicationFactory())->create(dirname(__DIR__, 2), $this->transport, $this->resolver, true, clock: $this->clock, feedbackGateway: $gateway);
    }

    private function invoke(string $action, ?array $payload = null, array $extra = [], ?B24Application $app = null): ConsoleRun
    {
        $argv = ['--json', '--config-dir', $this->directory, 'doctor:problem-trajectory', '--action', $action];
        if ($payload !== null) {
            $option = $action === 'record' ? '--report-file' : ($action === 'configure' ? '--settings-file' : '--batch-file');
            $argv = [...$argv, $option, '-'];
        }

        return ConsoleHarness::run($app ?? $this->app, [...$argv, ...$extra], $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function record(?array $report = null, array $extra = []): string
    {
        $run = $this->invoke('record', $report ?? ProblemTrajectoryFixtures::report(), $extra);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        return $run->json()['data']['cardId'];
    }

    private function analyze(array $ids, string $batch = 'fixture-batch', string $decision = 'candidate'): string
    {
        $run = $this->invoke('record-analysis', ProblemTrajectoryFixtures::batch($ids, $batch, $decision));
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        return $run->json()['data']['groups'][0]['groupRef'];
    }

    public function testStatusInventoryAndHelpRemainReadOnlyWithoutCredentials(): void
    {
        foreach ([['--version'], ['list', '--raw'], ['help', 'doctor:problem-trajectory']] as $argv) {
            self::assertSame(0, ConsoleHarness::run($this->app, ['--config-dir', $this->directory, ...$argv])->status);
        }

        $run = $this->invoke('status');
        self::assertSame(0, $run->status);
        self::assertSame(['collectProblemTrajectories' => true, 'maxProblemTrajectoryFiles' => 100, 'lastProblemTrajectoryProcessingAt' => null, 'lastProblemTrajectoryAnalysisOfferedAt' => null], $run->json()['data']['telemetry']);
        self::assertFalse($run->json()['data']['analysisDue']);
        self::assertDirectoryDoesNotExist($this->directory);
    }

    public function testRecordCreatesPrivateConfigurationAndRetryIsIdempotent(): void
    {
        $id = $this->record(extra: ['--idempotency-key', 'episode-one']);
        $run = $this->invoke('record', ProblemTrajectoryFixtures::report(), ['--idempotency-key', 'episode-one']);
        self::assertSame($id, $run->json()['data']['cardId']);
        self::assertTrue($run->json()['data']['duplicate']);
        $config = json_decode(file_get_contents($this->directory . '/config.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertNull($config['defaultProfile']);
        self::assertSame([], $config['profiles']);
        self::assertTrue($config['telemetry']['collectProblemTrajectories']);
        self::assertFileDoesNotExist($this->directory . '/credentials');
        if (PHP_OS_FAMILY !== 'Windows') {
            self::assertSame(0700, fileperms($this->directory . '/.problem-trajectories') & 0777);
            self::assertSame(0600, fileperms($this->directory . '/.problem-trajectories/' . $id . '.json') & 0777);
        }

        $changed = ProblemTrajectoryFixtures::report();
        $changed['expectedOutcome'] = 'Different expected result';
        self::assertSame(2, $this->invoke('record', $changed, ['--idempotency-key', 'episode-one'])->status);
        self::assertSame(1, $this->invoke('status')->json()['data']['fileCount']);
    }

    public static function privateValues(): iterable
    {
        foreach (['https://portal.invalid/rest/1/RecognizableFixtureSecret/', 'person@example.test', '/Users/private/person/secret', 'C:\\Private\\record.txt', 'token=RecognizableFixtureSecret', 'Bearer RecognizableFixtureSecret', '+79991234567', 'responsibleId=123', 'python -c dangerous', '```code```', '$(dangerous)'] as $value) {
            yield $value => [$value];
        }
    }

    #[DataProvider('privateValues')]
    public function testSensitiveInputNeverEntersStorageOrOutput(string $value): void
    {
        $report = ProblemTrajectoryFixtures::report();
        $report['sanitizedScenario'] = $value;
        $run = $this->invoke('record', $report);
        self::assertSame(2, $run->status, $run->stdout);
        self::assertStringNotContainsString($value, $run->stdout . $run->stderr);
        self::assertDirectoryDoesNotExist($this->directory);
    }

    public function testToggleAndLimitPreserveUnfinishedAndMockCards(): void
    {
        self::assertSame(0, $this->invoke('configure', ['collectProblemTrajectories' => false, 'maxProblemTrajectoryFiles' => 1])->status);
        self::assertSame('collection-disabled', $this->invoke('record', ProblemTrajectoryFixtures::report())->json()['error']['code']);
        self::assertSame(0, $this->invoke('configure', ['collectProblemTrajectories' => true])->status);
        $id = $this->record();
        self::assertSame('storage-full', $this->invoke('record', ProblemTrajectoryFixtures::report())->json()['error']['code']);
        $ref = $this->analyze([$id]);
        $mock = $this->invoke('submit', ['groupRefs' => [$ref]], ['--mock-feedback', '--confirm-publication']);
        self::assertSame(0, $mock->status, $mock->stdout);
        self::assertTrue($mock->json()['data']['mocked']);
        self::assertFalse($mock->json()['data']['delivered']);
        self::assertSame('storage-full', $this->invoke('record', ProblemTrajectoryFixtures::report())->json()['error']['code']);
        $this->analyze([$id], 'dismiss-old', 'dismissed');
        $next = $this->record();
        self::assertNotSame($id, $next);
        self::assertFileDoesNotExist($this->directory . '/.problem-trajectories/' . $id . '.json');
        self::assertSame(1, $this->invoke('status')->json()['data']['fileCount']);
    }

    public function testRecognizableSecretsInTechnicalMetadataAreAlsoRejected(): void
    {
        foreach (['cliVersion', 'skillVersion'] as $field) {
            $report = ProblemTrajectoryFixtures::report();
            $report[$field] = 'sk-RecognizableFixtureToken';
            $run = $this->invoke('record', $report);
            self::assertSame(2, $run->status);
            self::assertStringNotContainsString('RecognizableFixtureToken', $run->stdout . $run->stderr);
        }

        $report = ProblemTrajectoryFixtures::report();
        $report['availableCommands'] = ['sk-privatetoken'];
        self::assertSame(2, $this->invoke('record', $report)->status);
        self::assertDirectoryDoesNotExist($this->directory);
    }

    public function testWeeklyOfferUsesSeparateDatesAndExactUtcBoundary(): void
    {
        $first = $this->record();
        self::assertTrue($this->invoke('status')->json()['data']['analysisDue']);
        $offered = $this->invoke('record-offer');
        self::assertSame(0, $offered->status);
        self::assertNull($this->invoke('status')->json()['data']['telemetry']['lastProblemTrajectoryProcessingAt']);
        $this->clock->time = new \DateTimeImmutable('2026-10-19T23:59:59Z');
        self::assertFalse($this->invoke('status')->json()['data']['analysisDue']);
        $this->clock->time = new \DateTimeImmutable('2026-10-20T06:00:00+06:00');
        self::assertTrue($this->invoke('status')->json()['data']['analysisDue']);
        $second = $this->record();
        $this->analyze([$first]);
        $status = $this->invoke('status')->json()['data'];
        self::assertSame('2026-10-20T00:00:00Z', $status['telemetry']['lastProblemTrajectoryProcessingAt']);
        self::assertSame('2026-10-13T00:00:00Z', $status['telemetry']['lastProblemTrajectoryAnalysisOfferedAt']);
        self::assertSame(1, $status['counts']['pending']);
        self::assertSame($second, $this->invoke('list')->json()['data']['items'][0]['cardId']);
        self::assertFalse($status['analysisDue']);
    }

    public static function batchSizes(): iterable
    {
        foreach ([1, 2, 3, 20, 50] as $size) {
            yield (string) $size => [$size];
        }
    }

    #[DataProvider('batchSizes')]
    public function testOfflineBatchCycleAndDefaultUnavailableEndpoint(int $size): void
    {
        $ids = [];
        for ($i = 0; $i < $size; $i++) {
            $ids[] = $this->record();
        }

        $ref = $this->analyze($ids);
        self::assertSame(2, $this->invoke('submit', ['groupRefs' => [$ref]], ['--mock-feedback'])->status);
        self::assertSame('endpoint-unavailable', $this->invoke('submit', ['groupRefs' => [$ref]], ['--confirm-publication'])->json()['error']['code']);
        $app = $this->application();
        $run = $this->invoke('submit', ['groupRefs' => [$ref]], ['--confirm-publication', '--mock-feedback'], $app);
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame('mock-submitted', $run->json()['data']['groups'][0]['state']);
        $receipt = $run->json()['data']['groups'][0]['feedback']['receiptId'];
        $again = $this->invoke('reconcile', ['groupRefs' => [$ref]], ['--mock-feedback'], $this->application());
        self::assertSame(0, $again->status);
        self::assertSame($receipt, $again->json()['data']['groups'][0]['feedback']['receiptId']);
        self::assertSame($size, $this->invoke('status')->json()['data']['counts']['mock-submitted']);
    }

    public function testInvalidSnapshotLeavesDatesAndAllCardsUnchanged(): void
    {
        $id = $this->record();
        $batch = ProblemTrajectoryFixtures::batch([$id, str_repeat('a', 32)]);
        self::assertSame(2, $this->invoke('record-analysis', $batch)->status);
        self::assertNull($this->invoke('status')->json()['data']['telemetry']['lastProblemTrajectoryProcessingAt']);
        self::assertSame('pending', $this->invoke('list')->json()['data']['items'][0]['state']);
        $ref = $this->analyze([$id]);
        $this->clock->time = $this->clock->time->modify('+1 day');
        $retry = $this->invoke('record-analysis', ProblemTrajectoryFixtures::batch([$id]));
        self::assertTrue($retry->json()['data']['duplicate']);
        self::assertSame('2026-10-13T00:00:00Z', $this->invoke('status')->json()['data']['telemetry']['lastProblemTrajectoryProcessingAt']);
        self::assertSame($ref, $retry->json()['data']['groups'][0]['groupRef']);
    }

    public function testDefaultHundredCardLimitAndCorruptFilesAreNeverSilentlyDiscarded(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->record();
        }

        self::assertSame('storage-full', $this->invoke('record', ProblemTrajectoryFixtures::report())->json()['error']['code']);
        self::assertSame(100, $this->invoke('status')->json()['data']['fileCount']);
        self::assertSame(0, $this->invoke('configure', ['maxProblemTrajectoryFiles' => 1])->status);
        self::assertSame(100, $this->invoke('status')->json()['data']['fileCount']);
        $files = glob($this->directory . '/.problem-trajectories/*.json');
        file_put_contents($files[0], 'damaged-json');
        $run = $this->invoke('status');
        self::assertSame(3, $run->status);
        self::assertSame(1, $run->json()['data']['corruptFileCount']);
        self::assertSame('storage-full', $this->invoke('record', ProblemTrajectoryFixtures::report())->json()['error']['code']);
        self::assertSame('damaged-json', file_get_contents($files[0]));
    }

    public function testLostFeedbackReplyIsReconciledWithoutAnotherSubmissionOrSecretLeak(): void
    {
        $first = $this->record();
        $second = $this->record();
        $batch = ProblemTrajectoryFixtures::batch([$first], 'partial-batch');
        $group = ProblemTrajectoryFixtures::batch([$second])['groups'][0];
        $group['groupId'] = 'second';
        $batch['groups'][] = $group;
        $analysis = $this->invoke('record-analysis', $batch);
        self::assertSame(0, $analysis->status);
        $refs = array_column($analysis->json()['data']['groups'], 'groupRef');
        $fakeDoctorFeedbackGateway = new FakeDoctorFeedbackGateway();
        $fakeDoctorFeedbackGateway->outcomes = ['accepted', 'lost'];

        $run = $this->invoke('submit', ['groupRefs' => $refs], ['--confirm-publication'], $this->application($fakeDoctorFeedbackGateway));
        self::assertSame(3, $run->status, $run->stdout);
        self::assertTrue($run->json()['meta']['outcomeUnknown']);
        self::assertSame('submitted', $run->json()['data']['groups'][0]['state']);
        self::assertSame('submission-unknown', $run->json()['data']['groups'][1]['state']);
        self::assertSame(2, $fakeDoctorFeedbackGateway->submissions);
        $retry = $this->invoke('submit', ['groupRefs' => $refs], ['--confirm-publication'], $this->application($fakeDoctorFeedbackGateway));
        self::assertSame(3, $retry->status);
        self::assertSame(2, $fakeDoctorFeedbackGateway->submissions);
        $reconciled = $this->invoke('reconcile', ['groupRefs' => $refs], [], $this->application($fakeDoctorFeedbackGateway));
        self::assertSame(0, $reconciled->status);
        self::assertSame(2, $fakeDoctorFeedbackGateway->submissions);
        $listing = $this->invoke('list', extra: ['--state', 'all']);
        foreach ([$run, $retry, $reconciled, $listing, $this->invoke('status')] as $output) {
            self::assertStringNotContainsString('PrivateFixtureTokenNeverPublic', $output->stdout . $output->stderr);
        }

        $private = glob($this->directory . '/.problem-trajectories/private/*.json');
        self::assertCount(2, $private);
        self::assertStringContainsString('PrivateFixtureTokenNeverPublic', file_get_contents($private[0]));
        foreach (glob($this->directory . '/.problem-trajectories/*.json') as $path) {
            self::assertStringNotContainsString('PrivateFixtureTokenNeverPublic', file_get_contents($path));
        }
    }

    public function testUncheckedDeduplicationAndSupersededSnapshotCannotBeSubmitted(): void
    {
        $id = $this->record();
        $batch = ProblemTrajectoryFixtures::batch([$id], deduplication: 'unchecked');
        $analysis = $this->invoke('record-analysis', $batch);
        $ref = $analysis->json()['data']['groups'][0]['groupRef'];
        self::assertSame(2, $this->invoke('submit', ['groupRefs' => [$ref]], ['--mock-feedback', '--confirm-publication'])->status);
        $old = $this->analyze([$id], 'old-analysis');
        $this->analyze([$id], 'new-analysis');
        self::assertSame(2, $this->invoke('submit', ['groupRefs' => [$old]], ['--mock-feedback', '--confirm-publication'])->status);
    }

    public function testInterruptedJournalRecoveryRetainsConfigurationAndPendingCards(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('POSIX fault injection; Windows protection is covered by its platform harness.');
        }

        $id = $this->record();
        chmod($this->directory, 0500);
        try {
            self::assertSame(1, $this->invoke('configure', ['collectProblemTrajectories' => false])->status);
        } finally {
            chmod($this->directory, 0700);
        }

        self::assertSame('storage-recovery-required', $this->invoke('status')->json()['error']['code']);
        self::assertSame(0, $this->invoke('recover')->status);
        self::assertFalse($this->invoke('status')->json()['data']['telemetry']['collectProblemTrajectories']);
        self::assertSame($id, $this->invoke('list')->json()['data']['items'][0]['cardId']);
        self::assertFileDoesNotExist($this->directory . '/.problem-trajectories/.journal');
    }

    public function testSymlinksAndUnsafePermissionsAreRejectedWithoutFollowingThem(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('POSIX symbolic-link setup.');
        }

        $id = $this->record();
        $path = $this->directory . '/.problem-trajectories/' . $id . '.json';
        rename($path, $path . '.original');
        symlink($path . '.original', $path);
        self::assertSame('configuration-error', $this->invoke('status')->json()['error']['code']);
        unlink($path);
        rename($path . '.original', $path);
        chmod($path, 0644);
        self::assertSame('configuration-error', $this->invoke('list')->json()['error']['code']);
        chmod($path, 0600);
    }

    public function testConcurrentRecordsAndProfileMutationPreserveBothKindsOfState(): void
    {
        $this->invoke('configure', ['maxProblemTrajectoryFiles' => 10]);
        $processes = [];
        foreach (['record', 'record', 'profile'] as $index => $mode) {
            $process = proc_open([PHP_BINARY, __DIR__ . '/../Support/problem-trajectory-worker.php', $this->directory, $mode, 'concurrent-' . $index], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            fclose($pipes[0]);
            $processes[] = [$process, $pipes];
        }

        foreach ($processes as [$process, $pipes]) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $stdout . $stderr);
        }

        $config = json_decode(file_get_contents($this->directory . '/config.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('fixture', $config['profiles']);
        self::assertSame(10, $config['telemetry']['maxProblemTrajectoryFiles']);
        self::assertSame(2, $this->invoke('status')->json()['data']['counts']['pending']);
    }

    public function testInputBoundsContainersAndStdinConflictsAreRejectedBeforeStorage(): void
    {
        $report = ProblemTrajectoryFixtures::report();
        $report['availableCommands'] = (object) [];
        self::assertSame(2, $this->invoke('record', $report)->status);
        $report = ProblemTrajectoryFixtures::report();
        $report['sanitizedScenario'] = str_repeat('x', 65537);
        self::assertSame(2, $this->invoke('record', $report)->status);
        $deep = 'synthetic';
        for ($level = 0; $level < 70; $level++) {
            $deep = [$deep];
        }

        $report = ProblemTrajectoryFixtures::report();
        $report['wrapperAlgorithm'] = $deep;
        self::assertSame(2, $this->invoke('record', $report)->status);
        self::assertSame(2, $this->invoke('record', ProblemTrajectoryFixtures::report(), ['--config', '-'])->status);
        self::assertSame(2, $this->invoke('status', extra: ['--mock-feedback'])->status);
        self::assertDirectoryDoesNotExist($this->directory);
    }
}
