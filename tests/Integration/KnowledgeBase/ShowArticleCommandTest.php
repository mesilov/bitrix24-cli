<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\KnowledgeBase;

use Bitrix24\CLI\Tests\Support\ConsoleHarness;
use Bitrix24\CLI\Tests\Support\LiveKnowledgeBaseCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('knowledge-base')]
#[Group('command:kb:article:show')]
final class ShowArticleCommandTest extends LiveKnowledgeBaseCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathReturnsTheArticleAndItsMarkdownByteForByte(): void
    {
        $markdown = "# Отпуск\n\nТекст **жирный**\n\n| a | b |\n|---|---|\n| 1 | 2 |\n";
        $id = $this->article('Отпуск', $markdown);
        $json = $this->invoke(['kb:article:show', $id]);
        self::assertSame([$id, $this->collectionId, 'Отпуск', $markdown], [$json['data']['article']['id'], $json['data']['article']['collectionId'], $json['data']['article']['title'], $json['data']['article']['markdown']]);

        $run = ConsoleHarness::run($this->app, ['kb:article:show', (string) $id, '--markdown']);
        self::assertSame(0, $run->status, $run->stderr);
        self::assertSame($markdown, $run->stdout);
    }

    public function testMissingArticleIsExplicit(): void
    {
        $json = $this->invoke(['kb:article:show', 2147483000], [1]);
        self::assertSame('article-not-found', $json['error']['code']);
    }
}
