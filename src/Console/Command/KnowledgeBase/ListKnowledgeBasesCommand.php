<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\KnowledgeBase;

use Bitrix24\CLI\Application\KnowledgeBase\KnowledgeBaseReadService;
use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Console\CommandRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'kb:list', description: 'Показать доступные базы знаний (База знаний 2.0)')]
final class ListKnowledgeBasesCommand extends Command
{
    public function __construct(private readonly KnowledgeBaseReadService $service, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(<<<'HELP'
            Возвращает все базы знаний Базы знаний 2.0, доступные пользователю подключения: note.collection.list (REST 3.0, область доступа note). Страницы по 200 обходятся курсором nextCursor до конца выдачи. Старая база знаний на лендингах (landing.*) не поддерживается.

            Неполный обход (API повторил курсор или уже полученные ID) — meta.complete = false, reason pagination-no-progress, exit 3; полученные базы сохраняются. Тариф без REST API или вебхук без note — явный отказ, а не пустой список.

            JSON data.items: базы знаний с полями REST 3.0 как есть (id, name, position, policyLevel, accessLevel, isArchived, createdBy, createdAt, updatedBy, updatedAt и другие). TSV при --plain: id, name, policyLevel, accessLevel, isArchived. Пример: b24cli kb:list --json
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            static fn (): null => null,
            fn (): array => $this->service->collectionRoutes(),
            fn (): PreparedOperation => $this->service->prepareCollections(),
        );
    }
}
