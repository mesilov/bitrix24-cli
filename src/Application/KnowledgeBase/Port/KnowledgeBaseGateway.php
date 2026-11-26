<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\KnowledgeBase\Port;

/**
 * Knowledge Base 2.0 through REST 3.0 (note.*, scope note).
 *
 * Items keep the documented REST 3.0 field names exactly as the portal returns them,
 * including undocumented extras (state, accessLevel, markdownDescription), which callers
 * pass through without interpreting. Errors arrive as Failure with CLI error codes.
 */
interface KnowledgeBaseGateway
{
    /**
     * One page of note.collection.list.
     *
     * @param array{position: int, id: int}|null $afterCursor nextCursor of the previous page
     * @return array{items: list<array<string, mixed>>, nextCursor: array{position: int, id: int}|null}
     */
    public function collections(?array $afterCursor, int $limit): array;

    /**
     * note.document.tree.list: root nodes with nested children; archived articles are absent.
     *
     * @return array{items: list<array<string, mixed>>, truncated: bool}
     */
    public function tree(int $collectionId): array;

    /**
     * note.document.search.list; the method has no cursor, only a page limit.
     *
     * @return array{items: list<array<string, mixed>>, hasMore: bool}
     */
    public function search(string $query, int $limit): array;

    /** @return array<string, mixed> note.document.get item */
    public function article(int $id): array;

    /**
     * Calls note.document.add exactly once.
     *
     * @param array{collectionId: int, title: string, parentId?: int, markdown?: string} $fields
     * @param bool $collectionConfirmed the collection was already read, so a missing entity is the parent
     * @return array<string, mixed> created item
     */
    public function add(array $fields, bool $collectionConfirmed): array;

    /**
     * Calls note.document.update exactly once; overwrite=true is sent only when requested.
     *
     * @param array{title?: string, markdown?: string} $fields
     * @return array<string, mixed> item after the update
     */
    public function update(int $id, array $fields, bool $overwrite): array;
}
