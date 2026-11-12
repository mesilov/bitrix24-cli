<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\DeadlineAssignmentResolver;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Issue #141: relative day plus explicit time resolved to an exact deadline for task:add, task:update and task:deadline:set.
 */
final class TaskRelativeDeadlineTest extends TestCase
{
    private function clock(string $now): Clock
    {
        return new readonly class (new \DateTimeImmutable($now)) implements Clock {
            public function __construct(private \DateTimeImmutable $now)
            {
            }

            public function now(): \DateTimeImmutable
            {
                return $this->now;
            }
        };
    }

    private function resolve(string $now, string $day, string $time, string $timezone): string
    {
        return (new DeadlineAssignmentResolver($this->clock($now)))->resolve($day, $time, $timezone, '--day', '--time')?->value() ?? '';
    }

    public static function resolutions(): iterable
    {
        yield 'tomorrow' => ['2026-11-13T15:00:00+06:00', 'tomorrow', '18:00', 'Asia/Bishkek', '2026-11-14T18:00:00+06:00'];
        yield 'day after tomorrow' => ['2026-11-13T15:00:00+06:00', 'day-after-tomorrow', '09:30', 'Asia/Bishkek', '2026-11-15T09:30:00+06:00'];
        yield 'today later' => ['2026-11-13T15:00:00+06:00', 'today', '15:01', 'Asia/Bishkek', '2026-11-13T15:01:00+06:00'];
        yield 'friday from tuesday' => ['2026-11-10T10:00:00+03:00', 'friday', '17:00', 'Europe/Moscow', '2026-11-13T17:00:00+03:00'];
        yield 'friday from monday' => ['2026-11-09T10:00:00+03:00', 'friday', '17:00', 'Europe/Moscow', '2026-11-13T17:00:00+03:00'];
        yield 'friday on friday' => ['2026-11-13T08:00:00+03:00', 'friday', '17:00', 'Europe/Moscow', '2026-11-20T17:00:00+03:00'];
        yield 'monday from sunday' => ['2026-11-15T10:00:00+03:00', 'monday', '09:00', 'Europe/Moscow', '2026-11-16T09:00:00+03:00'];
        yield 'next-week friday from monday' => ['2026-11-09T10:00:00+03:00', 'next-week-friday', '17:00', 'Europe/Moscow', '2026-11-20T17:00:00+03:00'];
        yield 'next-week friday from sunday' => ['2026-11-15T10:00:00+03:00', 'next-week-friday', '17:00', 'Europe/Moscow', '2026-11-20T17:00:00+03:00'];
        yield 'next-week monday from monday' => ['2026-11-09T10:00:00+03:00', 'next-week-monday', '09:00', 'Europe/Moscow', '2026-11-16T09:00:00+03:00'];
        yield 'year rollover' => ['2026-12-31T20:00:00+06:00', 'tomorrow', '09:00', 'Asia/Bishkek', '2027-01-01T09:00:00+06:00'];
        yield 'leap date' => ['2026-11-13T15:00:00+06:00', '2028-02-29', '10:00', 'Asia/Bishkek', '2028-02-29T10:00:00+06:00'];
        yield 'summer offset' => ['2027-03-26T12:00:00+01:00', 'next-week-monday', '10:00', 'Europe/Berlin', '2027-03-29T10:00:00+02:00'];
        yield 'timezone decides today' => ['2026-11-13T20:30:00Z', 'today', '23:00', 'UTC', '2026-11-13T23:00:00+00:00'];
        yield 'timezone decides today east' => ['2026-11-13T20:30:00Z', 'today', '23:00', 'Asia/Bishkek', '2026-11-14T23:00:00+06:00'];
    }

    #[DataProvider('resolutions')]
    public function testResolvesExactDeadline(string $now, string $day, string $time, string $timezone, string $expected): void
    {
        self::assertSame($expected, $this->resolve($now, $day, $time, $timezone));
    }

    public static function rejections(): iterable
    {
        yield 'unknown token' => ['2026-11-13T15:00:00+06:00', 'someday', '10:00', 'Asia/Bishkek', 'day token'];
        yield 'missing date' => ['2026-11-13T15:00:00+06:00', '2027-02-29', '10:00', 'Asia/Bishkek', 'does not exist'];
        yield 'hour 24' => ['2026-11-13T15:00:00+06:00', 'tomorrow', '24:00', 'Asia/Bishkek', 'HH:MM'];
        yield 'short time' => ['2026-11-13T15:00:00+06:00', 'tomorrow', '7:5', 'Asia/Bishkek', 'HH:MM'];
        yield 'seconds' => ['2026-11-13T15:00:00+06:00', 'tomorrow', '18:00:30', 'Asia/Bishkek', 'HH:MM'];
        yield 'DST gap' => ['2027-03-20T12:00:00+01:00', '2027-03-28', '02:30', 'Europe/Berlin', 'does not exist'];
        yield 'DST overlap' => ['2026-10-20T12:00:00+02:00', '2026-10-25', '02:30', 'Europe/Berlin', 'occurs twice'];
        yield 'earlier today' => ['2026-11-13T15:00:00+06:00', 'today', '09:00', 'Asia/Bishkek', 'not after now'];
        yield 'equal to now' => ['2026-11-13T15:00:00+06:00', 'today', '15:00', 'Asia/Bishkek', 'not after now'];
        yield 'past date' => ['2026-11-13T15:00:00+06:00', '2026-11-01', '10:00', 'Asia/Bishkek', 'not after now'];
        yield 'bad timezone' => ['2026-11-13T15:00:00+06:00', 'tomorrow', '10:00', 'Mars/Olympus', 'IANA'];
    }

    #[DataProvider('rejections')]
    public function testRejectsInvalidOrPastDeadlines(string $now, string $day, string $time, string $timezone, string $message): void
    {
        try {
            $this->resolve($now, $day, $time, $timezone);
            self::fail('Expected usage error');
        } catch (Failure $failure) {
            self::assertSame([2, 'usage-error'], [$failure->exitStatus, $failure->errorCode]);
            self::assertStringContainsString($message, $failure->getMessage());
        }
    }

    public function testPartialArgumentsAreUsageErrorsWithoutDefaultHour(): void
    {
        $deadlineAssignmentResolver = new DeadlineAssignmentResolver($this->clock('2026-11-13T15:00:00+06:00'));
        self::assertNull($deadlineAssignmentResolver->resolve(null, null, null, '--day', '--time'));
        foreach ([['tomorrow', null, null, 'no default hour'], [null, '18:00', null, '--time requires --day'], [null, null, 'UTC', '--timezone requires']] as [$day, $time, $timezone, $message]) {
            try {
                $deadlineAssignmentResolver->resolve($day, $time, $timezone, '--day', '--time');
                self::fail('Expected usage error');
            } catch (Failure $failure) {
                self::assertSame(2, $failure->exitStatus);
                self::assertStringContainsString($message, $failure->getMessage());
            }
        }
    }

    private function invoke(array $argv, ?\Closure $respond = null): array
    {
        $fakeApiTransport = new FakeApiTransport($respond ?? static fn (string $method, int $version): ApiResponse => new ApiResponse(match ([$method, $version]) {
            ['tasks.task.getFields', 1] => ['fields' => ['UF_QUERY' => ['type' => 'string']]],
            ['tasks.task.add', 1] => ['task' => ['id' => '123']],
            ['tasks.task.add', 3] => ['item' => ['id' => 123]],
            ['tasks.task.update', 3] => ['result' => true],
            default => throw new \LogicException('Unexpected route ' . $method),
        }));
        $spyConnectionResolver = new SpyConnectionResolver();
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver, clock: $this->clock('2026-11-13T15:00:00+06:00'));
        return [ConsoleHarness::run($app, ['--json', ...$argv]), $fakeApiTransport, $spyConnectionResolver];
    }

    public function testCreateWithRelativeDeadline(): void
    {
        [$run, $api] = $this->invoke(['task:add', '--title', 'Отчёт', '--creator', '1', '--responsible', '2', '--deadline-day', 'tomorrow', '--deadline-time', '18:00', '--timezone', 'Asia/Bishkek']);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame(['tasks.task.add'], array_column($api->calls, 'method'));
        self::assertSame('2026-11-14T18:00:00+06:00', $api->calls[0]['parameters']['fields']['deadline']);
        self::assertSame(['day' => 'tomorrow', 'time' => '18:00', 'timezone' => 'Asia/Bishkek', 'now' => '2026-11-13T15:00:00+06:00', 'deadline' => '2026-11-14T18:00:00+06:00'], $run->json()['meta']['deadlineResolution']);
    }

    public function testLegacyCreateRouteReceivesTheSameDeadline(): void
    {
        [$run, $api] = $this->invoke(['task:add', '--title', 'Отчёт', '--creator', '1', '--responsible', '2', '--deadline-day', 'tomorrow', '--deadline-time', '18:00', '--timezone', 'Asia/Bishkek', '--user-fields', '{"UF_QUERY":"5"}']);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame([1, 1], array_column($api->calls, 'version'));
        self::assertSame('2026-11-14T18:00:00+06:00', $api->calls[1]['parameters']['fields']['DEADLINE']);
    }

    public function testUpdateAndDeadlineSetSendOnlyTheResolvedDeadline(): void
    {
        [$run, $api] = $this->invoke(['task:update', '123', '--deadline-day', 'friday', '--deadline-time', '17:00', '--timezone', 'Europe/Moscow']);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame(['id' => 123, 'fields' => ['deadline' => '2026-11-20T17:00:00+03:00']], $api->calls[0]['parameters']);
        [$run, $api] = $this->invoke(['task:deadline:set', '123', '--day', 'next-week-monday', '--time', '09:00', '--timezone', 'Europe/Moscow']);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame(['id' => 123, 'fields' => ['deadline' => '2026-11-16T09:00:00+03:00']], $api->calls[0]['parameters']);
        self::assertSame('next-week-monday', $run->json()['meta']['deadlineResolution']['day']);
    }

    public function testDryRunShowsResolutionWithoutWriting(): void
    {
        [$run, $api] = $this->invoke(['task:deadline:set', '123', '--day', 'tomorrow', '--time', '18:00', '--timezone', 'Asia/Bishkek', '--dry-run']);
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame([], array_filter($api->calls, static fn (array $call): bool => $call['effect'] !== 'read'));
        $json = $run->json();
        self::assertTrue($json['meta']['dryRun']);
        self::assertSame('2026-11-14T18:00:00+06:00', $json['meta']['deadlineResolution']['deadline']);
        self::assertStringContainsString('2026-11-14T18:00:00+06:00', json_encode($json['data'], JSON_THROW_ON_ERROR));
    }

    public function testUnknownWriteOutcomeKeepsResolutionAndIsNotRetried(): void
    {
        [$run, $api] = $this->invoke(['task:deadline:set', '123', '--day', 'tomorrow', '--time', '18:00', '--timezone', 'Asia/Bishkek'], static fn (): ApiResponse => throw new Failure('transport-error', 'The response was lost.', 1, [], true));
        self::assertSame(1, $run->status, $run->stdout);
        $json = $run->json();
        self::assertTrue($json['meta']['outcomeUnknown']);
        self::assertSame('2026-11-14T18:00:00+06:00', $json['error']['details']['deadlineResolution']['deadline']);
        self::assertCount(1, array_filter($api->calls, static fn (array $call): bool => $call['method'] === 'tasks.task.update'));
    }

    public static function invalidInvocations(): iterable
    {
        yield 'relative --at' => [['task:deadline:set', '123', '--at', 'tomorrow'], '--day/--time'];
        yield 'relative --deadline' => [['task:update', '123', '--deadline', 'friday 17:00'], '--deadline-day'];
        yield 'day without time' => [['task:add', '--title', 'Отчёт', '--deadline-day', 'tomorrow'], 'no default hour'];
        yield 'time without day' => [['task:deadline:set', '123', '--time', '18:00'], '--time requires --day'];
        yield 'mixed set' => [['task:deadline:set', '123', '--at', '2026-12-01T12:00:00+06:00', '--day', 'tomorrow', '--time', '18:00'], 'Choose --at'];
        yield 'mixed update' => [['task:update', '123', '--deadline', '2026-12-01T12:00:00+06:00', '--deadline-day', 'tomorrow', '--deadline-time', '18:00'], 'Choose --deadline'];
        yield 'with fields' => [['task:update', '123', '--fields', '{"deadline":"2026-12-01T12:00:00+06:00"}', '--deadline-day', 'tomorrow', '--deadline-time', '18:00'], '--fields'];
        yield 'timezone alone' => [['task:update', '123', '--title', 'X', '--timezone', 'UTC'], '--timezone requires'];
        yield 'nothing to set' => [['task:deadline:set', '123'], 'Provide --at'];
        yield 'past relative' => [['task:deadline:set', '123', '--day', 'today', '--time', '09:00', '--timezone', 'Asia/Bishkek'], 'not after now'];
    }

    #[DataProvider('invalidInvocations')]
    public function testInvalidInvocationsFailBeforeCredentialsAndApi(array $argv, string $message): void
    {
        [$run, $api, $spy] = $this->invoke($argv, static fn () => throw new \LogicException('Unexpected call'));
        self::assertSame(2, $run->status, $run->stdout);
        self::assertSame('usage-error', $run->json()['error']['code']);
        self::assertStringContainsString($message, $run->json()['error']['message']);
        self::assertSame([], $api->calls);
        self::assertSame(0, $spy->reads);
    }

    public function testHelpAndCompletionDescribeRelativeFlags(): void
    {
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), new FakeApiTransport(static fn () => throw new \LogicException('Unexpected call')), new SpyConnectionResolver());
        foreach ([['task:add', '--deadline-day'], ['task:update', '--deadline-day'], ['task:deadline:set', '--day']] as [$command, $flag]) {
            $help = ConsoleHarness::run($app, [$command, '--help']);
            self::assertStringContainsString($flag, $help->stdout);
            self::assertStringContainsString('--timezone', $help->stdout);
            self::assertStringContainsString('часа по умолчанию нет', $help->stdout);
            $completion = ConsoleHarness::run($app, ['_complete', '--shell', 'bash', '--input', 'b24cli', '--input', $command, '--input=' . $flag, '--input=n', '--current', '3']);
            self::assertSame(0, $completion->status);
            foreach (['tomorrow', 'friday', 'next-week-friday'] as $token) {
                self::assertStringContainsString($token, $completion->stdout, $command);
            }
        }
    }
}
