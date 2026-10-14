<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\HistoryScanner;
use Bitrix24\CLI\Infrastructure\Bitrix24\{ApiResponse, Rest1TaskHistoryAdapter};
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use PHPUnit\Framework\TestCase;

final class TaskHistoryExhaustionTest extends TestCase
{
    private function rows(int $start, int $count): array
    {
        return array_map(static fn (int $id): array => ['id' => $id, 'field' => 'STATUS', 'createdDate' => '2026-10-14T00:00:00Z', 'value' => ['from' => '2', 'to' => '3']], range($start, $start + $count - 1));
    }

    public function testAbsentNextExhaustsCollectionWithoutTotalOrRowCountHeuristic(): void
    {
        foreach ([1, 50, 58] as $count) {
            $fakeApiTransport = new FakeApiTransport(fn (): ApiResponse => new ApiResponse(['list' => $this->rows(1, $count)]));
            $result = (new HistoryScanner(new Rest1TaskHistoryAdapter($fakeApiTransport)))->scan(123, [], true);
            self::assertSame(0, $result->exitStatus);
            self::assertCount($count, $result->data['items']);
            self::assertCount(1, $fakeApiTransport->calls);
        }
    }

    public function testFollowsNextUntilTerminalWithoutTotal(): void
    {
        $fakeApiTransport = new FakeApiTransport(fn (string $method, int $version, array $params): ApiResponse => new ApiResponse(['list' => $this->rows(($params['start'] ?? 0) + 1, $params['start'] === 0 ? 50 : 5)], $params['start'] === 0 ? 50 : null));
        $result = (new HistoryScanner(new Rest1TaskHistoryAdapter($fakeApiTransport)))->scan(123, [], true);
        self::assertSame(0, $result->exitStatus);
        self::assertCount(55, $result->data['items']);
        self::assertSame([0, 50], array_column(array_column($fakeApiTransport->calls, 'parameters'), 'start'));
    }

    public function testContradictingCountersDoNotYieldCompleteCollection(): void
    {
        foreach ([0, 3] as $total) {
            $fakeApiTransport = new FakeApiTransport(fn (): ApiResponse => new ApiResponse(['list' => $this->rows(1, 2)], null, $total));
            $result = (new HistoryScanner(new Rest1TaskHistoryAdapter($fakeApiTransport)))->scan(123, [], true);
            self::assertSame(3, $result->exitStatus);
            self::assertContains('history-navigation-unverified', $result->meta['reasons']);
        }
    }

    public function testMalformedHistoryCollectionIsAnApiError(): void
    {
        $fakeApiTransport = new FakeApiTransport(static fn (): ApiResponse => new ApiResponse(['list' => ['unexpected' => 'value']]));
        $this->expectException(Failure::class);
        (new Rest1TaskHistoryAdapter($fakeApiTransport))->page(123, [], 0);
    }
}
