<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Clock;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\{ConsoleHarness, FakeApiTransport};
use PHPUnit\Framework\TestCase;

final class WeekdayStatusDurationsReplayTest extends TestCase
{
    public function testSavedLongPortalTaskThroughOrdinaryCommand(): void
    {
        $root = dirname(__DIR__, 2);
        $archive = $root . '/openspec/changes/archive/2026-10-14-calculate-task-status-durations/';
        $fixture = json_decode(file_get_contents($archive . 'long-status-history-acceptance.json'), true, 512, JSON_THROW_ON_ERROR);
        $original = json_decode(file_get_contents($archive . 'ordinary-cli-status-durations-result.json'), true, 512, JSON_THROW_ON_ERROR);
        $rows = $fixture['steps'][array_key_last($fixture['steps'])]['history']['events'];
        // Capture retained user.id as userId; restore that original REST shape for replay.
        $rows = array_map(static fn (array $row): array => [...$row, 'user' => ['id' => $row['userId']]], $rows);

        $snapshot = $original['meta']['snapshots']['after'];
        $creation = $original['meta']['creationEvidence']['after'];
        $asOf = new \DateTimeImmutable($original['data']['asOf']);
        $fakeApiTransport = new FakeApiTransport(static fn (string $method): ApiResponse => new ApiResponse(match ($method) {
            'tasks.task.get' => ['item' => $snapshot],
            'tasks.task.list' => ['items' => [$creation]],
            'tasks.task.history.list' => ['list' => $rows],
            default => throw new \LogicException('Unexpected replay API method: ' . $method),
        }));
        $clock = new readonly class ($asOf) implements Clock {
            public function __construct(private \DateTimeImmutable $asOf)
            {
            }

            public function now(): \DateTimeImmutable
            {
                return $this->asOf;
            }
        };
        $app = (new ApplicationFactory())->create($root, $fakeApiTransport, clock: $clock);
        $run = ConsoleHarness::run($app, ['--json', 'task:status:durations', '6372', '--as-of', $asOf->format('Y-m-d\TH:i:s.uP')]);
        self::assertSame(0, $run->status, $run->stdout);
        $json = $run->json();
        self::assertTrue($json['meta']['complete']);
        self::assertTrue($json['meta']['historyComplete']);
        self::assertNull($json['error']);
        self::assertCount(55, $json['data']['observedTransitions']);
        self::assertCount(56, $json['data']['intervals']);
        self::assertCount(2, $json['data']['excludedHistoryEvidence']);
        self::assertSame('+03:00', $json['meta']['calendar']['utcOffset']);
        self::assertArrayNotHasKey('precision', $json['meta']);
        self::assertSame($original['data']['creationBoundary'], $json['data']['creationBoundary']);
        self::assertSame($original['data']['historyEvidence'], $json['data']['historyEvidence']);
        foreach (['pending' => [92, 0.03], 'in_progress' => [97, 0.03], 'supposedly_completed' => [96, 0.03], 'completed' => [49, 0.01], 'deferred' => [0, 0], 'declined' => [0, 0]] as $status => [$seconds, $hours]) {
            self::assertEquals(['seconds' => $seconds, 'hours' => $hours, 'days' => 0], $json['data']['totals'][$status]);
            self::assertIsInt($json['data']['totals'][$status]['seconds']);
        }

        foreach ($json['data']['intervals'] as $index => $interval) {
            self::assertSame($original['data']['intervals'][$index]['enteredAt'], $interval['enteredAt']);
            self::assertSame($original['data']['intervals'][$index]['leftAt'], $interval['leftAt']);
            self::assertSame($original['data']['intervals'][$index]['evidenceIds'], $interval['evidenceIds']);
        }

        file_put_contents($root . '/var/cache/weekday-long-cli-result.json', json_encode($json, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    }
}
