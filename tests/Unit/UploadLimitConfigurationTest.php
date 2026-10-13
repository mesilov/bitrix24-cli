<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Application\Task\LocalFile;
use Bitrix24\CLI\Application\Task\StreamingBase64;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Filesystem\LocalFileSource;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use Bitrix24\SDK\Infrastructure\Filesystem\Base64Encoder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

final class UploadLimitConfigurationTest extends TestCase
{
    private string $directory;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/b24cli-limit-' . bin2hex(random_bytes(5));
        $this->filesystem->mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    private function sparse(string $path, int $bytes): void
    {
        $handle = fopen($path, 'wb');
        ftruncate($handle, $bytes);
        fclose($handle);
    }

    public function testConfiguredLimitOverridesDefaultInBothDirections(): void
    {
        $path = $this->directory . '/payload.bin';
        $config = $this->directory . '/runtime.json';
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), null, $spyConnectionResolver);
        $args = ['--json', 'task:file:attach', '123', '--file', $path, '--folder-id', '456', '--dry-run'];
        foreach ([100, 500000000, 600000000] as $limit) {
            $this->filesystem->dumpFile($config, json_encode(['maxUploadBytes' => $limit], JSON_THROW_ON_ERROR));
            foreach ([$limit, $limit + 1] as $bytes) {
                $this->sparse($path, $bytes);
                $run = ConsoleHarness::run($app, ['--config', $config, ...$args]);
                self::assertSame($bytes === $limit ? 0 : 2, $run->status, $run->stdout);
                if ($bytes === $limit) {
                    self::assertSame($limit, $run->json()['data']['plan']['steps'][0]['fields']['maxUploadBytes']);
                }
            }
        }

        $this->sparse($path, LocalFile::DEFAULT_MAX_BYTES);
        self::assertSame(0, ConsoleHarness::run($app, $args)->status);
        $this->sparse($path, LocalFile::DEFAULT_MAX_BYTES + 1);
        self::assertSame(2, ConsoleHarness::run($app, $args)->status);
        self::assertSame(0, $spyConnectionResolver->reads);
    }

    public function testInvalidConfiguredLimitsFailBeforeCredentialsEvenForReadCommands(): void
    {
        $config = $this->directory . '/runtime.json';
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), null, $spyConnectionResolver);
        foreach ([0, -1, null, true, false, '500000000', 1.5, [], (object) []] as $value) {
            $this->filesystem->dumpFile($config, json_encode(['maxUploadBytes' => $value], JSON_THROW_ON_ERROR));
            $run = ConsoleHarness::run($app, ['--config', $config, '--json', 'task:show', '123']);
            self::assertSame(2, $run->status, $run->stdout);
            self::assertSame('usage-error', $run->json()['error']['code']);
        }

        self::assertSame(0, $spyConnectionResolver->reads);
    }

    public function testFull500MbStreamThroughSdkAt128MbMemoryAndConfigAboveDefault(): void
    {
        foreach ([[500000000], [500000001, 'config']] as $arguments) {
            $process = proc_open([PHP_BINARY, '-d', 'memory_limit=128M', __DIR__ . '/../Support/large-upload-worker.php', ...array_map(strval(...), $arguments)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }

            self::assertSame(0, proc_close($process), $stdout . $stderr);
            $json = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($arguments[0], $json['sizeBytes']);
            self::assertTrue($json['hashMatched']);
            self::assertSame('128M', $json['memoryLimit']);
            self::assertLessThan(128 * 1024 * 1024, $json['peakMemoryBytes']);
            self::assertSame(2, $json['apiCalls']);
        }
    }

    public function testChangedSourceDuringSdkSendRemainsUnknownWithoutAttachment(): void
    {
        $path = $this->directory . '/payload.bin';
        $size = LocalFileSource::DIRECT_ENCODING_BYTES + 1;
        $this->sparse($path, $size);
        $calls = 0;
        $mockHttpClient = new \Symfony\Component\HttpClient\MockHttpClient(function (string $method, string $url, array $options) use ($path, $size, &$calls): \Symfony\Component\HttpClient\Response\MockResponse {
            ++$calls;
            $body = $options['body'];
            self::assertNotEmpty($body(16372));
            $this->sparse($path, $size + 1);
            while ($body(16372) !== '') {
            }

            self::fail('Changed upload was sent');
        });
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), null, new SpyConnectionResolver(), false, $mockHttpClient);
        $run = ConsoleHarness::run($app, ['--json', 'task:file:attach', '123', '--file', $path, '--folder-id', '456']);
        self::assertSame(1, $run->status);
        self::assertSame('transport-error', $run->json()['error']['code']);
        self::assertTrue($run->json()['meta']['outcomeUnknown']);
        self::assertSame(['unknown', 'skipped'], array_column($run->json()['error']['details']['ledger'], 'status'));
        self::assertSame(1, $calls);
    }

    public function testStreamCancellationAndChangedFileStopTheBody(): void
    {
        $path = $this->directory . '/payload.bin';
        $this->sparse($path, LocalFileSource::DIRECT_ENCODING_BYTES + 1);
        $runtimeState = new RuntimeState();
        $localFileSource = new LocalFileSource($this->filesystem, new Base64Encoder($this->filesystem, new \Symfony\Component\Mime\Encoder\Base64Encoder(), new NullLogger()), $runtimeState);
        $encoded = $localFileSource->encode($localFileSource->inspect($path));
        self::assertInstanceOf(StreamingBase64::class, $encoded);
        $chunks = $encoded->chunks();
        $chunks->rewind();
        self::assertNotEmpty($chunks->current());
        $runtimeState->cancelled = true;
        try {
            $chunks->next();
            self::fail('Cancelled stream continued');
        } catch (Failure $failure) {
            self::assertSame(130, $failure->exitStatus);
        }

        $runtimeState->cancelled = false;
        $file = $localFileSource->inspect($path);
        $encoded = $localFileSource->encode($file);
        $this->sparse($path, $file->sizeBytes + 1);
        try {
            $encoded->chunks()->rewind();
            self::fail('Changed stream opened');
        } catch (Failure $failure) {
            self::assertSame(2, $failure->exitStatus);
        }
    }
}
