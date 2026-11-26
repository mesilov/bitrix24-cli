<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\KnowledgeBase;

use Bitrix24\CLI\Tests\Support\LiveKnowledgeBaseCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('knowledge-base')]
#[Group('command:kb:article:search')]
final class SearchArticlesCommandTest extends LiveKnowledgeBaseCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathFindsArticleByContent(): void
    {
        $token = 'b24clikb' . bin2hex(random_bytes(5));
        $id = $this->article('Поиск', 'Статья про ' . $token);
        $json = $this->invoke(['kb:article:search', '--query', $token]);
        $found = array_column($json['data']['items'], null, 'documentId');
        self::assertArrayHasKey($id, $found, 'The portal indexed new articles immediately on 2026-11-26.');
        self::assertSame($this->collectionId, $found[$id]['collectionId']);
        self::assertStringContainsString($token, strip_tags($found[$id]['snippet']));
    }
}
