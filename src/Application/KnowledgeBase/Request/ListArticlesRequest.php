<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\KnowledgeBase\Request;

/** Normalized kb:article:list input. */
final readonly class ListArticlesRequest
{
    public function __construct(public int $collectionId)
    {
    }
}
