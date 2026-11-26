<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\KnowledgeBase\Request;

/** Normalized kb:article:search input: trimmed query of 3…200 characters, limit 1…200. */
final readonly class SearchArticlesRequest
{
    public function __construct(public string $query, public int $limit)
    {
    }
}
