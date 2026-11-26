<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\KnowledgeBase;

use Bitrix24\CLI\Application\KnowledgeBase\KnowledgeBaseReadService;
use Bitrix24\CLI\Application\KnowledgeBase\Request\SearchArticlesRequest;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\KnowledgeBaseInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'kb:article:search', description: 'Найти статьи базы знаний по заголовку и тексту')]
final class SearchArticlesCommand extends Command
{
    public function __construct(private readonly KnowledgeBaseReadService $service, private readonly KnowledgeBaseInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('query', null, InputOption::VALUE_REQUIRED, 'Поисковый запрос, от 3 до 200 символов.');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Размер страницы выдачи, от 1 до 200 (по умолчанию 50).');
        $this->setHelp(<<<'HELP'
            Ищет статьи по заголовку и содержимому во всех доступных базах знаний и в статьях с прямым доступом: note.document.search.list (REST 3.0, область доступа note). Выдача ранжирована; курсора у метода нет.

            meta.hasMore = true означает, что за пределами страницы есть ещё результаты: уточните --query или увеличьте --limit. Это не ошибка (exit 0). Запрос короче 3 или длиннее 200 символов — usage-error до обращения к API.

            JSON data.items: documentId, collectionId (null при прямом доступе), title, score, snippet (HTML с выделением <mark>, как вернул портал), sharedAccess. Human показывает snippet без тегов. TSV при --plain: documentId, collectionId, title, score, sharedAccess. Пример: b24cli kb:article:search --query 'отпуск' --json
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): SearchArticlesRequest => $this->mapper->search($input),
            fn (): array => $this->service->searchRoutes(),
            fn (SearchArticlesRequest $request): PreparedOperation => $this->service->prepareSearch($request),
        );
    }
}
