<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console;

use Bitrix24\CLI\Application\Failure;
use Bitrix24\CLI\Application\KnowledgeBase\Request\AddArticleRequest;
use Bitrix24\CLI\Application\KnowledgeBase\Request\ListArticlesRequest;
use Bitrix24\CLI\Application\KnowledgeBase\Request\SearchArticlesRequest;
use Bitrix24\CLI\Application\KnowledgeBase\Request\ShowArticleRequest;
use Bitrix24\CLI\Application\KnowledgeBase\Request\UpdateArticleRequest;
use Symfony\Component\Console\Input\InputInterface;

/**
 * kb:* input. Limits repeat the portal ones (title 255 characters, Markdown 1048576 bytes,
 * search query 3…200 characters, page 1…200) so mistakes fail before credentials and API.
 */
final readonly class KnowledgeBaseInputMapper
{
    public const MAX_TITLE = 255;

    public const MAX_MARKDOWN_BYTES = 1048576;

    public function __construct(private TaskInputMapper $numbers, private InputSourceReader $reader)
    {
    }

    public function tree(InputInterface $input): ListArticlesRequest
    {
        return new ListArticlesRequest($this->collection($input));
    }

    public function search(InputInterface $input): SearchArticlesRequest
    {
        $query = $input->getOption('query');
        $query = is_string($query) ? trim($query) : '';
        if (mb_strlen($query) < 3 || mb_strlen($query) > 200) {
            throw Failure::usage('Pass --query with 3 to 200 characters.');
        }

        $limit = $this->numbers->id($input->getOption('limit') ?? 50, '--limit');
        if ($limit > 200) {
            throw Failure::usage('--limit accepts 1 to 200.');
        }

        return new SearchArticlesRequest($query, $limit);
    }

    public function show(InputInterface $input): ShowArticleRequest
    {
        $markdownOnly = (bool) $input->getOption('markdown');
        if ($markdownOnly && ($input->getOption('json') || $input->getOption('plain'))) {
            throw Failure::usage('--markdown prints only the article content; do not combine it with --json or --plain.');
        }

        return new ShowArticleRequest($this->numbers->id($input->getArgument('ID'), 'ID'), $markdownOnly);
    }

    public function add(InputInterface $input): AddArticleRequest
    {
        $collection = $this->collection($input);
        $title = $this->title($input->getOption('title'));
        if ($title === null) {
            throw Failure::usage('Pass --title with 1 to 255 characters.');
        }

        $parent = $input->getOption('parent') === null ? null : $this->numbers->id($input->getOption('parent'), '--parent');
        return new AddArticleRequest($collection, $title, $parent, $this->markdown($input), (bool) $input->getOption('allow-duplicate-title'));
    }

    public function update(InputInterface $input): UpdateArticleRequest
    {
        $id = $this->numbers->id($input->getArgument('ID'), 'ID');
        $title = $this->title($input->getOption('title'));
        $markdown = $this->markdown($input);
        if ($title === null && $markdown === null) {
            throw Failure::usage('Pass --title, --markdown-file or both; note.document.update needs a changed field.');
        }

        return new UpdateArticleRequest($id, $title, $markdown, (bool) $input->getOption('overwrite'));
    }

    private function collection(InputInterface $input): int
    {
        if ($input->getOption('collection') === null) {
            throw Failure::usage('Pass --collection with the knowledge base ID from kb:list.');
        }

        return $this->numbers->id($input->getOption('collection'), '--collection');
    }

    private function title(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $title = is_string($value) ? trim($value) : '';
        if ($title === '' || mb_strlen($title) > self::MAX_TITLE) {
            throw Failure::usage('Pass --title with 1 to 255 characters.');
        }

        return $title;
    }

    /** The file content unchanged: no trimming or newline normalization. */
    private function markdown(InputInterface $input): ?string
    {
        $path = $input->getOption('markdown-file');
        if ($path === null) {
            return null;
        }

        try {
            return $this->reader->read((string) $path, self::MAX_MARKDOWN_BYTES);
        } catch (Failure $failure) {
            if ($failure->getMessage() === 'Input exceeds the allowed size.') {
                throw Failure::usage('The --markdown-file content exceeds 1048576 bytes, the article size limit.');
            }

            throw $failure;
        }
    }
}
