<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\KnowledgeBase;

use Bitrix24\CLI\Application\KnowledgeBase\KnowledgeBaseReadService;
use Bitrix24\CLI\Application\KnowledgeBase\Request\ListArticlesRequest;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\KnowledgeBaseInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'kb:article:list', description: 'Показать дерево статей базы знаний')]
final class ListArticlesCommand extends Command
{
    public function __construct(private readonly KnowledgeBaseReadService $service, private readonly KnowledgeBaseInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('collection', null, InputOption::VALUE_REQUIRED, 'ID базы знаний (collectionId) из kb:list.');
        $this->setHelp(<<<'HELP'
            Возвращает дерево статей одной базы знаний: note.document.tree.list (REST 3.0, область доступа note). Архивные статьи в дерево не входят. ID статьи пригоден для kb:article:show и kb:article:update.

            Если дерево больше внутреннего лимита портала (5000 узлов), API обрезает его по корневым узлам: meta.complete = false, reason tree-truncated, exit 3; ищите статью через kb:article:search. Нет базы или нет доступа — collection-not-found.

            JSON data.items: корневые статьи с полями REST 3.0 (id, collectionId, parentId, title, position) и вложенными children. Human и TSV при --plain выводят плоский список в порядке обхода: id, parentId, depth, title. Пример: b24cli kb:article:list --collection 42 --json
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): ListArticlesRequest => $this->mapper->tree($input),
            fn (): array => $this->service->treeRoutes(),
            fn (ListArticlesRequest $request): PreparedOperation => $this->service->prepareTree($request),
        );
    }
}
