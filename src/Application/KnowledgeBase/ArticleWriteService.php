<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\KnowledgeBase;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\KnowledgeBase\Port\KnowledgeBaseGateway;
use Bitrix24\CLI\Application\KnowledgeBase\Request\AddArticleRequest;
use Bitrix24\CLI\Application\KnowledgeBase\Request\UpdateArticleRequest;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;
use Bitrix24\CLI\Application\Task\Plan;

/**
 * Creates (KB-04) and replaces (KB-05) Knowledge Base 2.0 articles.
 *
 * Creation first reads the tree of the knowledge base: an article with exactly the same
 * title under the same parent, or a truncated tree, blocks the write unless the duplicate is
 * allowed. Each write is one call without retry; a lost response is outcomeUnknown. The
 * article returned by the API is compared with the request: title, knowledge base and parent
 * must match (else exit 3), while differing Markdown only marks markdownNormalized because the
 * portal may normalize it. note.document.update replaces the whole Markdown.
 */
final readonly class ArticleWriteService
{
    public function __construct(private KnowledgeBaseGateway $gateway)
    {
    }

    /** @return list<Route> */
    public function addRoutes(AddArticleRequest $request): array
    {
        return [
            ...($request->allowDuplicateTitle ? [] : [new Route('note.document.tree.list', 3)]),
            new Route('note.document.add', 3, 'write'),
        ];
    }

    /** @return list<Route> */
    public function updateRoutes(): array
    {
        return [new Route('note.document.update', 3, 'write')];
    }

    public function prepareAdd(AddArticleRequest $request): PreparedOperation
    {
        $check = $this->duplicateCheck($request);
        $fields = [
            'collectionId' => $request->collectionId,
            'title' => $request->title,
            ...($request->parentId === null ? [] : ['parentId' => $request->parentId]),
            ...($request->markdown === null ? [] : ['markdown' => $request->markdown]),
        ];
        $plan = [Plan::step(null, 'note.document.add', 3, 'write', [
            'collectionId' => $request->collectionId, 'parentId' => $request->parentId, 'title' => $request->title,
            ...$this->markdownSummary($request->markdown), 'duplicateCheck' => $check['status'],
        ])];

        return new PreparedOperation($plan, fn (): OperationResult => $this->add($request, $fields, $check), 'new article "' . $request->title . '" in knowledge base ' . $request->collectionId);
    }

    public function prepareUpdate(UpdateArticleRequest $request): PreparedOperation
    {
        $fields = [
            ...($request->title === null ? [] : ['title' => $request->title]),
            ...($request->markdown === null ? [] : ['markdown' => $request->markdown]),
        ];
        $plan = [Plan::step(null, 'note.document.update', 3, 'write', [
            'id' => $request->id, 'title' => $request->title, ...$this->markdownSummary($request->markdown), 'overwrite' => $request->overwrite,
        ])];

        return new PreparedOperation($plan, fn (): OperationResult => $this->update($request, $fields), 'article ' . $request->id);
    }

    /** @return array{status: string, siblings: int} */
    private function duplicateCheck(AddArticleRequest $request): array
    {
        if ($request->allowDuplicateTitle) {
            return ['status' => 'skipped', 'siblings' => 0];
        }

        $tree = $this->gateway->tree($request->collectionId);
        $siblings = $this->siblings($tree['items'], $request->parentId);
        $duplicates = array_values(array_map(
            static fn (array $node): array => ['id' => $node['id'] ?? null, 'parentId' => $node['parentId'] ?? null, 'title' => $node['title']],
            array_filter($siblings, static fn (array $node): bool => is_string($node['title'] ?? null) && trim($node['title']) === $request->title),
        ));
        $details = ['collectionId' => $request->collectionId, 'parentId' => $request->parentId, 'title' => $request->title, 'candidates' => $duplicates, 'truncated' => $tree['truncated']];
        if ($duplicates !== []) {
            throw new Failure('article-exists', 'An article with exactly this title already exists under the same parent; update it with kb:article:update or pass --allow-duplicate-title. Nothing was created.', 4, $details);
        }

        if ($tree['truncated']) {
            throw new Failure('search-incomplete', 'The article tree is truncated, so the title is not proven unused; pass --allow-duplicate-title to create anyway. Nothing was created.', 4, $details);
        }

        return ['status' => 'none', 'siblings' => count($siblings)];
    }

    /**
     * @param array<string, mixed> $fields
     * @param array{status: string, siblings: int} $check
     */
    private function add(AddArticleRequest $request, array $fields, array $check): OperationResult
    {
        try {
            $item = $this->gateway->add($fields, $check['status'] === 'none');
        } catch (Failure $failure) {
            throw $this->unknown($failure, 'The article may have been created; check kb:article:list --collection ' . $request->collectionId . ' before creating it again.');
        }

        $mismatch = [];
        foreach (['title' => $request->title, 'collectionId' => $request->collectionId, 'parentId' => $request->parentId] as $field => $expected) {
            if (($item[$field] ?? null) !== $expected) {
                $mismatch[] = $field;
            }
        }

        return $this->written('create', $item, $mismatch, $request->markdown, ['duplicateCheck' => $check]);
    }

    /** @param array<string, mixed> $fields */
    private function update(UpdateArticleRequest $request, array $fields): OperationResult
    {
        try {
            $item = $this->gateway->update($request->id, $fields, $request->overwrite);
        } catch (Failure $failure) {
            throw $this->unknown($failure, 'The article may have been changed; check kb:article:show ' . $request->id . ' before writing again.');
        }

        $mismatch = $request->title !== null && ($item['title'] ?? null) !== $request->title ? ['title'] : [];
        return $this->written('update', $item, $mismatch, $request->markdown, ['changedFields' => array_keys($fields), 'overwrite' => $request->overwrite]);
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $mismatch
     * @param array<string, mixed> $extra
     */
    private function written(string $action, array $item, array $mismatch, ?string $markdown, array $extra): OperationResult
    {
        $verified = $mismatch === [];
        $operation = [
            'action' => $action, 'id' => $item['id'], 'collectionId' => $item['collectionId'] ?? null, 'parentId' => $item['parentId'] ?? null, 'title' => $item['title'] ?? null,
            'confirmed' => true, 'readback' => $verified ? 'verified' : 'mismatch', 'mismatch' => $mismatch,
            'markdownNormalized' => $markdown !== null && ($item['markdown'] ?? null) !== $markdown,
        ];

        return new OperationResult('kb-article-written', ['operation' => $operation, 'article' => $item, ...$extra], [
            'complete' => $verified,
            'reason' => $verified ? null : 'readback-mismatch',
            'outcomeUnknown' => false,
        ], $verified ? 0 : 3);
    }

    private function unknown(Failure $failure, string $hint): Failure
    {
        return $failure->outcomeUnknown ? new Failure($failure->errorCode, $failure->getMessage() . ' ' . $hint, $failure->exitStatus, $failure->details, true) : $failure;
    }

    /**
     * Direct children of $parentId (roots when null) in note.document.tree.list nodes.
     *
     * @param list<array<string, mixed>> $nodes
     * @return list<array<string, mixed>>
     */
    private function siblings(array $nodes, ?int $parentId): array
    {
        if ($parentId === null) {
            return array_values(array_filter($nodes, is_array(...)));
        }

        $flat = KnowledgeBaseReadService::flatten($nodes);
        return array_values(array_filter($flat, static fn (array $node): bool => ($node['parentId'] ?? null) === $parentId));
    }

    /** @return array{markdownBytes: int|null, markdownSha256: string|null} */
    private function markdownSummary(?string $markdown): array
    {
        return ['markdownBytes' => $markdown === null ? null : strlen($markdown), 'markdownSha256' => $markdown === null ? null : hash('sha256', $markdown)];
    }
}
