<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Integration\KnowledgeBase;

use Bitrix24\CLI\Tests\Support\LiveKnowledgeBaseCommandTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('knowledge-base')]
#[Group('command:kb:list')]
final class ListKnowledgeBasesCommandTest extends LiveKnowledgeBaseCommandTestCase
{
    #[Group('happy-path')]
    public function testHappyPathListsTheCreatedKnowledgeBase(): void
    {
        $json = $this->invoke(['kb:list']);
        $items = array_column($json['data']['items'], null, 'id');
        self::assertArrayHasKey($this->collectionId, $items);
        self::assertStringStartsWith('b24cli-kb ', $items[$this->collectionId]['name']);
        self::assertArrayHasKey('policyLevel', $items[$this->collectionId]);
        self::assertSame(['note.collection.list'], array_values(array_unique(array_column($json['meta']['apiCalls'], 'method'))));
    }
}
