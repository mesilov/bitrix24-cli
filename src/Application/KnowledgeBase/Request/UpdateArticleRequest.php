<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\KnowledgeBase\Request;

/**
 * Normalized kb:article:update input; at least one of $title and $markdown is set.
 * $markdown replaces the whole article content.
 */
final readonly class UpdateArticleRequest
{
    public function __construct(public int $id, public ?string $title, public ?string $markdown, public bool $overwrite)
    {
    }
}
