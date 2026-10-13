<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\Task\{HistoryScanner, Page};
use Bitrix24\CLI\Application\Task\Port\TaskHistoryGateway;
use PHPUnit\Framework\TestCase;

final class HistoryEvidenceTest extends TestCase
{
    private function row(int $id = 1): array
    {
        return ['id' => $id, 'createdDate' => '2026-10-01T00:00:00+03:00', 'field' => 'REAL_STATUS', 'from' => '2', 'to' => '3', 'userId' => 9];
    }

    private function scanner(array $pages): HistoryScanner
    {
        return new HistoryScanner(new readonly class ($pages) implements TaskHistoryGateway {
            public function __construct(private array $pages)
            {
            }
            public function page(int $taskId, array $query, int $offset): Page
            {
                $page = $this->pages[$offset];
                if ($page instanceof Failure) {
                    throw $page;
                }

                return $page;
            }
        });
    }

    public function testEvidenceKeepsConflictRowsAndRawOffsetsWithoutChangingPublicHistory(): void
    {
        $row = $this->row();
        $scanner = $this->scanner([0 => new Page([$row, $row, [...$row, 'to' => '5']])]);
        $public = $scanner->scan(123, []);
        self::assertSame(['items' => [$row]], $public->data);
        self::assertSame(0, $public->exitStatus);
        self::assertSame(['scope' => 'visible-task-change-history', 'complete' => true, 'reason' => null, 'returned' => 1], $public->meta);
        $evidence = $scanner->scan(123, [], true);
        self::assertCount(3, $evidence->data['evidence']);
        self::assertSame(3, $evidence->exitStatus);
        self::assertSame(['conflicting-history-id'], $evidence->meta['reasons']);
        self::assertSame($row['createdDate'], $evidence->data['evidence'][0]['createdDate']);
    }

    public function testNavigationPageErrorAndBudgetReasonsSurviveTogether(): void
    {
        $scanner = $this->scanner([0 => new Page([$this->row()], 50, false), 50 => new Failure('api-error', 'Failed.')]);
        $result = $scanner->scan(123, [], true);
        self::assertSame(['history-navigation-unverified', 'page-error'], $result->meta['reasons']);
        self::assertCount(1, $result->data['evidence']);
        $result = $this->scanner([0 => new Page(array_map($this->row(...), range(1, 10001)))])->scan(123, [], true);
        self::assertSame(3, $result->exitStatus);
        self::assertCount(10000, $result->data['evidence']);
        self::assertContains('history-budget-exhausted', $result->meta['reasons']);
    }

    public function testIdenticalDuplicatesAndEmptyConfirmedPageRemainComplete(): void
    {
        $row = $this->row();
        $scanner = $this->scanner([0 => new Page([$row, $row], 50), 50 => new Page([])]);
        $result = $scanner->scan(123, [], true);
        self::assertTrue($result->meta['complete']);
        self::assertCount(2, $result->data['evidence']);
        self::assertCount(1, $result->data['items']);
    }
}
