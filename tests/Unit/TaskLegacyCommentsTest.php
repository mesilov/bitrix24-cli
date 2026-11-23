<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Bootstrap\CommandCatalog;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\TestCase;

/**
 * #182: the comment feed of the old task card is a separate, legacy source.
 * The fake honours ORDER/FILTER like the portal observed in research.md and serves pages of $pageSize.
 */
final class TaskLegacyCommentsTest extends TestCase
{
    private const TOKEN_URL = '/bitrix/tools/disk/uf.php?attachedId=2&auth%5Baplogin%5D=1&auth%5Bap%5D=secret-token&action=download';

    /** @var list<int> */
    private array $ids = [4, 6, 8, 10];

    private int $pageSize = 50;

    /** @var \Closure(array): ApiResponse|null */
    private ?\Closure $override = null;

    private function respond(string $method, int $version, array $params): ApiResponse
    {
        if ($method !== 'task.commentitem.getlist' || $version !== 1) {
            throw new \LogicException('Unexpected ' . $method);
        }

        if ($this->override instanceof \Closure) {
            return ($this->override)($params);
        }

        $ids = $this->ids;
        $filter = $params['FILTER'] ?? [];
        if (isset($filter['>ID'])) {
            $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > $filter['>ID']));
        }

        if (isset($filter['<ID'])) {
            $ids = array_values(array_filter($ids, static fn (int $id): bool => $id < $filter['<ID']));
        }

        if (($params['ORDER']['ID'] ?? 'asc') === 'desc') {
            rsort($ids);
        }

        return new ApiResponse(array_map(fn (int $id): array => $this->row($id), array_slice($ids, 0, $this->pageSize)));
    }

    private function row(int $id): array
    {
        $row = [
            'POST_MESSAGE_HTML' => null, 'ID' => (string) $id, 'AUTHOR_ID' => '1', 'AUTHOR_NAME' => 'Автор',
            'AUTHOR_EMAIL' => 'author@example.test', 'POST_DATE' => '2025-02-01T16:34:28+03:00', 'POST_MESSAGE' => 'comment ' . $id,
        ];
        if ($id === 10) {
            $row['ATTACHED_OBJECTS'] = ['2' => ['ATTACHMENT_ID' => '2', 'NAME' => 'photo.jpg', 'SIZE' => '88727', 'FILE_ID' => '838', 'DOWNLOAD_URL' => self::TOKEN_URL, 'VIEW_URL' => self::TOKEN_URL]];
        }

        return $row;
    }

    private function invoke(array $argv, string $format = '--json'): array
    {
        $fakeApiTransport = new FakeApiTransport($this->respond(...));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, new SpyConnectionResolver());
        $run = ConsoleHarness::run($app, [$format, ...$argv]);
        return [$run, $fakeApiTransport->calls];
    }

    public function testFullFeedReturnsCommentsWithLegacyMetadata(): void
    {
        [$run, $calls] = $this->invoke(['task:legacy:comment:list', '2', '--all']);
        $json = $run->json();
        self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        self::assertSame([4, 6, 8, 10], array_column($json['data']['items'], 'id'));
        self::assertSame(['id' => 4, 'authorId' => 1, 'authorName' => 'Автор', 'createdDate' => '2025-02-01T16:34:28+03:00', 'text' => 'comment 4', 'attachments' => []], $json['data']['items'][0]);
        self::assertSame([['attachmentId' => 2, 'fileId' => 838, 'name' => 'photo.jpg', 'size' => 88727]], $json['data']['items'][3]['attachments']);
        self::assertSame('legacy-comments', $json['meta']['source']);
        self::assertTrue($json['meta']['legacy']);
        self::assertTrue($json['meta']['complete']);
        self::assertFalse($json['meta']['hasMore']);
        self::assertSame(4, $json['meta']['scanned']);
        self::assertSame(['task.commentitem.getlist'], array_values(array_unique(array_column($calls, 'method'))));
        self::assertSame(['TASKID' => 2, 'ORDER' => ['ID' => 'asc']], $calls[0]['parameters']);
        self::assertSame(['TASKID' => 2, 'ORDER' => ['ID' => 'asc'], 'FILTER' => ['>ID' => 10]], $calls[1]['parameters']);
        self::assertSame([], array_filter($calls, static fn (array $call): bool => $call['effect'] !== 'read'));
        self::assertStringNotContainsString('author@example.test', $run->stdout);
    }

    public function testAttachmentUrlsWithTokensNeverReachAnyFormat(): void
    {
        foreach (['--json', '--plain', '--no-ansi'] as $format) {
            [$run] = $this->invoke(['task:legacy:comment:list', '2', '--all'], $format);
            self::assertSame(0, $run->status, $format . $run->stderr);
            self::assertStringNotContainsString('secret-token', $run->stdout . $run->stderr, $format);
            self::assertStringNotContainsString('uf.php', $run->stdout . $run->stderr, $format);
        }
    }

    public function testPlainKeepsTsvOnStdoutAndLegacyMarkerOnStderr(): void
    {
        [$run] = $this->invoke(['task:legacy:comment:list', '2', '--all'], '--plain');
        self::assertSame(0, $run->status, $run->stderr);
        $lines = explode("\n", trim($run->stdout));
        self::assertSame("4\t2025-02-01T16:34:28+03:00\t1\tАвтор\tcomment 4\t0", $lines[0]);
        self::assertSame("10\t2025-02-01T16:34:28+03:00\t1\tАвтор\tcomment 10\t1", $lines[3]);
        self::assertStringNotContainsString('legacy', $run->stdout);
        self::assertStringContainsString('(legacy, только чтение)', $run->stderr);
    }

    public function testHumanOutputStartsWithLegacyHeader(): void
    {
        [$run] = $this->invoke(['task:legacy:comment:list', '2', '--all'], '--no-ansi');
        self::assertSame(0, $run->status, $run->stderr);
        self::assertStringStartsWith('Прежняя лента комментариев задачи 2 (legacy, только чтение). Текущее обсуждение — b24cli task:chat:list 2.', $run->stdout);
        self::assertStringContainsString('attachmentCount', $run->stdout);
    }

    public function testLatestWindowWalksDownAcrossPagesAndReturnsAscending(): void
    {
        $this->ids = range(1, 120);
        $this->pageSize = 7;
        [$run, $calls] = $this->invoke(['task:legacy:comment:list', '2', '--limit', '20']);
        $json = $run->json();
        self::assertSame(0, $run->status, $run->stderr);
        self::assertSame(range(101, 120), array_column($json['data']['items'], 'id'));
        self::assertTrue($json['meta']['hasMore']);
        self::assertSame('selected-comment-window', $json['meta']['scope']);
        self::assertSame(['TASKID' => 2, 'ORDER' => ['ID' => 'desc']], $calls[0]['parameters']);
        self::assertSame(['TASKID' => 2, 'ORDER' => ['ID' => 'desc'], 'FILTER' => ['<ID' => 114]], $calls[1]['parameters']);
    }

    public function testAllReadsSeveralPagesUntilAnEmptyPage(): void
    {
        $this->ids = range(1, 120);
        [$run, $calls] = $this->invoke(['task:legacy:comment:list', '2', '--all']);
        $json = $run->json();
        self::assertSame(range(1, 120), array_column($json['data']['items'], 'id'));
        self::assertTrue($json['meta']['complete']);
        self::assertSame(120, $json['meta']['returned']);
        self::assertCount(4, $calls);
    }

    public function testCursors(): void
    {
        [$run] = $this->invoke(['task:legacy:comment:list', '2', '--after', '6']);
        self::assertSame([8, 10], array_column($run->json()['data']['items'], 'id'));
        [$run, $calls] = $this->invoke(['task:legacy:comment:list', '2', '--before', '8']);
        self::assertSame([4, 6], array_column($run->json()['data']['items'], 'id'));
        self::assertSame(['TASKID' => 2, 'ORDER' => ['ID' => 'desc'], 'FILTER' => ['<ID' => 8]], $calls[0]['parameters']);
    }

    public function testBudgetExhaustionIsPartial(): void
    {
        $this->ids = range(1, 30);
        [$run] = $this->invoke(['task:legacy:comment:list', '2', '--all', '--max-comments', '25']);
        $json = $run->json();
        self::assertSame(3, $run->status);
        self::assertCount(25, $json['data']['items']);
        self::assertFalse($json['meta']['complete']);
        self::assertTrue($json['meta']['hasMore']);
        self::assertSame('comment-budget-exhausted', $json['meta']['reason']);
    }

    public function testIgnoredCursorStopsWithoutProgress(): void
    {
        $this->override = fn (array $params): ApiResponse => new ApiResponse([$this->row(4), $this->row(6)]);
        [$run, $calls] = $this->invoke(['task:legacy:comment:list', '2', '--all']);
        $json = $run->json();
        self::assertSame(3, $run->status);
        self::assertSame([4, 6], array_column($json['data']['items'], 'id'));
        self::assertSame('pagination-no-progress', $json['meta']['reason']);
        self::assertCount(2, $calls);
    }

    public function testEmptyFeedIsCompleteSuccess(): void
    {
        $this->ids = [];
        [$run] = $this->invoke(['task:legacy:comment:list', '4', '--all']);
        self::assertSame(0, $run->status);
        self::assertSame([], $run->json()['data']['items']);
        self::assertTrue($run->json()['meta']['complete']);
    }

    public function testMissingMethodOrTaskIsAnErrorNotAnEmptyFeed(): void
    {
        foreach (['ERROR_METHOD_NOT_FOUND', 'ERROR_CORE'] as $code) {
            $this->override = static fn (): never => throw new Failure('api-error', 'Bitrix24 rejected the request.', 1, ['apiErrorCode' => $code]);
            [$run] = $this->invoke(['task:legacy:comment:list', '2', '--all']);
            $json = $run->json();
            self::assertSame(1, $run->status, $code);
            self::assertNull($json['data']);
            self::assertSame('legacy-comments-unavailable', $json['error']['code']);
            self::assertSame('legacy-comments', $json['error']['details']['source']);
            self::assertSame($code, $json['error']['details']['apiErrorCode']);
            self::assertStringContainsString('not checked', $json['error']['message']);
        }
    }

    public function testPageErrorAfterPartialReadIsPartial(): void
    {
        $this->ids = range(1, 60);
        $this->override = function (array $params): ApiResponse {
            if (isset($params['FILTER'])) {
                throw new Failure('api-error', 'Bitrix24 rejected the request.', 1, ['apiErrorCode' => 'INTERNAL_SERVER_ERROR']);
            }

            return new ApiResponse(array_map(fn (int $id): array => $this->row($id), range(1, 50)));
        };
        [$run] = $this->invoke(['task:legacy:comment:list', '2', '--all']);
        self::assertSame(3, $run->status);
        self::assertCount(50, $run->json()['data']['items']);
        self::assertSame('page-error', $run->json()['meta']['reason']);
    }

    public function testMalformedCommentIdIsAnErrorNotAComment(): void
    {
        $this->override = static fn (): ApiResponse => new ApiResponse([['ID' => 'abc', 'POST_MESSAGE' => 'x']]);
        [$run] = $this->invoke(['task:legacy:comment:list', '2', '--all']);
        self::assertSame(1, $run->status);
        self::assertSame('api-error', $run->json()['error']['code']);
        self::assertNull($run->json()['data']);
    }

    public function testInvalidOptionsAreRejectedBeforeApi(): void
    {
        foreach ([['--before', '8', '--after', '4'], ['--limit', '5', '--all'], ['--max-comments', '0'], ['--after', 'x']] as $options) {
            [$run, $calls] = $this->invoke(['task:legacy:comment:list', '2', ...$options]);
            self::assertSame(2, $run->status, implode(' ', $options));
            self::assertSame([], $calls);
        }
    }

    public function testStrictRest3DeniesBeforeCredentials(): void
    {
        $spyConnectionResolver = new SpyConnectionResolver();
        $fakeApiTransport = new FakeApiTransport($this->respond(...));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $spyConnectionResolver);
        $run = ConsoleHarness::run($app, ['--json', '--api-policy', 'strict-rest3', 'task:legacy:comment:list', '2']);
        self::assertSame(4, $run->status, $run->stdout);
        self::assertSame('policy-denied', $run->json()['error']['code']);
        self::assertSame([], $fakeApiTransport->calls);
        self::assertSame(0, $spyConnectionResolver->reads);
    }

    public function testCatalogListAndHelpShowTheLegacyNamespace(): void
    {
        self::assertSame([['task.commentitem.getlist', 1]], CommandCatalog::ROUTES['task:legacy:comment:list']);
        self::assertSame('read', CommandCatalog::COMMANDS['task:legacy:comment:list']['effect']);
        self::assertSame(['task:legacy:comment:list'], array_values(array_filter(array_keys(CommandCatalog::COMMANDS), static fn (string $name): bool => str_starts_with($name, 'task:legacy:'))));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), null, new SpyConnectionResolver());
        $list = ConsoleHarness::run($app, ['list', 'task']);
        self::assertMatchesRegularExpression('/task:legacy:comment:list\s+\[legacy\] /u', $list->stdout);
        $help = ConsoleHarness::run($app, ['task:legacy:comment:list', '--help']);
        self::assertStringContainsString('Устаревший источник: лента комментариев старой карточки задачи.', $help->stdout);
        self::assertStringContainsString('task:chat:list', $help->stdout);
    }
}
