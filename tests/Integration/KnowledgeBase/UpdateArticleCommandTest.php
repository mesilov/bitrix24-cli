<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\KnowledgeBase;

use Bitrix24\CLI\Tests\Support\LiveKnowledgeBaseCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('knowledge-base')]
#[Group('command:kb:article:update')]
final class UpdateArticleCommandTest extends LiveKnowledgeBaseCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathReplacesContentThenTitle(): void
    {
        $id = $this->article('Отпуск', "# Отпуск\n\nСтарый текст\n");
        $markdown = "# Отпуск\n\nНовый текст\n";
        $updated = $this->invoke(['kb:article:update', $id, '--markdown-file', $this->markdownFile($markdown)]);
        self::assertSame(['update', 'verified', ['markdown']], [$updated['data']['operation']['action'], $updated['data']['operation']['readback'], $updated['data']['changedFields']]);
        self::assertSame([$markdown, 'Отпуск'], [$this->readArticle($id)['markdown'], $this->readArticle($id)['title']]);

        $renamed = $this->invoke(['kb:article:update', $id, '--title', 'Отпуск 2026']);
        self::assertSame('verified', $renamed['data']['operation']['readback']);
        self::assertSame(['Отпуск 2026', $markdown], [$this->readArticle($id)['title'], $this->readArticle($id)['markdown']]);
    }

    public function testMissingArticleIsExplicit(): void
    {
        $json = $this->invoke(['kb:article:update', 2147483000, '--title', 'Нет'], [1]);
        self::assertSame('article-not-found', $json['error']['code']);
    }
}
