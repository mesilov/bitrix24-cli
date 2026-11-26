<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\KnowledgeBase;

use Bitrix24\CLI\Application\KnowledgeBase\ArticleWriteService;
use Bitrix24\CLI\Application\KnowledgeBase\Request\UpdateArticleRequest;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\KnowledgeBaseInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'kb:article:update', description: 'Заменить заголовок и/или содержимое статьи базы знаний')]
final class UpdateArticleCommand extends Command
{
    public function __construct(private readonly ArticleWriteService $service, private readonly KnowledgeBaseInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('ID', InputArgument::REQUIRED, 'ID статьи.');
        $this->addOption('title', null, InputOption::VALUE_REQUIRED, 'Новый заголовок (title), от 1 до 255 символов.');
        $this->addOption('markdown-file', null, InputOption::VALUE_REQUIRED, 'Новое содержимое (markdown) из PATH или - (stdin), до 1048576 байт; заменяет текст статьи целиком.');
        $this->addOption('overwrite', null, InputOption::VALUE_NONE, 'Перезаписать содержимое, даже если в совместном редакторе есть несохранённые изменения (overwrite=true).');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи.');
        $this->setHelp(<<<'HELP'
            Обновляет статью: note.document.update (REST 3.0, область доступа note). Нужен хотя бы один из --title и --markdown-file. Без --title заголовок не меняется.

            Содержимое заменяется целиком, частичной правки нет. Сценарий правки: b24cli kb:article:show 77 --markdown > article.md, изменить файл, b24cli kb:article:update 77 --markdown-file article.md. Чтения перед записью нет, а API не защищает от одновременной правки: побеждает последняя запись; поле updatedAt в kb:article:show помогает заметить чужое изменение.

            Если в совместном редакторе есть несохранённые изменения, портал может отклонить запись; CLI подсказывает --overwrite и сам overwrite=true не передаёт.

            --dry-run показывает план (размер и SHA-256 содержимого вместо текста). Запись выполняется один раз; если ответ потерян, meta.outcomeUnknown = true и повтора нет: проверьте kb:article:show. Ответ сверяется с запросом: другой title — exit 3; отличие markdown — признак markdownNormalized.

            JSON data: operation (action, id, collectionId, parentId, title, confirmed, readback, mismatch, markdownNormalized), article (статья из ответа API), changedFields, overwrite. TSV при --plain: id, collectionId, parentId, title, confirmed, readback, markdownNormalized. Пример: b24cli kb:article:update 77 --markdown-file article.md --json
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): UpdateArticleRequest => $this->mapper->update($input),
            fn (): array => $this->service->updateRoutes(),
            fn (UpdateArticleRequest $request): PreparedOperation => $this->service->prepareUpdate($request),
        );
    }
}
