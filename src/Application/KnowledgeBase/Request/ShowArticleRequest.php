<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\KnowledgeBase\Request;

/** Normalized kb:article:show input; $markdownOnly prints only the stored Markdown. */
final readonly class ShowArticleRequest
{
    public function __construct(public int $id, public bool $markdownOnly)
    {
    }
}
