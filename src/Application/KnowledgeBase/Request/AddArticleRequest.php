<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\KnowledgeBase\Request;

/**
 * Normalized kb:article:add input. $title is trimmed (1…255 characters); $markdown is the
 * file content unchanged (at most 1048576 bytes) or null when no file was given.
 */
final readonly class AddArticleRequest
{
    public function __construct(public int $collectionId, public string $title, public ?int $parentId, public ?string $markdown, public bool $allowDuplicateTitle)
    {
    }
}
