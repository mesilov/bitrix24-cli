<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\KnowledgeBase;

use Bitrix24\CLI\Tests\Support\LiveKnowledgeBaseCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('knowledge-base')]
#[Group('command:kb:article:list')]
final class ListArticlesCommandTest extends LiveKnowledgeBaseCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathReturnsTheNestedTree(): void
    {
        $root = $this->article('Регламенты');
        $child = $this->article('Отпуск', 'текст', $root);
        $json = $this->invoke(['kb:article:list', '--collection', $this->collectionId]);
        self::assertSame([$root], array_column($json['data']['items'], 'id'));
        self::assertSame([[$child, $root, 'Отпуск']], array_map(static fn (array $node): array => [$node['id'], $node['parentId'], $node['title']], $json['data']['items'][0]['children']));
        self::assertSame(2, $json['meta']['count']);
    }

    public function testMissingKnowledgeBaseIsExplicit(): void
    {
        $json = $this->invoke(['kb:article:list', '--collection', 2147483000], [1]);
        self::assertSame('collection-not-found', $json['error']['code']);
    }
}
