<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Offline;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\FixturePortal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScanningAndBindingTest extends TestCase
{
    private function invoke(array $argv, \Closure $response): array
    {
        $fakeApiTransport = new FakeApiTransport($response);
        $run = ConsoleHarness::run((new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport), ['--json', ...$argv]);
        return [$run, $fakeApiTransport];
    }

    public function testSearchScansSecondPageAndLimitsOnlyOutput(): void
    {
        [$run, $api] = $this->invoke(['task:find', '--title', 'ЁЖ[', '--limit', '1'], static function ($method, $version, array $params): ApiResponse {
            if ($params['pagination']['offset'] === 0) {
                return new ApiResponse(['items' => array_map(static fn ($id): array => ['id' => $id, 'title' => $id === 2 ? 'ёж[ first' : 'other'], range(1, 50))]);
            }

            return new ApiResponse(['items' => [['id' => 51, 'title' => 'Ёж[ second']]]);
        });
        self::assertSame(0, $run->status, $run->stdout);
        $json = $run->json();
        self::assertSame([['id' => 2, 'title' => 'ёж[ first']], $json['data']['items']);
        self::assertSame(51, $json['meta']['scanned']);
        self::assertSame(2, $json['meta']['matched']);
        self::assertTrue($json['meta']['limitApplied']);
        self::assertTrue($json['meta']['complete']);
        self::assertSame(50, $api->calls[1]['parameters']['pagination']['offset']);
        self::assertSame([], $api->calls[0]['parameters']['filter']);
    }

    public static function partialSearchCases(): iterable
    {
        yield 'budget' => ['--max-scan', '1', 'scan-budget-exhausted'];
        yield 'repeated page' => [null, null, 'pagination-no-progress'];
        yield 'missing title' => [null, null, 'missing-predicate-field'];
        yield 'page error' => [null, null, 'page-error'];
    }

    #[DataProvider('partialSearchCases')]
    public function testPartialSearchReturnsInspectableList(?string $flag, ?string $value, string $reason): void
    {
        $counter = 0;
        [$run] = $this->invoke(['task:find', '--title', 'x', ...($flag === null ? [] : [$flag, $value])], static function () use (&$counter, $reason): ApiResponse {
            $counter++;
            if ($reason === 'page-error' && $counter === 2) {
                throw new Failure('transport-error', 'unavailable');
            }

            if ($reason === 'missing-predicate-field') {
                return new ApiResponse(['items' => [['id' => 1]]]);
            }

            return new ApiResponse(['items' => array_map(static fn ($id): array => ['id' => $id, 'title' => 'x'], range(1, $reason === 'scan-budget-exhausted' ? 1 : 50))]);
        });
        self::assertSame(3, $run->status, $run->stdout);
        self::assertFalse($run->json()['meta']['complete']);
        self::assertSame($reason, $run->json()['meta']['reason']);
        self::assertIsArray($run->json()['data']['items']);
        self::assertLessThanOrEqual(2, $counter);
    }

    public function testSearchZeroAndManyResultsAreAlwaysLists(): void
    {
        foreach ([[], [['id' => 1, 'title' => 'x']], [['id' => 1, 'title' => 'x'], ['id' => 2, 'title' => 'x']]] as $rows) {
            [$run] = $this->invoke(['task:find', '--title', 'x', '--all'], static fn (): ApiResponse => new ApiResponse(['items' => $rows]));
            self::assertSame($rows, $run->json()['data']['items']);
        }
    }

    public function testListSeparatesIdServerFilterFromLocalPredicates(): void
    {
        [$run, $api] = $this->invoke(['task:list', '--id', '123', '--responsible', '2', '--order', 'title:desc'], (new FixturePortal())->respond(...));
        self::assertSame(0, $run->status, $run->stdout);
        self::assertSame([['id', 'in', [123]]], $api->calls[0]['parameters']['filter']);
        self::assertSame(['title' => 'DESC', 'id' => 'ASC'], (array) $api->calls[0]['parameters']['order']);
        self::assertSame(1, $run->json()['meta']['returned']);
    }

    public function testWrongChatAndMessageNeverMutate(): void
    {
        foreach ([true, false] as $badRelation) {
            $portal = new FixturePortal();
            if ($badRelation) {
                $portal->task['chat']['entityId'] = 999;
            }

            [$run, $api] = $this->invoke(['task:chat:update', '123', '--message', '901', '--text', 'x'], $portal->respond(...));
            self::assertSame(4, $run->status, $run->stdout);
            self::assertNotContains('im.message.update', array_column($api->calls, 'method'));
        }
    }

    public function testForeignTimeEntryAndChecklistRootNeverMutate(): void
    {
        $fixturePortal = new FixturePortal();
        foreach ([['task:time:update', '123', '--entry', '31', '--seconds', '120'], ['task:checklist:item:complete', '123', '--item', '10'], ['task:checklist:item:add', '123', '--checklist', '99', '--title', 'x']] as $argv) {
            [$run, $api] = $this->invoke($argv, $fixturePortal->respond(...));
            self::assertSame(4, $run->status, $run->stdout);
            self::assertSame(['read'], array_values(array_unique(array_column($api->calls, 'effect'))));
        }

        [$run, $api] = $this->invoke(['task:time:delete', '123', '--entry', '30', '--force'], static fn (): ApiResponse => new ApiResponse([['ID' => '30', 'TASK_ID' => '999']]));
        self::assertSame(4, $run->status);
        self::assertCount(1, $api->calls);
    }

    public function testChecklistNormalizationOrderingAndDeletePreview(): void
    {
        $fixturePortal = new FixturePortal();
        [$run] = $this->invoke(['task:checklist:item:list', '123', '--checklist', '10'], $fixturePortal->respond(...));
        self::assertSame([11, 12], array_column($run->json()['data']['items'], 'id'));
        self::assertSame([false, true], array_column($run->json()['data']['items'], 'isComplete'));
        [$run, $api] = $this->invoke(['task:checklist:item:delete', '123', '--item', '11', '--dry-run'], $fixturePortal->respond(...));
        self::assertSame(0, $run->status, $run->stdout);
        self::assertStringContainsString('12', $run->stdout);
        self::assertSame(['task.checklistitem.getlist'], array_column($api->calls, 'method'));
        $fixturePortal->nodes[0]['PARENT_ID'] = '12';
        [$run] = $this->invoke(['task:checklist:list', '123'], $fixturePortal->respond(...));
        self::assertSame(4, $run->status);
    }

    public function testTimeCodecAndOmittedRoleAreExact(): void
    {
        [$run, $api] = $this->invoke(['task:time:list', '123', '--params', '{"order":{"SECONDS":"desc"},"params":{"NAV_PARAMS":{"nPageSize":10,"iNumPage":2}}}'], (new FixturePortal())->respond(...));
        self::assertSame(0, $run->status, $run->stdout);
        self::assertTrue(array_is_list($api->calls[0]['parameters']));
        self::assertSame(['NAV_PARAMS' => ['nPageSize' => 10, 'iNumPage' => 2]], $api->calls[0]['parameters'][4]);
        [$run, $api] = $this->invoke(['task:participants:set', '123', '--auditor', '2'], (new FixturePortal())->respond(...));
        self::assertSame(['taskId' => 123, 'fields' => ['AUDITORS' => [2]]], $api->calls[1]['parameters']);
    }

    public function testHistoryMissingNavigationDeclaresPartialAndNormalizesNestedValues(): void
    {
        [$run] = $this->invoke(['task:history:list', '123'], static fn (): ApiResponse => new ApiResponse(['list' => [['id' => 1, 'createdDate' => '2026-10-09', 'user' => ['id' => 2], 'field' => 'TITLE', 'value' => ['from' => 'old', 'to' => 'new']]]]));
        self::assertSame(3, $run->status, $run->stdout);
        self::assertSame('old', $run->json()['data']['items'][0]['from']);
        self::assertSame(2, $run->json()['data']['items'][0]['userId']);
        self::assertFalse($run->json()['meta']['complete']);
    }

    public function testAttachmentPartialLedgerDoesNotRetryLostWrite(): void
    {
        [$run, $api] = $this->invoke(['task:file:attach', '123', '--file-id', '50', '--file-id', '51', '--file-id', '52'], static function ($method, $version, array $params): ApiResponse {
            if ($params['fileIds'] === [51]) {
                throw new Failure('transport-error', 'lost response', 1, [], true);
            }

            return new ApiResponse(['result' => true]);
        });
        self::assertSame(3, $run->status, $run->stdout);
        self::assertSame([50], array_column($run->json()['data']['items'], 'resourceId'));
        self::assertSame(['confirmed', 'unknown', 'skipped'], array_column($run->json()['meta']['ledger'], 'status'));
        self::assertTrue($run->json()['meta']['outcomeUnknown']);
        self::assertCount(2, $api->calls);
    }
}
