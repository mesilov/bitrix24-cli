<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Infrastructure\Bitrix24;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\KnowledgeBase\Port\KnowledgeBaseGateway;

/**
 * Knowledge Base 2.0, REST 3.0, scope note:
 * https://apidocs.bitrix24.ru/api-reference/note/document/index.html
 *
 * Error codes below were observed on a test portal on 2026-11-26; the documentation lists
 * only ACCESS_DENIED. The collaborative editor conflict of note.document.update could not be
 * reproduced through REST, so an unknown error of a markdown write hints at --overwrite.
 */
final readonly class Rest3KnowledgeBaseAdapter implements KnowledgeBaseGateway
{
    private const NOT_FOUND = 'BITRIX_REST_V3_EXCEPTION_ENTITYNOTFOUNDEXCEPTION';

    /** Validation-like note.* codes: field and CLI option they belong to. */
    private const VALIDATION_CODES = [
        'NOTE_INVALID_PARENT' => ['parentId', '--parent'],
        'NOTE_MARKDOWN_TOO_LARGE' => ['markdown', '--markdown-file'],
        'NOTE_SEARCH_QUERY_TOO_SHORT' => ['query', '--query'],
        'NOTE_EMPTY_UPDATE' => ['fields', '--title'],
    ];

    private const OPTIONS = ['title' => '--title', 'markdown' => '--markdown-file', 'collectionId' => '--collection', 'parentId' => '--parent', 'query' => '--query', 'pagination.limit' => '--limit'];

    /** Codes whose meaning is known; any other markdown write error may be the editor conflict. */
    private const KNOWN_CODES = [self::NOT_FOUND, 'NOTE_INVALID_PARENT', 'NOTE_MARKDOWN_TOO_LARGE', 'NOTE_SEARCH_QUERY_TOO_SHORT', 'NOTE_EMPTY_UPDATE', 'ACCESS_DENIED', 'INSUFFICIENT_SCOPE', 'INTERNAL_SERVER_ERROR'];

    public function __construct(private ApiTransport $api)
    {
    }

    public function collections(?array $afterCursor, int $limit): array
    {
        $pagination = ['limit' => $limit, ...($afterCursor === null ? [] : ['afterCursor' => $afterCursor])];
        $result = $this->call('note.collection.list', ['pagination' => $pagination], 'collection');
        $items = $result['items'] ?? null;
        $cursor = $result['nextCursor'] ?? null;
        if (!is_array($items) || !array_is_list($items) || !array_all($items, static fn (mixed $item): bool => is_array($item) && is_int($item['id'] ?? null))) {
            throw new Failure('api-error', 'The knowledge base list response has an unsupported shape.');
        }

        if ($cursor !== null && (!is_array($cursor) || !is_int($cursor['position'] ?? null) || !is_int($cursor['id'] ?? null))) {
            throw new Failure('api-error', 'The knowledge base list returned an unsupported nextCursor.');
        }

        return ['items' => $items, 'nextCursor' => $cursor === null ? null : ['position' => $cursor['position'], 'id' => $cursor['id']]];
    }

    public function tree(int $collectionId): array
    {
        $result = $this->call('note.document.tree.list', ['collectionId' => $collectionId], 'collection');
        $items = $result['items'] ?? null;
        if (!is_array($items) || !array_is_list($items)) {
            throw new Failure('api-error', 'The article tree response has an unsupported shape.');
        }

        return ['items' => $items, 'truncated' => ($result['truncated'] ?? false) === true];
    }

    public function search(string $query, int $limit): array
    {
        $result = $this->call('note.document.search.list', ['query' => $query, 'pagination' => ['limit' => $limit]], 'article');
        $items = $result['items'] ?? null;
        if (!is_array($items) || !array_is_list($items)) {
            throw new Failure('api-error', 'The article search response has an unsupported shape.');
        }

        return ['items' => $items, 'hasMore' => ($result['hasMore'] ?? false) === true];
    }

    public function article(int $id): array
    {
        $item = $this->call('note.document.get', ['id' => $id], 'article')['item'] ?? null;
        if (!is_array($item) || ($item['id'] ?? null) !== $id) {
            throw new Failure('api-error', 'The requested article was not returned by Bitrix24.');
        }

        return $item;
    }

    public function add(array $fields, bool $collectionConfirmed): array
    {
        $missing = $collectionConfirmed && array_key_exists('parentId', $fields) ? 'parent' : 'collection';
        $item = $this->call('note.document.add', ['fields' => $fields], $missing, 'write')['item'] ?? null;
        if (!is_array($item) || !is_int($item['id'] ?? null) || $item['id'] < 1) {
            throw new Failure('api-error', 'note.document.add did not return the created article; it may have been created.', 1, ['collectionId' => $fields['collectionId'], 'title' => $fields['title']], true);
        }

        return $item;
    }

    public function update(int $id, array $fields, bool $overwrite): array
    {
        $parameters = ['id' => $id, 'fields' => $fields, ...($overwrite ? ['overwrite' => true] : [])];
        $item = $this->call('note.document.update', $parameters, 'article', 'write', array_key_exists('markdown', $fields) && !$overwrite)['item'] ?? null;
        if (!is_array($item) || ($item['id'] ?? null) !== $id) {
            throw new Failure('api-error', 'note.document.update did not return the article; it may have been changed.', 1, ['id' => $id], true);
        }

        return $item;
    }

    /**
     * @param 'collection'|'parent'|'article' $missing what a missing entity means for this call
     * @return array<string, mixed>
     */
    private function call(string $method, array $parameters, string $missing, string $effect = 'read', bool $conflictHint = false): array
    {
        try {
            return $this->api->call($method, 3, $parameters, $effect)->result;
        } catch (Failure $failure) {
            throw $this->translate($failure, $missing, $conflictHint);
        }
    }

    private function translate(Failure $failure, string $missing, bool $conflictHint): Failure
    {
        $code = $failure->details['apiErrorCode'] ?? null;
        if ($failure->errorCode === 'api-validation-error') {
            $validation = array_map(static fn (array $error): array => [...$error, 'option' => $error['option'] ?? self::OPTIONS[$error['field'] ?? ''] ?? null], $failure->details['validation'] ?? []);
            return new Failure('api-validation-error', $failure->getMessage(), $failure->exitStatus, [...$failure->details, 'validation' => $validation], $failure->outcomeUnknown);
        }

        if ($failure->errorCode === 'permission-denied') {
            return new Failure('permission-denied', 'Bitrix24 denied the Knowledge Base operation; the webhook needs scope note, access to the knowledge base, and a plan with REST API.', $failure->exitStatus, $failure->details, $failure->outcomeUnknown);
        }

        if ($failure->errorCode !== 'api-error' || !is_string($code)) {
            return $this->hinted($failure, $conflictHint && $failure->errorCode === 'api-error');
        }

        if ($code === self::NOT_FOUND) {
            return match ($missing) {
                'article' => new Failure('article-not-found', 'The article does not exist or is not accessible to the connection user.', 1, $failure->details),
                'parent' => new Failure('parent-not-found', 'The parent article does not exist or is not accessible; check --parent.', 1, $failure->details),
                default => new Failure('collection-not-found', 'The knowledge base does not exist or is not accessible; list them with kb:list.', 1, $failure->details),
            };
        }

        if (isset(self::VALIDATION_CODES[$code])) {
            [$field, $option] = self::VALIDATION_CODES[$code];
            $message = match ($code) {
                'NOTE_INVALID_PARENT' => 'The parent article belongs to another knowledge base.',
                'NOTE_MARKDOWN_TOO_LARGE' => 'The article content exceeds 1048576 bytes.',
                'NOTE_SEARCH_QUERY_TOO_SHORT' => 'The search query is shorter than 3 characters.',
                default => 'The update contains no changeable fields.',
            };
            return new Failure('api-validation-error', 'Bitrix24 rejected the supplied fields.', 1, [...$failure->details, 'validation' => [['field' => $field, 'message' => $message, 'option' => $option]]]);
        }

        return $this->hinted($failure, $conflictHint && !in_array($code, self::KNOWN_CODES, true));
    }

    private function hinted(Failure $failure, bool $conflictHint): Failure
    {
        if (!$conflictHint) {
            return $failure;
        }

        return new Failure($failure->errorCode, $failure->getMessage() . ' If the article has unsaved changes in the collaborative editor, repeat with --overwrite only when its content must be replaced.', $failure->exitStatus, [...$failure->details, 'hint' => '--overwrite'], $failure->outcomeUnknown);
    }
}
