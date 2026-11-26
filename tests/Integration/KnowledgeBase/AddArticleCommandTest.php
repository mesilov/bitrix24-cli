<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\KnowledgeBase;

use Bitrix24\CLI\Tests\Support\LiveKnowledgeBaseCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('knowledge-base')]
#[Group('command:kb:article:add')]
final class AddArticleCommandTest extends LiveKnowledgeBaseCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathCreatesOnceAndBlocksTheDuplicate(): void
    {
        $markdown = "# Регламент\n\nТекст\n";
        $file = $this->markdownFile($markdown);
        $plan = $this->invoke(['kb:article:add', '--collection', $this->collectionId, '--title', 'Регламент', '--markdown-file', $file, '--dry-run']);
        self::assertSame(['note.document.add'], array_column($plan['data']['plan']['steps'], 'method'));
        self::assertNotContains('note.document.add', array_column($plan['meta']['apiCalls'], 'method'));

        $created = $this->invoke(['kb:article:add', '--collection', $this->collectionId, '--title', 'Регламент', '--markdown-file', $file]);
        $id = $created['data']['operation']['id'];
        self::assertSame(['create', true, 'verified'], [$created['data']['operation']['action'], $created['data']['operation']['confirmed'], $created['data']['operation']['readback']]);
        self::assertSame(1, count(array_filter($created['meta']['apiCalls'], static fn (array $call): bool => $call['method'] === 'note.document.add')));
        $article = $this->readArticle($id);
        self::assertSame([$this->collectionId, null, 'Регламент', $markdown], [$article['collectionId'], $article['parentId'], $article['title'], $article['markdown']]);

        $child = $this->invoke(['kb:article:add', '--collection', $this->collectionId, '--parent', $id, '--title', 'Дочерняя']);
        self::assertSame($id, $this->readArticle($child['data']['operation']['id'])['parentId']);

        $duplicate = $this->invoke(['kb:article:add', '--collection', $this->collectionId, '--title', 'Регламент'], [4]);
        self::assertSame('article-exists', $duplicate['error']['code']);
        self::assertSame([$id], array_column($duplicate['error']['details']['candidates'], 'id'));
        self::assertNotContains('note.document.add', array_column($duplicate['meta']['apiCalls'], 'method'));
    }
}
