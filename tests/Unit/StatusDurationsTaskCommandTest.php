<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\{Clock, Failure};
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\{ConsoleHarness, FakeApiTransport, SpyConnectionResolver};
use PHPUnit\Framework\TestCase;

final class StatusDurationsTaskCommandTest extends TestCase
{
    private function api(bool $navigation = true, bool $race = false, bool $snapshotError = false, bool $creationRace = false, bool $creationError = false): FakeApiTransport
    {
        $reads = 0;
        $creationReads = 0;
        return new FakeApiTransport(static function (string $method) use (&$reads, &$creationReads, $navigation, $race, $snapshotError, $creationRace, $creationError): ApiResponse {
            if ($method === 'tasks.task.list') {
                $creationReads++;
                if ($creationError) {
                    throw new Failure('permission-denied', 'No creation readback.');
                }

                return new ApiResponse(['items' => [['id' => 123, 'created' => $creationRace && $creationReads === 2 ? '2026-10-01T00:00:01Z' : '2026-10-01T00:00:00Z']]]);
            }

            if ($method === 'tasks.task.get') {
                $reads++;
                if ($snapshotError && $reads === 2) {
                    throw new Failure('permission-denied', 'No readback.');
                }

                return new ApiResponse(['item' => ['id' => 123, 'status' => $race && $reads === 2 ? 'pending' : 'in_progress', 'statusChanged' => '2026-10-01T00:00:10Z', 'changed' => '2026-10-01T00:00:10Z']]);
            }

            return new ApiResponse(['list' => [
                ['id' => 1, 'field' => 'NEW', 'createdDate' => '2026-10-01T00:00:00Z', 'value' => ['from' => null, 'to' => null]],
                ['id' => 2, 'field' => 'REAL_STATUS', 'createdDate' => '2026-10-01T00:00:10Z', 'user' => ['id' => 7], 'value' => ['from' => 'pending', 'to' => 'in_progress']],
            ]], null, $navigation ? 2 : 3); // Missing next contradicts total=3 with only two rows.
        });
    }

    private function invoke(FakeApiTransport $api, array $argv, ?SpyConnectionResolver $resolver = null): \Bitrix24\CLI\Tests\Support\ConsoleRun
    {
        $clock = new readonly class () implements Clock {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-10-01T00:02:00Z');
            }
        };
        return ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $api, $resolver, clock: $clock), $argv);
    }

    public function testJsonArithmeticRoutesAndExplicitProfileUseSharedContracts(): void
    {
        $api = $this->api();
        $run = $this->invoke($api, ['--json', 'task:status:durations', '123']);
        self::assertSame(0, $run->status, $run->stdout);
        $json = $run->json();
        self::assertSame(10, $json['data']['totals']['pending']['seconds']);
        self::assertSame(110, $json['data']['totals']['in_progress']['seconds']);
        self::assertSame('2026-10-01T00:02:00.000000+00:00', $json['data']['asOf']);
        self::assertSame(['tasks.task.get', 'tasks.task.list', 'tasks.task.history.list', 'tasks.task.list', 'tasks.task.get'], array_column($api->calls, 'method'));
        self::assertSame(['id' => 123, 'select' => ['id', 'status', 'statusChanged', 'changed']], $api->calls[0]['parameters']);
        self::assertEquals(['filter' => [['id', '=', 123]], 'select' => ['id', 'created'], 'order' => (object) ['id' => 'ASC'], 'pagination' => ['offset' => 0, 'limit' => 2]], $api->calls[1]['parameters']);
        self::assertSame(['taskId' => 123, 'start' => 0, 'order' => ['createdDate' => 'ASC']], $api->calls[2]['parameters']);
        self::assertSame(['read'], array_unique(array_column($api->calls, 'effect')));
        self::assertFalse($json['meta']['snapshotAtomic']);
        self::assertSame(['seconds' => 110, 'hours' => 0.03, 'days' => 0], $json['data']['totals']['in_progress']);
        self::assertSame('weekdays-only', $json['meta']['semantics']);
        self::assertSame('server', $json['meta']['calendar']['timezoneSource']);
        self::assertSame('+00:00', $json['meta']['calendar']['utcOffset']);
        self::assertArrayNotHasKey('precision', $json['meta']);
        $human = $this->invoke($this->api(), ['--no-ansi', 'task:status:durations', '123']);
        self::assertSame(0, $human->status);
        self::assertStringContainsString('110 с / 0.03 ч / 0.00 д', $human->stdout);
        $plain = $this->invoke($this->api(), ['--plain', 'task:status:durations', '123']);
        self::assertSame(0, $plain->status);
        self::assertCount(8, explode("\t", strtok($plain->stdout, "\n")));
        $api = $this->api();
        $badProfile = $this->invoke($api, ['--json', '--profile', 'missing', '--config-dir', sys_get_temp_dir() . '/b24cli-absent-status-profile', 'task:status:durations', '123']);
        self::assertNotSame(0, $badProfile->status);
        self::assertSame([], $api->calls);
    }

    public function testValidationHelpDryRunAndStrictPolicyAvoidApiAndCredentials(): void
    {
        foreach ([['0'], ['123', '--as-of', '2026-10-01T00:00:00'], ['123', '--as-of', '2026-02-30T00:00:00Z'], ['123', '--as-of', '2099-10-01T00:00:00Z'], ['123', '--api-policy', 'strict-rest3'], ['--help'], ['123', '--dry-run']] as $args) {
            $api = $this->api();
            $resolver = new SpyConnectionResolver();
            $run = $this->invoke($api, ['--json', 'task:status:durations', ...$args], $resolver);
            self::assertSame(in_array('--help', $args, true) ? 0 : (in_array('strict-rest3', $args, true) ? 4 : 2), $run->status, $run->stdout);
            self::assertSame([], $api->calls);
            self::assertSame(0, $resolver->reads);
        }
    }

    public function testPartialRaceReadbackErrorAndFormatsExposeUnknownWithoutZero(): void
    {
        foreach ([$this->api(false), $this->api(race: true), $this->api(snapshotError: true), $this->api(creationRace: true), $this->api(creationError: true)] as $api) {
            $run = $this->invoke($api, ['--json', 'task:status:durations', '123']);
            self::assertSame(3, $run->status, $run->stdout);
            self::assertSame('partial-result', $run->json()['error']['code']);
            self::assertNull($run->json()['data']['totals']['pending']['seconds']);
            self::assertNotEmpty($run->json()['meta']['reasons']);
        }

        foreach (['--plain', '--no-ansi'] as $format) {
            $run = $this->invoke($this->api(false), [$format, 'task:status:durations', '123']);
            self::assertSame(3, $run->status);
            self::assertStringContainsString('неизвестно', $run->stdout);
            self::assertStringContainsString('history-navigation-unverified', $run->stdout . $run->stderr);
        }
    }

    public function testFirstPageErrorAndCancellationKeepErrorContracts(): void
    {
        foreach ([1, 130] as $exit) {
            $api = new FakeApiTransport(static fn (): never => throw new Failure($exit === 130 ? 'interrupted' : 'api-error', 'Stopped.', $exit));
            $run = $this->invoke($api, ['--json', 'task:status:durations', '123']);
            self::assertSame($exit, $run->status);
            self::assertNull($run->json()['data']);
        }
    }
}
