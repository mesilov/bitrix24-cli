<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Unit;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Bootstrap\ApplicationFactory;
use Bitrix24\CLI\Infrastructure\Bitrix24\ApiResponse;
use Bitrix24\CLI\Infrastructure\Connection\ConnectionResolver;
use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\ConsoleRun;
use Bitrix24\CLI\Tests\Support\FakeApiTransport;
use Bitrix24\CLI\Tests\Support\SpyConnectionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * kb:* over fixtures shaped like the REST 3.0 responses observed on a test portal on 2026-11-26.
 */
final class KnowledgeBaseCommandsTest extends TestCase
{
    private const NOT_FOUND = 'BITRIX_REST_V3_EXCEPTION_ENTITYNOTFOUNDEXCEPTION';

    /** @var list<array<string, mixed>> */
    private array $tree = [];

    private bool $truncated = false;

    private ?\Closure $add = null;

    private ?\Closure $update = null;

    private FakeApiTransport $transport;

    protected function setUp(): void
    {
        $this->tree = [
            $this->node(888, null, 'Регламенты', [$this->node(890, 888, 'Отпуск'), $this->node(892, 888, 'Командировки')]),
            $this->node(894, null, 'Онбординг'),
        ];
    }

    /** @param list<array<string, mixed>> $children */
    private function node(int $id, ?int $parentId, string $title, array $children = []): array
    {
        return ['id' => $id, 'collectionId' => 42, 'parentId' => $parentId, 'title' => $title, 'position' => 1000, 'children' => $children];
    }

    private static function article(int $id, string $title = 'Отпуск', string $markdown = "# Отпуск\n\nТекст **жирный**\n", ?int $parentId = 888): array
    {
        return [
            'id' => $id, 'collectionId' => 42, 'parentId' => $parentId, 'title' => $title, 'markdown' => $markdown, 'position' => 1000,
            'createdBy' => 1, 'updatedBy' => 1, 'createdAt' => '2026-11-26T09:20:53+03:00', 'updatedAt' => '2026-11-26T09:20:56+03:00',
            'contentUpdatedAt' => '2026-11-26T09:20:56+03:00', 'state' => 'live', 'isArchived' => false, 'isTrashed' => false, 'isOrphan' => false,
        ];
    }

    private static function failure(string $code): Failure
    {
        return new Failure('api-error', 'Bitrix24 rejected the request; verify the target, fields and permissions.', 1, ['apiErrorCode' => $code]);
    }

    private function portal(string $method, int $version, array $params): ApiResponse
    {
        self::assertSame(3, $version, $method . ' must use REST 3.0');
        return match ($method) {
            'note.collection.list' => new ApiResponse(['nextCursor' => null, 'items' => [
                ['id' => 2, 'name' => 'Моя компания', 'position' => 1000, 'policyLevel' => 'portal', 'accessLevel' => 'full', 'state' => 'live', 'isArchived' => false, 'createdBy' => 0, 'markdownDescription' => null],
            ]]),
            'note.document.tree.list' => $params['collectionId'] === 42 ? new ApiResponse(['items' => $this->tree, ...($this->truncated ? ['truncated' => true] : [])]) : throw self::failure(self::NOT_FOUND),
            'note.document.search.list' => new ApiResponse(['hasMore' => false, 'items' => [
                ['documentId' => 890, 'collectionId' => 42, 'title' => 'Отпуск', 'score' => 0.71, 'snippet' => 'Правила <mark>отпуск</mark>а &amp; графики', 'sharedAccess' => false],
            ]]),
            'note.document.get' => $params['id'] === 890 ? new ApiResponse(['item' => self::article(890)]) : throw self::failure(self::NOT_FOUND),
            'note.document.add' => $this->add instanceof \Closure ? ($this->add)($params) : new ApiResponse(['item' => [...self::article(900, $params['fields']['title'], $params['fields']['markdown'] ?? '', $params['fields']['parentId'] ?? null), 'collectionId' => $params['fields']['collectionId']]]),
            'note.document.update' => $this->update instanceof \Closure ? ($this->update)($params) : new ApiResponse(['item' => [...self::article($params['id']), ...$params['fields']]]),
            default => throw new \LogicException('Unexpected ' . $method),
        };
    }

    private function exec(array $argv, ?\Closure $portal = null, string $mode = '--json', ?string $stdin = null): ConsoleRun
    {
        $this->transport = new FakeApiTransport($portal ?? $this->portal(...));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $this->transport, new SpyConnectionResolver());
        return ConsoleHarness::run($app, $mode === '' ? $argv : [$mode, ...$argv], $stdin);
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function json(array $argv, ?\Closure $portal = null, ?string $stdin = null): array
    {
        $run = $this->exec($argv, $portal, '--json', $stdin);
        return [$run->status, $run->json()];
    }

    /** @return list<string> */
    private function methods(): array
    {
        return array_column($this->transport->calls, 'method');
    }

    private function file(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'b24cli-kb-');
        file_put_contents($path, $content);
        return $path;
    }

    public function testListsKnowledgeBasesWithApiFieldNames(): void
    {
        [$status, $json] = $this->json(['kb:list']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(['pagination' => ['limit' => 200]], $this->transport->calls[0]['parameters']);
        self::assertSame(['id' => 2, 'name' => 'Моя компания', 'position' => 1000, 'policyLevel' => 'portal', 'accessLevel' => 'full', 'state' => 'live', 'isArchived' => false, 'createdBy' => 0, 'markdownDescription' => null], $json['data']['items'][0]);
        self::assertTrue($json['meta']['complete']);

        $run = $this->exec(['kb:list'], null, '--plain');
        self::assertSame("2\tМоя компания\tportal\tfull\tfalse\n", $run->stdout);
    }

    public function testFollowsTheCursorAndStopsWithoutProgress(): void
    {
        $pages = [
            ['nextCursor' => ['position' => 1000, 'id' => 2], 'items' => [['id' => 2, 'name' => 'A']]],
            ['nextCursor' => null, 'items' => [['id' => 3, 'name' => 'B']]],
        ];
        [$status, $json] = $this->json(['kb:list'], static function () use (&$pages): ApiResponse {
            return new ApiResponse(array_shift($pages));
        });
        self::assertSame(0, $status, json_encode($json));
        self::assertSame([2, 3], array_column($json['data']['items'], 'id'));
        self::assertSame(['limit' => 200, 'afterCursor' => ['position' => 1000, 'id' => 2]], $this->transport->calls[1]['parameters']['pagination']);

        [$status, $json] = $this->json(['kb:list'], static fn (): ApiResponse => new ApiResponse(['nextCursor' => ['position' => 1, 'id' => 2], 'items' => [['id' => 2, 'name' => 'A']]]));
        self::assertSame([3, 'partial-result', 'pagination-no-progress'], [$status, $json['error']['code'], $json['meta']['reason']]);
        self::assertSame([2], array_column($json['data']['items'], 'id'));
        self::assertCount(2, $this->transport->calls);

        [$status, $json] = $this->json(['kb:list'], static fn (): ApiResponse => new ApiResponse(['nextCursor' => null, 'items' => []]));
        self::assertSame([0, []], [$status, $json['data']['items']]);
    }

    public function testArticleTreeKeepsNestingInJsonAndDepthInLines(): void
    {
        [$status, $json] = $this->json(['kb:article:list', '--collection', '42']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(['collectionId' => 42], $this->transport->calls[0]['parameters']);
        self::assertSame([890, 892], array_column($json['data']['items'][0]['children'], 'id'));
        self::assertSame(4, $json['meta']['count']);

        $run = $this->exec(['kb:article:list', '--collection', '42'], null, '--plain');
        self::assertSame("888\t\t0\tРегламенты\n890\t888\t1\tОтпуск\n892\t888\t1\tКомандировки\n894\t\t0\tОнбординг\n", $run->stdout);
    }

    public function testTruncatedTreeIsIncompleteAndMissingCollectionIsExplicit(): void
    {
        $this->truncated = true;
        $run = $this->exec(['kb:article:list', '--collection', '42'], null, '--plain');
        self::assertSame(3, $run->status);
        self::assertStringContainsString('tree-truncated', $run->stderr);
        self::assertStringContainsString('kb:article:search', $run->stderr);

        [$status, $json] = $this->json(['kb:article:list', '--collection', '7']);
        self::assertSame([1, 'collection-not-found', self::NOT_FOUND], [$status, $json['error']['code'], $json['error']['details']['apiErrorCode']]);
    }

    public function testSearchPassesSnippetAsIsAndStripsItForHumans(): void
    {
        [$status, $json] = $this->json(['kb:article:search', '--query', '  отпуск ', '--limit', '20']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(['query' => 'отпуск', 'pagination' => ['limit' => 20]], $this->transport->calls[0]['parameters']);
        self::assertSame('Правила <mark>отпуск</mark>а &amp; графики', $json['data']['items'][0]['snippet']);
        self::assertFalse($json['meta']['hasMore']);

        $run = $this->exec(['kb:article:search', '--query', 'отпуск'], null, '');
        self::assertStringContainsString('Правила отпуска & графики', $run->stdout);
        self::assertStringNotContainsString('<mark>', $run->stdout);

        $more = static fn (): ApiResponse => new ApiResponse(['hasMore' => true, 'items' => []]);
        [$status, $json] = $this->json(['kb:article:search', '--query', 'отпуск'], $more);
        self::assertSame([0, true, null], [$status, $json['meta']['hasMore'], $json['error']]);
        self::assertStringContainsString('--limit', $json['meta']['hint']);
    }

    /** @return iterable<string, array{list<string>}> */
    public static function invalidInput(): iterable
    {
        yield 'short query' => [['kb:article:search', '--query', ' ab ']];
        yield 'long query' => [['kb:article:search', '--query', str_repeat('я', 201)]];
        yield 'search limit' => [['kb:article:search', '--query', 'отпуск', '--limit', '201']];
        yield 'tree without collection' => [['kb:article:list']];
        yield 'show id' => [['kb:article:show', 'abc']];
        yield 'add without collection' => [['kb:article:add', '--title', 'X']];
        yield 'add without title' => [['kb:article:add', '--collection', '42']];
        yield 'add blank title' => [['kb:article:add', '--collection', '42', '--title', '   ']];
        yield 'add long title' => [['kb:article:add', '--collection', '42', '--title', str_repeat('я', 256)]];
        yield 'add parent' => [['kb:article:add', '--collection', '42', '--title', 'X', '--parent', '0']];
        yield 'update nothing' => [['kb:article:update', '890']];
        yield 'update blank title' => [['kb:article:update', '890', '--title', '']];
    }

    #[DataProvider('invalidInput')]
    public function testInvalidInputFailsBeforeCredentialsAndApi(array $argv): void
    {
        $resolver = new class () implements ConnectionResolver {
            public function webhook(): string
            {
                throw new \LogicException('Credential read before input validation.');
            }
        };
        $fakeApiTransport = new FakeApiTransport(static fn (): never => throw new \LogicException('API call before input validation.'));
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), $fakeApiTransport, $resolver);
        $run = ConsoleHarness::run($app, ['--json', ...$argv]);
        self::assertSame([2, 'usage-error'], [$run->status, $run->json()['error']['code']], $run->stdout);
        self::assertSame([], $fakeApiTransport->calls);
    }

    public function testMarkdownOverLimitIsRejectedBeforeApi(): void
    {
        $run = $this->exec(['kb:article:update', '890', '--markdown-file', '-'], null, '--json', str_repeat('a', 1048577));
        self::assertSame([2, 'usage-error'], [$run->status, $run->json()['error']['code']]);
        self::assertStringContainsString('1048576', $run->json()['error']['message']);
        self::assertSame([], $this->transport->calls);
    }

    public function testShowReturnsTheArticleAndMarkdownModePrintsContentByteForByte(): void
    {
        [$status, $json] = $this->json(['kb:article:show', '890']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(self::article(890), $json['data']['article']);
        self::assertSame(['id' => 890], $this->transport->calls[0]['parameters']);

        $run = $this->exec(['kb:article:show', '890', '--markdown'], null, '');
        self::assertSame(0, $run->status, $run->stderr);
        self::assertSame("# Отпуск\n\nТекст **жирный**\n", $run->stdout);

        $run = $this->exec(['kb:article:show', '890'], null, '');
        self::assertStringContainsString("Текст **жирный**", $run->stdout);

        $run = $this->exec(['kb:article:show', '890', '--markdown'], null, '--json');
        self::assertSame([2, 'usage-error'], [$run->status, $run->json()['error']['code']]);

        [$status, $json] = $this->json(['kb:article:show', '5']);
        self::assertSame([1, 'article-not-found'], [$status, $json['error']['code']]);
    }

    public function testAddChecksSiblingsThenCreatesOnceWithApiFieldNames(): void
    {
        $markdown = "# Новое\n\n| a | b |\n|---|---|\n";
        [$status, $json] = $this->json(['kb:article:add', '--collection', '42', '--parent', '888', '--title', '  Онбординг ', '--markdown-file', $this->file($markdown)]);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(['note.document.tree.list', 'note.document.add'], $this->methods());
        self::assertSame(['fields' => ['collectionId' => 42, 'title' => 'Онбординг', 'parentId' => 888, 'markdown' => $markdown]], $this->transport->calls[1]['parameters']);
        self::assertSame('write', $this->transport->calls[1]['effect']);
        self::assertSame(['action' => 'create', 'id' => 900, 'collectionId' => 42, 'parentId' => 888, 'title' => 'Онбординг', 'confirmed' => true, 'readback' => 'verified', 'mismatch' => [], 'markdownNormalized' => false], $json['data']['operation']);
        self::assertSame(['status' => 'none', 'siblings' => 2], $json['data']['duplicateCheck']);
        self::assertFalse($json['meta']['outcomeUnknown']);

        $run = $this->exec(['kb:article:add', '--collection', '42', '--title', 'Новое'], null, '--plain');
        self::assertSame("900\t42\t\tНовое\ttrue\tverified\tfalse\n", $run->stdout);
        self::assertSame(['fields' => ['collectionId' => 42, 'title' => 'Новое']], $this->transport->calls[1]['parameters']);
    }

    public function testMarkdownFromStdinIsSentUnchanged(): void
    {
        [$status, $json] = $this->json(['kb:article:add', '--collection', '42', '--title', 'Из stdin', '--markdown-file', '-'], null, "  отступ\r\n");
        self::assertSame(0, $status, json_encode($json));
        self::assertSame("  отступ\r\n", $this->transport->calls[1]['parameters']['fields']['markdown']);
    }

    public function testDryRunChecksDuplicatesAndShowsSizeInsteadOfContent(): void
    {
        $markdown = str_repeat('x', 100);
        [$status, $json] = $this->json(['kb:article:add', '--collection', '42', '--title', 'Новое', '--markdown-file', $this->file($markdown), '--dry-run']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(['note.document.tree.list'], $this->methods());
        self::assertSame(['collectionId' => 42, 'parentId' => null, 'title' => 'Новое', 'markdownBytes' => 100, 'markdownSha256' => hash('sha256', $markdown), 'duplicateCheck' => 'none'], $json['data']['plan']['steps'][0]['fields']);

        [$status, $json] = $this->json(['kb:article:update', '890', '--markdown-file', $this->file($markdown), '--overwrite', '--dry-run']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame([], $this->methods());
        self::assertSame(['id' => 890, 'title' => null, 'markdownBytes' => 100, 'markdownSha256' => hash('sha256', $markdown), 'overwrite' => true], $json['data']['plan']['steps'][0]['fields']);
    }

    public function testDuplicateTitleOrTruncatedTreeBlocksCreation(): void
    {
        [$status, $json] = $this->json(['kb:article:add', '--collection', '42', '--parent', '888', '--title', 'Отпуск']);
        self::assertSame([4, 'article-exists'], [$status, $json['error']['code']], json_encode($json));
        self::assertSame([['id' => 890, 'parentId' => 888, 'title' => 'Отпуск']], $json['error']['details']['candidates']);
        self::assertNotContains('note.document.add', $this->methods());

        // The same title under another parent is not a duplicate.
        [$status] = $this->json(['kb:article:add', '--collection', '42', '--title', 'Отпуск']);
        self::assertSame(0, $status);

        $this->truncated = true;
        [$status, $json] = $this->json(['kb:article:add', '--collection', '42', '--title', 'Новое']);
        self::assertSame([4, 'search-incomplete'], [$status, $json['error']['code']]);
        self::assertNotContains('note.document.add', $this->methods());

        [$status, $json] = $this->json(['kb:article:add', '--collection', '42', '--parent', '888', '--title', 'Отпуск', '--allow-duplicate-title']);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame(['note.document.add'], $this->methods());
        self::assertSame(['status' => 'skipped', 'siblings' => 0], $json['data']['duplicateCheck']);
    }

    public function testLostCreateResponseIsUnknownAndNeverRetried(): void
    {
        $this->add = static fn (): never => throw new Failure('transport-error', 'Request failed; inspect the resource before retrying a write.', 1, [], true);
        [$status, $json] = $this->json(['kb:article:add', '--collection', '42', '--title', 'Новое']);
        self::assertSame([1, 'transport-error', true], [$status, $json['error']['code'], $json['meta']['outcomeUnknown']]);
        self::assertStringContainsString('kb:article:list --collection 42', $json['error']['message']);
        self::assertSame(1, count(array_keys($this->methods(), 'note.document.add', true)));

        $this->add = static fn (): ApiResponse => new ApiResponse(['item' => null]);
        [$status, $json] = $this->json(['kb:article:add', '--collection', '42', '--title', 'Новое']);
        self::assertSame([1, true], [$status, $json['meta']['outcomeUnknown']]);
    }

    public function testCreateReadbackMismatchAndNormalizedMarkdown(): void
    {
        $this->add = static fn (): ApiResponse => new ApiResponse(['item' => self::article(900, 'Другое', 'x', null)]);
        [$status, $json] = $this->json(['kb:article:add', '--collection', '42', '--title', 'Новое', '--markdown-file', $this->file('x')]);
        self::assertSame([3, 'mismatch', ['title']], [$status, $json['data']['operation']['readback'], $json['data']['operation']['mismatch']]);
        self::assertSame(900, $json['data']['operation']['id']);

        $this->add = static fn (): ApiResponse => new ApiResponse(['item' => self::article(900, 'Новое', "x\n", null)]);
        [$status, $json] = $this->json(['kb:article:add', '--collection', '42', '--title', 'Новое', '--markdown-file', $this->file('x')]);
        self::assertSame([0, 'verified', true], [$status, $json['data']['operation']['readback'], $json['data']['operation']['markdownNormalized']]);
        self::assertSame("x\n", $json['data']['article']['markdown']);
    }

    public function testCreateErrorsNameTheParentOption(): void
    {
        $this->add = static fn (): never => throw self::failure('NOTE_INVALID_PARENT');
        [$status, $json] = $this->json(['kb:article:add', '--collection', '42', '--parent', '777', '--title', 'Новое']);
        self::assertSame([1, 'api-validation-error'], [$status, $json['error']['code']]);
        self::assertSame(['field' => 'parentId', 'message' => 'The parent article belongs to another knowledge base.', 'option' => '--parent'], $json['error']['details']['validation'][0]);

        $this->add = static fn (): never => throw self::failure(self::NOT_FOUND);
        [$status, $json] = $this->json(['kb:article:add', '--collection', '42', '--parent', '777', '--title', 'Новое']);
        self::assertSame([1, 'parent-not-found'], [$status, $json['error']['code']]);

        [$status, $json] = $this->json(['kb:article:add', '--collection', '42', '--parent', '777', '--title', 'Новое', '--allow-duplicate-title']);
        self::assertSame([1, 'collection-not-found'], [$status, $json['error']['code']]);

        $this->add = static fn (): never => throw new Failure('api-validation-error', 'Bitrix24 rejected the supplied fields.', 1, ['validation' => [['field' => 'title', 'message' => 'Длина строки должна быть не больше, чем 255 знаков', 'option' => null]]]);
        [$status, $json] = $this->json(['kb:article:add', '--collection', '42', '--title', 'Новое']);
        self::assertSame('--title', $json['error']['details']['validation'][0]['option']);

        $this->add = static fn (): never => throw new Failure('permission-denied', 'Bitrix24 denied this operation; check the connection permissions.', 1, ['apiErrorCode' => 'ACCESS_DENIED']);
        [$status, $json] = $this->json(['kb:article:add', '--collection', '42', '--title', 'Новое']);
        self::assertSame([1, 'permission-denied'], [$status, $json['error']['code']]);
        self::assertStringContainsString('scope note', $json['error']['message']);
    }

    public function testUpdateReplacesContentAndSendsOverwriteOnlyOnRequest(): void
    {
        $markdown = "# Отпуск\n\nНовый текст\n";
        [$status, $json] = $this->json(['kb:article:update', '890', '--markdown-file', $this->file($markdown)]);
        self::assertSame(0, $status, json_encode($json));
        self::assertSame([['id' => 890, 'fields' => ['markdown' => $markdown]]], array_column($this->transport->calls, 'parameters'));
        self::assertSame('write', $this->transport->calls[0]['effect']);
        self::assertSame(['markdown'], $json['data']['changedFields']);
        self::assertSame(['action' => 'update', 'id' => 890, 'collectionId' => 42, 'parentId' => 888, 'title' => 'Отпуск', 'confirmed' => true, 'readback' => 'verified', 'mismatch' => [], 'markdownNormalized' => false], $json['data']['operation']);

        [$status] = $this->json(['kb:article:update', '890', '--title', ' Отпуск 2026 ', '--markdown-file', $this->file($markdown), '--overwrite']);
        self::assertSame(0, $status);
        self::assertSame(['id' => 890, 'fields' => ['title' => 'Отпуск 2026', 'markdown' => $markdown], 'overwrite' => true], $this->transport->calls[0]['parameters']);

        $this->update = static fn (): ApiResponse => new ApiResponse(['item' => self::article(890, 'Старое')]);
        [$status, $json] = $this->json(['kb:article:update', '890', '--title', 'Новое']);
        self::assertSame([3, ['title']], [$status, $json['data']['operation']['mismatch']]);
    }

    public function testUnknownMarkdownWriteErrorHintsAtOverwrite(): void
    {
        $this->update = static fn (): never => throw self::failure('NOTE_DOCUMENT_CONFLICT');
        [$status, $json] = $this->json(['kb:article:update', '890', '--markdown-file', $this->file('x')]);
        self::assertSame([1, 'api-error', '--overwrite'], [$status, $json['error']['code'], $json['error']['details']['hint']]);
        self::assertStringContainsString('--overwrite', $json['error']['message']);
        self::assertSame(1, count($this->transport->calls));

        [$status, $json] = $this->json(['kb:article:update', '890', '--markdown-file', $this->file('x'), '--overwrite']);
        self::assertArrayNotHasKey('hint', $json['error']['details']);

        [$status, $json] = $this->json(['kb:article:update', '890', '--title', 'Новое']);
        self::assertArrayNotHasKey('hint', $json['error']['details']);

        $this->update = static fn (): never => throw self::failure(self::NOT_FOUND);
        [$status, $json] = $this->json(['kb:article:update', '890', '--markdown-file', $this->file('x')]);
        self::assertSame([1, 'article-not-found'], [$status, $json['error']['code']]);

        $this->update = static fn (): never => throw new Failure('transport-error', 'Request failed; inspect the resource before retrying a write.', 1, [], true);
        [$status, $json] = $this->json(['kb:article:update', '890', '--markdown-file', $this->file('x')]);
        self::assertSame([1, true], [$status, $json['meta']['outcomeUnknown']]);
        self::assertStringContainsString('kb:article:show 890', $json['error']['message']);
        self::assertSame(1, count($this->transport->calls));
    }

    public function testStrictRest3AdmitsEveryKnowledgeBaseCommand(): void
    {
        foreach ([['kb:list'], ['kb:article:list', '--collection', '42'], ['kb:article:search', '--query', 'отпуск'], ['kb:article:show', '890'], ['kb:article:add', '--collection', '42', '--title', 'Новое'], ['kb:article:update', '890', '--title', 'Новое']] as $argv) {
            [$status, $json] = $this->json([...$argv, '--api-policy', 'strict-rest3']);
            self::assertSame(0, $status, $argv[0] . ' ' . json_encode($json));
            self::assertSame([3], array_values(array_unique(array_column($this->transport->calls, 'version'))));
        }
    }

    public function testInventoryAndHelpReadNoCredentials(): void
    {
        $resolver = new class () implements ConnectionResolver {
            public int $reads = 0;
            public function webhook(): string
            {
                $this->reads++;
                throw new \LogicException('Credential read on offline surface.');
            }
        };
        $app = (new ApplicationFactory())->create(dirname(__DIR__, 2), null, $resolver);
        $registered = array_keys($app->all('kb'));
        sort($registered);
        self::assertSame(['kb:article:add', 'kb:article:list', 'kb:article:search', 'kb:article:show', 'kb:article:update', 'kb:list'], $registered);
        foreach ([['list', 'kb'], ['kb:article:update', '--help'], ['kb:list', '--help']] as $argv) {
            $run = ConsoleHarness::run($app, $argv);
            self::assertSame(0, $run->status, $run->stdout . $run->stderr);
        }

        self::assertSame(0, $resolver->reads);
    }
}
