<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Tests\Support;

use Bitrix24\CLI\Application\Failure;

/**
 * Each case gets a disposable Knowledge Base 2.0 collection with a unique name, created and
 * filled through SDK calls (never through another CLI command) and moved to the trash with
 * note.collection.delete afterwards, which cascades to its articles.
 */
abstract class LiveKnowledgeBaseCommandTestCase extends LiveCommandTestCase
{
    protected int $collectionId;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $item = $this->api->call('note.collection.add', 3, ['fields' => ['name' => 'b24cli-kb ' . bin2hex(random_bytes(4))]], 'write')->result['item'] ?? null;
        self::assertIsArray($item, 'note.collection.add must return the created knowledge base.');
        $this->collectionId = (int) $item['id'];
        self::assertGreaterThan(0, $this->collectionId);
    }

    /** Creates an article through the SDK and returns its ID. */
    protected function article(string $title, string $markdown = '', ?int $parentId = null): int
    {
        $fields = ['collectionId' => $this->collectionId, 'title' => $title, 'markdown' => $markdown, ...($parentId === null ? [] : ['parentId' => $parentId])];
        $id = (int) ($this->api->call('note.document.add', 3, ['fields' => $fields], 'write')->result['item']['id'] ?? 0);
        self::assertGreaterThan(0, $id);
        return $id;
    }

    /** @return array<string, mixed> */
    protected function readArticle(int $id): array
    {
        return $this->api->call('note.document.get', 3, ['id' => $id])->result['item'];
    }

    protected function markdownFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'b24cli-kb-');
        file_put_contents($path, $content);
        $this->files[] = $path;
        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $path) {
            @unlink($path);
        }

        if (isset($this->collectionId)) {
            try {
                $this->api->call('note.collection.delete', 3, ['id' => $this->collectionId], 'delete');
            } catch (Failure) {
                // The evidence of the base class records the commands; a leftover test collection is visible by name.
            }
        }

        parent::tearDown();
    }
}
