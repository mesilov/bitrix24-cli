<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\RuntimeState;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Console\B24Application;
use Bitrix24\CLI\Infrastructure\Bitrix24\SdkApiTransport;
use Bitrix24\CLI\Infrastructure\Connection\B24ClientProvider;
use Bitrix24\CLI\Infrastructure\Connection\EnvConnectionResolver;
use Bitrix24\CLI\Infrastructure\Connection\FileCredentialStore;
use Bitrix24\CLI\Infrastructure\Connection\FileProtection;
use Bitrix24\CLI\Infrastructure\Connection\ProfileRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;

abstract class LiveCommandTestCase extends TestCase
{
    protected B24Application $app;
    protected int $userId;
    protected SdkApiTransport $api;
    protected array $observedCommands = [];
    private array $settings = [];

    protected function setUp(): void
    {
        $envConnectionResolver = new EnvConnectionResolver($this->root());
        try {
            $envConnectionResolver->webhook();
        } catch (Failure $failure) {
            if ($failure->errorCode === 'connection-unavailable') {
                self::markTestSkipped('Live portal tests need BITRIX24_WEBHOOK in the selected root environment file.');
            }

            throw $failure;
        }

        $path = $envConnectionResolver->environmentFile();
        if (is_file($path)) {
            $this->settings = (new Dotenv())->parse(file_get_contents($path));
        }

        $runtimeState = new RuntimeState();
        $this->api = new SdkApiTransport(new B24ClientProvider($envConnectionResolver, $runtimeState), $runtimeState);
        $this->userId = (int) ($this->api->call('profile', 1, [])->result['ID'] ?? 0);
        self::assertGreaterThan(0, $this->userId, 'Webhook profile must return the acting user ID.');
        $this->app = (new ApplicationFactory())->create($this->root());
    }

    protected function root(): string
    {
        return dirname(__DIR__, 2);
    }

    protected function setting(string $name): string
    {
        return getenv($name) ?: ($this->settings[$name] ?? '');
    }

    protected function commandName(): string
    {
        $groups = array_map(static fn (\ReflectionAttribute $attribute): string => $attribute->newInstance()->name(), (new \ReflectionClass($this))->getAttributes(Group::class));
        $commands = array_values(array_filter($groups, static fn (string $group): bool => str_starts_with($group, 'command:')));
        if (count($commands) !== 1) {
            throw new \LogicException('Each live test class must declare exactly one command group.');
        }

        return substr($commands[0], strlen('command:'));
    }

    protected function isHappyPath(): bool
    {
        return array_any((new \ReflectionMethod($this, $this->name()))->getAttributes(Group::class), static fn (\ReflectionAttribute $attribute): bool => $attribute->newInstance()->name() === 'happy-path');
    }

    protected function invoke(array $argv, array $statuses = [0], ?B24Application $app = null): array
    {
        self::assertSame($this->commandName(), $argv[0], 'Fixtures and readback must use SDK helpers, not another CLI command.');
        $run = ConsoleHarness::run($app ?? $this->app, ['--json', ...array_map(strval(...), $argv)]);
        $json = $run->json();
        $this->observedCommands[] = ['command' => $argv[0], 'status' => $run->status, 'apiCalls' => $json['meta']['apiCalls'] ?? [], 'complete' => $json['meta']['complete'] ?? null, 'reason' => $json['meta']['reason'] ?? null, 'errorCode' => $json['error']['code'] ?? null, 'apiErrorCode' => $json['error']['details']['apiErrorCode'] ?? null, ...(isset($json['meta']['roleFilter']) ? ['roleFilter' => $json['meta']['roleFilter']] : []), 'taskStatus' => $json['data']['task']['status'] ?? null, 'confirmed' => $json['data']['operation']['confirmed'] ?? null, 'outcomeUnknown' => $json['meta']['outcomeUnknown'] ?? null];
        $this->afterInvoke($argv, $json);

        self::assertContains($run->status, $statuses, $run->stdout . $run->stderr);
        if ($run->status === 0) {
            self::assertNull($json['error']);
            if (array_key_exists('complete', $json['meta'])) {
                self::assertTrue($json['meta']['complete']);
            }
        }

        return $json;
    }


    protected function afterInvoke(array $argv, array $json): void
    {
    }

    protected function withProfile(array $roles, \Closure $run): mixed
    {
        $directory = sys_get_temp_dir() . '/b24cli-live-profile-' . bin2hex(random_bytes(12));
        $fileProtection = new FileProtection();
        $profileRepository = new ProfileRepository($fileProtection, new FileCredentialStore($fileProtection));
        try {
            $webhook = (new EnvConnectionResolver($this->root()))->webhook();
            $profileRepository->add($directory, 'fixture', $webhook, null, ['creatorId' => $this->userId, 'responsibleId' => $this->userId], $roles);
            return $run($directory);
        } finally {
            if (is_dir($directory)) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                    $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
                }

                rmdir($directory);
            }
        }
    }

    protected function tearDown(): void
    {
        if (!isset($this->userId)) {
            return;
        }

        $directory = $this->root() . '/var/cache';
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        $evidence = ['class' => static::class, 'test' => $this->name(), 'command' => $this->commandName(), 'happyPath' => $this->isHappyPath(), 'actingUserId' => $this->userId, 'commands' => $this->observedCommands, 'skipped' => $this->status()->isSkipped(), 'cleanupFailures' => []];
        self::assertNotFalse(file_put_contents($directory . '/portal-evidence.jsonl', json_encode($evidence, JSON_THROW_ON_ERROR) . "\n", FILE_APPEND | LOCK_EX));
        if ($this->isHappyPath() && !$this->status()->isSkipped()) {
            self::assertNotEmpty(array_filter($this->observedCommands, static fn (array $call): bool => $call['status'] === 0), 'The happy-path case must execute its own CLI command successfully.');
        }
    }
}
