<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\KnowledgeBase;

use Bitrix24\CLI\Application\KnowledgeBase\ArticleWriteService;
use Bitrix24\CLI\Application\KnowledgeBase\Request\AddArticleRequest;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\KnowledgeBaseInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'kb:article:add', description: 'Создать статью в базе знаний')]
final class AddArticleCommand extends Command
{
    public function __construct(private readonly ArticleWriteService $service, private readonly KnowledgeBaseInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('collection', null, InputOption::VALUE_REQUIRED, 'ID базы знаний (collectionId) из kb:list.');
        $this->addOption('title', null, InputOption::VALUE_REQUIRED, 'Заголовок статьи (title), от 1 до 255 символов.');
        $this->addOption('parent', null, InputOption::VALUE_REQUIRED, 'ID родительской статьи (parentId) для дочерней страницы; из той же базы знаний.');
        $this->addOption('markdown-file', null, InputOption::VALUE_REQUIRED, 'Содержимое (markdown) из PATH или - (stdin), до 1048576 байт; передаётся без изменений.');
        $this->addOption('allow-duplicate-title', null, InputOption::VALUE_NONE, 'Создать статью, даже если у того же родителя есть статья с таким заголовком; дерево не проверяется.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Проверить дубль и показать план без создания.');
        $this->setHelp(<<<'HELP'
            Создаёт статью: note.document.add (REST 3.0, область доступа note), поля collectionId, title, parentId, markdown.

            До записи читает дерево базы знаний (note.document.tree.list): статья с точно таким же заголовком у того же родителя — article-exists (exit 4, ID в error.details.candidates); обрезанное дерево — search-incomplete (exit 4); ничего не создаётся. --allow-duplicate-title пропускает проверку. Архивные статьи проверка не видит.

            --dry-run выполняет только проверку дубля и показывает план (размер и SHA-256 содержимого вместо текста). Запись выполняется один раз; если ответ потерян, meta.outcomeUnknown = true и повтора нет: проверьте kb:article:list перед повтором. Ответ API сверяется с запросом: другой title, collectionId или parentId — exit 3 (readback mismatch). Отличие markdown — признак markdownNormalized, запись подтверждена.

            JSON data: operation (action, id, collectionId, parentId, title, confirmed, readback, mismatch, markdownNormalized), article (статья из ответа API), duplicateCheck. TSV при --plain: id, collectionId, parentId, title, confirmed, readback, markdownNormalized. Пример: b24cli kb:article:add --collection 42 --title 'Регламент отпусков' --markdown-file vacation.md --json
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): AddArticleRequest => $this->mapper->add($input),
            fn (AddArticleRequest $request): array => $this->service->addRoutes($request),
            fn (AddArticleRequest $request): PreparedOperation => $this->service->prepareAdd($request),
        );
    }
}
