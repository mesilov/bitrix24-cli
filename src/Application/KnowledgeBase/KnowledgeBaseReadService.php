<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\KnowledgeBase;

use Bitrix24\CLI\Application\KnowledgeBase\Port\KnowledgeBaseGateway;
use Bitrix24\CLI\Application\KnowledgeBase\Request\ListArticlesRequest;
use Bitrix24\CLI\Application\KnowledgeBase\Request\SearchArticlesRequest;
use Bitrix24\CLI\Application\KnowledgeBase\Request\ShowArticleRequest;
use Bitrix24\CLI\Application\OperationResult;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Route;

/**
 * Reads of Knowledge Base 2.0: accessible knowledge bases (KB-01), the article tree of one
 * knowledge base and full-text search (KB-02), one article with its Markdown (KB-03).
 * Items are passed through with REST 3.0 field names; incomplete reads are exit 3.
 */
final readonly class KnowledgeBaseReadService
{
    public const PAGE_SIZE = 200;

    public function __construct(private KnowledgeBaseGateway $gateway)
    {
    }

    /** @return list<Route> */
    public function collectionRoutes(): array
    {
        return [new Route('note.collection.list', 3)];
    }

    /** @return list<Route> */
    public function treeRoutes(): array
    {
        return [new Route('note.document.tree.list', 3)];
    }

    /** @return list<Route> */
    public function searchRoutes(): array
    {
        return [new Route('note.document.search.list', 3)];
    }

    /** @return list<Route> */
    public function articleRoutes(): array
    {
        return [new Route('note.document.get', 3)];
    }

    public function prepareCollections(): PreparedOperation
    {
        return new PreparedOperation([], $this->collections(...));
    }

    public function prepareTree(ListArticlesRequest $request): PreparedOperation
    {
        return new PreparedOperation([], fn (): OperationResult => $this->tree($request));
    }

    public function prepareSearch(SearchArticlesRequest $request): PreparedOperation
    {
        return new PreparedOperation([], fn (): OperationResult => $this->search($request));
    }

    public function prepareArticle(ShowArticleRequest $request): PreparedOperation
    {
        return new PreparedOperation([], fn (): OperationResult => new OperationResult(
            $request->markdownOnly ? 'kb-article-markdown' : 'kb-article',
            ['article' => $this->gateway->article($request->id)],
        ));
    }

    private function collections(): OperationResult
    {
        $items = [];
        $seenIds = [];
        $seenCursors = [];
        $cursor = null;
        $pages = 0;
        $reason = null;
        do {
            $page = $this->gateway->collections($cursor, self::PAGE_SIZE);
            $pages++;
            $fresh = array_values(array_filter($page['items'], static fn (array $item): bool => !isset($seenIds[$item['id']])));
            foreach ($fresh as $item) {
                $seenIds[$item['id']] = true;
                $items[] = $item;
            }

            $cursor = $page['nextCursor'];
            $key = $cursor === null ? null : $cursor['position'] . ':' . $cursor['id'];
            if ($cursor !== null && (isset($seenCursors[$key]) || $fresh === [])) {
                $reason = 'pagination-no-progress';
                break;
            }

            if ($key !== null) {
                $seenCursors[$key] = true;
            }
        } while ($cursor !== null);

        return new OperationResult('kb-collections', ['items' => $items], ['complete' => $reason === null, 'reason' => $reason, 'pages' => $pages, 'count' => count($items)], $reason === null ? 0 : 3);
    }

    private function tree(ListArticlesRequest $request): OperationResult
    {
        $tree = $this->gateway->tree($request->collectionId);
        $truncated = $tree['truncated'];
        return new OperationResult('kb-tree', ['collectionId' => $request->collectionId, 'items' => $tree['items']], [
            'complete' => !$truncated,
            'reason' => $truncated ? 'tree-truncated' : null,
            'truncated' => $truncated,
            'count' => count(self::flatten($tree['items'])),
            ...($truncated ? ['hint' => 'The tree exceeded the portal node limit and was cut by root nodes; find the article with kb:article:search.'] : []),
        ], $truncated ? 3 : 0);
    }

    private function search(SearchArticlesRequest $request): OperationResult
    {
        $result = $this->gateway->search($request->query, $request->limit);
        return new OperationResult('kb-search', ['query' => $request->query, 'items' => $result['items']], [
            'complete' => true,
            'hasMore' => $result['hasMore'],
            'limit' => $request->limit,
            ...($result['hasMore'] ? ['hint' => 'More results exist beyond this page; refine --query or raise --limit (up to 200).'] : []),
        ]);
    }

    /**
     * Depth-first flattening of note.document.tree.list nodes for line-oriented output.
     *
     * @param list<array<string, mixed>> $nodes
     * @return list<array<string, mixed>> nodes without children, with depth from 0
     */
    public static function flatten(array $nodes, int $depth = 0): array
    {
        $rows = [];
        foreach ($nodes as $node) {
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            unset($node['children']);
            $rows[] = [...$node, 'depth' => $depth];
            array_push($rows, ...self::flatten($children, $depth + 1));
        }

        return $rows;
    }
}
