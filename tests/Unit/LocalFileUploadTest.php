<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Bootstrap\CommandCatalog;
use Bitrix24\CLI\Console\B24Application;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiTransport;
use Bitrix24\CLI\Infrastructure\Filesystem\LocalFileSource;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use Bitrix24\SDK\Infrastructure\Filesystem\Base64Encoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

final class LocalFileUploadTest extends TestCase
{
    private string $directory;
    private string $path;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/b24cli-upload-' . bin2hex(random_bytes(6));
        $this->filesystem->mkdir($this->directory);
        $this->path = $this->directory . '/Отчёт с пробелом.bin';
        $this->filesystem->dumpFile($this->path, "SECRET-BINARY\0\xff\n" . str_repeat('x', 100));
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    private function source(): LocalFileSource
    {
        return new LocalFileSource($this->filesystem, new Base64Encoder($this->filesystem, new \Symfony\Component\Mime\Encoder\Base64Encoder(), new NullLogger()));
    }

    private function arguments(): array
    {
        return ['task:file:attach', '123', '--file', $this->path, '--folder-id', '456'];
    }

    private function app(\Closure $respond, ?RuntimeState $state = null): array
    {
        $containerBuilder = new ContainerBuilder();
        $configure = require dirname(__DIR__, 2) . '/config/services.php';
        $configure($containerBuilder, dirname(__DIR__, 2));
        $containerBuilder->removeAlias(ApiTransport::class);
        $containerBuilder->register(ApiTransport::class)->setSynthetic(true)->setPublic(true);
        if ($state instanceof \Bitrix24\CLI\Application\RuntimeState) {
            $containerBuilder->register(RuntimeState::class)->setSynthetic(true)->setPublic(true);
        }

        $containerBuilder->compile();
        if ($state instanceof \Bitrix24\CLI\Application\RuntimeState) {
            $containerBuilder->set(RuntimeState::class, $state);
        }

        $api = new FakeApiTransport(static function ($method, $version, $params, $effect) use ($state, $respond): ApiResponse {
            if ($state instanceof \Bitrix24\CLI\Application\RuntimeState) {
                $state->checkCancellation();
                $state->calls[] = ['method' => $method, 'apiVersion' => $version . '.0', 'effect' => $effect];
            }

            return $respond($method, $version, $params, $effect);
        });
        $containerBuilder->set(ApiTransport::class, $api);
        $app = $containerBuilder->get(B24Application::class);
        $app->setCommandLoader(new ContainerCommandLoader($containerBuilder, array_map(static fn (array $command) => $command['class'], CommandCatalog::all())));
        return [$app, $api];
    }

    private static function success(string $method): ApiResponse
    {
        return new ApiResponse($method === 'disk.folder.uploadFile' ? ['ID' => '789', 'FILE_ID' => '999', 'NAME' => 'Actual.bin', 'DOWNLOAD_URL' => 'https://secret.example/download?token=SECRET'] : ['result' => true]);
    }

    public function testExactBytesSdkEncodingAndDiskIdAreUsed(): void
    {
        [$app, $api] = $this->app(self::success(...), new RuntimeState());
        $run = ConsoleHarness::run($app, ['--json', ...$this->arguments(), '--name', 'Chosen.bin']);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertCount(2, $api->calls);
        $upload = $api->calls[0];
        self::assertSame(['method' => 'disk.folder.uploadFile', 'version' => 1, 'effect' => 'write'], array_diff_key($upload, ['parameters' => null]));
        self::assertSame(['id', 'data', 'fileContent', 'generateUniqueName'], array_keys($upload['parameters']));
        self::assertSame(456, $upload['parameters']['id']);
        self::assertSame(['NAME' => 'Chosen.bin'], $upload['parameters']['data']);
        self::assertSame('Chosen.bin', $upload['parameters']['fileContent'][0]);
        self::assertSame(file_get_contents($this->path), base64_decode($upload['parameters']['fileContent'][1], true));
        self::assertTrue($upload['parameters']['generateUniqueName']);
        self::assertSame(['taskId' => 123, 'fileIds' => [789]], $api->calls[1]['parameters']);
        $json = $run->json();
        self::assertSame(['upload', 'attach'], array_column($json['data']['items'], 'action'));
        self::assertSame('Actual.bin', $json['data']['items'][0]['NAME']);
        self::assertSame(['1.0', '3.0'], $json['meta']['apiVersions']);
        self::assertSame(['confirmed', 'confirmed'], array_column($json['meta']['ledger'], 'status'));
        self::assertStringNotContainsString('SECRET', $run->stdout . $run->stderr);
    }

    public static function invalidModes(): iterable
    {
        yield 'missing folder' => [['--file', '@path']];
        yield 'mixed' => [['--file', '@path', '--folder-id', '456', '--file-id', '789']];
        yield 'folder only' => [['--folder-id', '456']];
        yield 'name with IDs' => [['--file-id', '789', '--name', 'x']];
        foreach (['0', '-1', '1.2', 'abc', '99999999999999999999999999'] as $id) {
            yield 'folder ' . $id => [['--file', '@path', '--folder-id', $id]];
        }

        foreach (['', '.', '..', '../x', 'a\\b', "a\n", "a\x7f", "a\u{0085}", "\xff", str_repeat('я', 256)] as $name) {
            yield 'name ' . bin2hex($name) => [['--file', '@path', '--folder-id', '456', '--name', $name]];
        }

        foreach (['-', 'http://example.test/a', 'data:text/plain,x', 'php://memory', '/missing-upload-file', "a\0b"] as $path) {
            yield 'path ' . bin2hex($path) => [['--file', $path, '--folder-id', '456']];
        }
    }

    #[DataProvider('invalidModes')]
    public function testInvalidLocalInputPrecedesCredentialsAndApi(array $options): void
    {
        $fakeApiTransport = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected API call'));
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        $options = array_map(fn ($value) => $value === '@path' ? $this->path : $value, $options);
        $run = ConsoleHarness::run($app, ['--json', 'task:file:attach', '123', ...$options]);
        self::assertSame(2, $run->status, $run->stdout . $run->stderr);
        self::assertSame('usage-error', $run->json()['error']['code']);
        self::assertSame([], $fakeApiTransport->calls);
        self::assertSame(0, $spyConnectionResolver->reads);
    }

    public function testOfflinePreviewAndStrictGateDoNotReadCredentials(): void
    {
        $fakeApiTransport = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected API call'));
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        foreach (['--json', '--plain', null] as $format) {
            $run = ConsoleHarness::run($app, [...array_filter([$format]), ...$this->arguments(), '--dry-run']);
            self::assertSame(0, $run->status, $run->stdout . $run->stderr);
            self::assertStringContainsString('disk.folder.uploadFile', $run->stdout);
            self::assertStringContainsString('tasks.task.file.attach', $run->stdout);
            self::assertStringNotContainsString('SECRET', $run->stdout . $run->stderr);
        }

        foreach ([[], ['--dry-run']] as $extra) {
            $run = ConsoleHarness::run($app, ['--json', '--api-policy', 'strict-rest3', ...$this->arguments(), ...$extra]);
            self::assertSame(4, $run->status, $run->stdout);
            self::assertSame('policy-denied', $run->json()['error']['code']);
        }

        self::assertSame([], $fakeApiTransport->calls);
        self::assertSame(0, $spyConnectionResolver->reads);
    }

    public function testSizeBoundsSpecialFilesAndSdkRoundTrip(): void
    {
        $source = $this->source();
        foreach ([0, LocalFileSource::DIRECT_ENCODING_BYTES] as $size) {
            $this->filesystem->dumpFile($this->path, str_repeat("\0", $size));
            $file = $source->inspect($this->path, str_repeat('я', 255));
            self::assertSame($size, $file->sizeBytes);
            self::assertSame(str_repeat("\0", $size), base64_decode($source->encode($file), true));
        }

        $handle = fopen($this->path, 'wb');
        ftruncate($handle, LocalFileSource::MAX_BYTES + 1);
        fclose($handle);
        foreach ([$this->path, $this->directory] as $path) {
            try {
                $source->inspect($path);
                self::fail('Invalid file accepted');
            } catch (Failure $failure) {
                self::assertSame(2, $failure->exitStatus);
            }
        }

        if (function_exists('posix_mkfifo')) {
            $fifo = $this->directory . '/pipe';
            self::assertTrue(posix_mkfifo($fifo, 0600));
            try {
                $source->inspect($fifo);
                self::fail('FIFO accepted');
            } catch (Failure $failure) {
                self::assertSame(2, $failure->exitStatus);
            }
        }

        self::assertTrue(Path::isLocal('C:\\Files\\Отчёт.bin'));
        self::assertTrue(Path::isLocal(Path::normalize('C:\\Files\\Отчёт.bin')));
    }

    public function testUnreadableAndDeletedFilesDoNotUpload(): void
    {
        $source = $this->source();
        $file = $source->inspect($this->path);
        $this->filesystem->chmod($this->path, 0000);
        try {
            $source->inspect($this->path);
            self::fail('Unreadable source accepted');
        } catch (Failure $failure) {
            self::assertSame(2, $failure->exitStatus);
        } finally {
            $this->filesystem->chmod($this->path, 0600);
        }

        $this->filesystem->remove($this->path);
        $this->expectException(Failure::class);
        $source->encode($file);
    }

    public function testCancelledBeforeUploadDoesNotSendEitherWrite(): void
    {
        $fakeApiTransport = new FakeApiTransport(static fn () => throw new \LogicException('Unexpected API call'));
        $runtimeState = new RuntimeState();
        $attachTaskFilesHandler = new \Bitrix24\CLI\Application\Task\Handler\AttachTaskFilesHandler(
            new \Bitrix24\CLI\Infrastructure\Bitrix24\Rest3TaskAdapter($fakeApiTransport),
            new \Bitrix24\CLI\Infrastructure\Bitrix24\Rest1DiskUploadAdapter($fakeApiTransport),
            $this->source(),
            $runtimeState,
        );
        $attachTaskFilesRequest = new \Bitrix24\CLI\Application\Task\Request\AttachTaskFilesRequest(123, [], $this->source()->inspect($this->path), 456);
        $operation = $attachTaskFilesHandler->prepare($attachTaskFilesRequest);
        $runtimeState->cancelled = true;
        try {
            ($operation->execute)();
            self::fail('Cancelled upload executed');
        } catch (Failure $failure) {
            self::assertSame(130, $failure->exitStatus);
            self::assertSame([], $fakeApiTransport->calls);
            self::assertFalse($failure->outcomeUnknown);
        }
    }

    public function testSymlinkToFileAndChangedSource(): void
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            $link = $this->directory . '/link.bin';
            $this->filesystem->symlink($this->path, $link);
            self::assertSame(file_get_contents($this->path), base64_decode($this->source()->encode($this->source()->inspect($link)), true));
        }

        $source = $this->source();
        $file = $source->inspect($this->path);
        $handle = fopen($this->path, 'wb');
        ftruncate($handle, LocalFileSource::MAX_BYTES + 1);
        fclose($handle);
        $this->expectException(Failure::class);
        $source->encode($file);
    }

    public static function attachmentFailures(): iterable
    {
        yield 'denied' => [new Failure('permission-denied', 'Denied'), 'failed', false, 3];
        yield 'unknown' => [new Failure('transport-error', 'Lost response', 1, [], true), 'unknown', true, 3];
        yield 'interrupted sent' => [new Failure('interrupted', 'Interrupted while awaiting response', 130, [], true), 'unknown', true, 130];
    }

    #[DataProvider('attachmentFailures')]
    public function testAttachFailureRetainsUploadedId(Failure $failure, string $status, bool $unknown, int $exit): void
    {
        [$app, $api] = $this->app(static fn ($method) => $method === 'disk.folder.uploadFile' ? self::success($method) : throw $failure);
        $run = ConsoleHarness::run($app, ['--json', ...$this->arguments()]);
        self::assertSame($exit, $run->status, $run->stdout);
        $json = $run->json();
        self::assertSame(789, $json['data']['items'][0]['resourceId']);
        self::assertSame(['confirmed', $status], array_column($json['meta']['ledger'], 'status'));
        self::assertFalse($json['meta']['complete']);
        self::assertSame($unknown, $json['meta']['outcomeUnknown']);
        self::assertCount(2, $api->calls);
    }

    public function testUploadFailuresNeverAttachAndMalformedResultsAreUnknown(): void
    {
        foreach ([[], ['ID' => '0', 'NAME' => 'x'], ['FILE_ID' => 999, 'NAME' => 'x'], ['ID' => '99999999999999999999999999', 'NAME' => 'x'], ['ID' => 789, 'NAME' => "\xff"]] as $response) {
            [$app, $api] = $this->app(static fn () => new ApiResponse($response));
            $run = ConsoleHarness::run($app, ['--json', ...$this->arguments()]);
            self::assertSame(1, $run->status, $run->stdout);
            self::assertTrue($run->json()['meta']['outcomeUnknown']);
            self::assertSame(['unknown', 'skipped'], array_column($run->json()['error']['details']['ledger'], 'status'));
            self::assertCount(1, $api->calls);
        }

        foreach ([new Failure('permission-denied', 'Denied'), new Failure('transport-error', 'Lost', 1, [], true), new Failure('interrupted', 'Interrupted awaiting upload', 130, [], true)] as $failure) {
            [$app, $api] = $this->app(static fn () => throw $failure);
            $run = ConsoleHarness::run($app, ['--json', ...$this->arguments()]);
            self::assertSame($failure->exitStatus, $run->status);
            self::assertSame($failure->outcomeUnknown, $run->json()['meta']['outcomeUnknown']);
            self::assertCount(1, $api->calls);
        }
    }

    public function testCancellationBetweenWritesPreservesUploadAndSkipsAttach(): void
    {
        $runtimeState = new RuntimeState();
        [$app, $api] = $this->app(static function ($method) use ($runtimeState): ApiResponse {
            $runtimeState->cancelled = true;
            return self::success($method);
        }, $runtimeState);
        $run = ConsoleHarness::run($app, ['--json', ...$this->arguments()]);
        self::assertSame(130, $run->status, $run->stdout);
        self::assertSame(789, $run->json()['data']['items'][0]['resourceId']);
        self::assertSame(['confirmed', 'skipped'], array_column($run->json()['meta']['ledger'], 'status'));
        self::assertFalse($run->json()['meta']['outcomeUnknown']);
        self::assertCount(1, $api->calls);
    }

    public function testPlainAndHumanPartialPreserveIdWithoutPayload(): void
    {
        foreach (['--plain', '--json', null] as $format) {
            [$app] = $this->app(static fn ($method) => $method === 'disk.folder.uploadFile' ? self::success($method) : throw new Failure('permission-denied', 'Denied'));
            $run = ConsoleHarness::run($app, [...array_filter([$format]), '-vvv', ...$this->arguments()]);
            self::assertSame(3, $run->status, $run->stdout);
            self::assertStringContainsString('789', $run->stdout);
            self::assertStringContainsString('upload', $run->stdout);
            self::assertStringNotContainsString('SECRET', $run->stdout . $run->stderr);
            self::assertStringContainsString('--file-id 789', $run->stderr . $run->stdout);
        }
    }
}
