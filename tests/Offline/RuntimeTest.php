<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Offline;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Infrastructure\Bitrix24\SdkApiTransport;
use Bitrix24\CLI\Infrastructure\Connection\B24ClientProvider;
use Bitrix24\CLI\Infrastructure\Connection\ConnectionResolver;
use PHPUnit\Framework\TestCase;

final class RuntimeTest extends TestCase
{
    public function testTtyConfirmationDeclineNeverWritesAndAcceptanceWritesOnce(): void
    {
        foreach (['n' => 130, 'y' => 0] as $answer => $expected) {
            $process = proc_open([PHP_BINARY, __DIR__ . '/../Support/confirmation-worker.php', '--json', 'task:delete', '123'], [0 => ['pty'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            try {
                fwrite($pipes[0], $answer . "\n");
                stream_set_timeout($pipes[1], 5);
                stream_set_timeout($pipes[2], 5);
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }

                self::assertSame($expected, proc_close($process), $stdout . $stderr);
                $process = null;
                self::assertSame($answer === 'y' ? 1 : 0, substr_count($stderr, 'WRITE'));
                self::assertStringContainsString('Согласовать договор', $stderr);
            } finally {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }
        }
    }

    public function testRealProcessSigintRetainsConfirmedStepAndSkipsLaterWrites(): void
    {
        self::assertTrue(function_exists('pcntl_signal'), 'Distributed Docker runtime needs pcntl.');
        $process = proc_open([PHP_BINARY, __DIR__ . '/../Support/signal-worker.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        try {
            stream_set_timeout($pipes[2], 5);
            self::assertSame("READY\n", fgets($pipes[2]));
            self::assertTrue(proc_terminate($process, SIGINT));
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }

            $status = proc_close($process);
            $process = null;
            self::assertSame(130, $status, $stdout . $stderr);
            $json = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
            self::assertSame([50], array_column($json['data']['items'], 'resourceId'));
            self::assertSame(['confirmed', 'failed', 'skipped'], array_column($json['meta']['ledger'], 'status'));
            self::assertSame('interrupted', $json['error']['code']);
        } finally {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }
    }

    public function testRealHttpTimeoutIsBoundedAndLostWriteOutcomeIsUnknown(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        self::assertIsResource($socket, $error);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $process = proc_open([PHP_BINARY, '-S', $address, __DIR__ . '/../Support/slow-http.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        try {
            $ready = false;
            for ($i = 0; $i < 50; $i++) {
                $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
                if (is_resource($probe)) {
                    fclose($probe);
                    $ready = true;
                    break;
                }

                usleep(20000);
            }

            self::assertTrue($ready);
            $resolver = new readonly class ($address) implements ConnectionResolver {
                public function __construct(private string $address)
                {
                }
                public function webhook(): string
                {
                    return 'http://' . $this->address . '/rest/1/timeout-fixture/';
                }
            };
            $runtimeState = new RuntimeState();
            $runtimeState->begin(0.1);
            $sdkApiTransport = new SdkApiTransport(new B24ClientProvider($resolver, $runtimeState), $runtimeState);
            $started = microtime(true);
            try {
                $sdkApiTransport->call('tasks.task.update', 3, ['id' => 123], 'write');
                self::fail('Expected timeout.');
            } catch (Failure $failure) {
                self::assertSame('transport-error', $failure->errorCode);
                self::assertTrue($failure->outcomeUnknown);
            }

            self::assertLessThan(2.0, microtime(true) - $started);
            self::assertCount(1, $runtimeState->calls);
        } finally {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }

            proc_close($process);
        }
    }

    public function testBothEntrypointsRunOfflineWithoutReadingSelectedEnvFile(): void
    {
        foreach (['bin/b24cli', 'bin/console'] as $entrypoint) {
            foreach ([['--version'], ['list', 'task', '--raw'], ['task:add', '--help'], ['completion', 'bash']] as $argv) {
                $env = [...getenv(), 'B24CLI_ENV_FILE' => '/does/not/exist'];
                unset($env['BITRIX24_WEBHOOK'], $env['BITRIX24_PHP_SDK_PLAYGROUND_WEBHOOK']);
                $process = proc_open([PHP_BINARY, $entrypoint, ...$argv], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2), $env);
                self::assertIsResource($process);
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }

                self::assertSame(0, proc_close($process), $stdout . $stderr);
                self::assertNotSame('', $stdout);
                self::assertStringNotContainsString("\033[", $stdout);
            }
        }
    }
}
