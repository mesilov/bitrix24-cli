<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\KnowledgeBase;

use Bitrix24\CLI\Application\KnowledgeBase\KnowledgeBaseReadService;
use Bitrix24\CLI\Application\KnowledgeBase\Request\ShowArticleRequest;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\KnowledgeBaseInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'kb:article:show', description: 'Показать статью базы знаний с содержимым в Markdown')]
final class ShowArticleCommand extends Command
{
    public function __construct(private readonly KnowledgeBaseReadService $service, private readonly KnowledgeBaseInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('ID', InputArgument::REQUIRED, 'ID статьи из kb:article:list или documentId из kb:article:search.');
        $this->addOption('markdown', null, InputOption::VALUE_NONE, 'Вывести только содержимое статьи в Markdown, без изменений; несовместимо с --json и --plain.');
        $this->setHelp(<<<'HELP'
            Возвращает одну статью: note.document.get (REST 3.0, область доступа note). Нет статьи или нет доступа — article-not-found.

            --markdown пишет в stdout ровно значение markdown из API, без заголовков и добавленных символов. Так статью правят в файле: b24cli kb:article:show 77 --markdown > article.md, затем b24cli kb:article:update 77 --markdown-file article.md. Обновление заменяет содержимое целиком.

            JSON data.article: поля REST 3.0 как есть — id, collectionId (null при прямом доступе), parentId, title, markdown, position, createdBy, updatedBy, createdAt, updatedAt, contentUpdatedAt, isArchived, isTrashed, isOrphan и другие. Human показывает поля и содержимое. TSV при --plain: id, collectionId, parentId, title, updatedAt. Пример: b24cli kb:article:show 77 --json
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): ShowArticleRequest => $this->mapper->show($input),
            fn (): array => $this->service->articleRoutes(),
            fn (ShowArticleRequest $request): PreparedOperation => $this->service->prepareArticle($request),
        );
    }
}
