<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\PreparedOperation;
use Bitrix24\CLI\Application\Task\Request\ListTaskLegacyCommentsRequest;
use Bitrix24\CLI\Application\Task\TaskLegacyCommentService;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:legacy:comment:list', description: '[legacy] Прежняя лента комментариев задачи (до перехода на чат)')]
final class ListTaskLegacyCommentsCommand extends Command
{
    public function __construct(private readonly TaskLegacyCommentService $service, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('TASK_ID', InputArgument::REQUIRED, 'ID задачи.');
        $this->addOption('before', null, InputOption::VALUE_REQUIRED, 'Комментарии с ID меньше COMMENT_ID.');
        $this->addOption('after', null, InputOption::VALUE_REQUIRED, 'Комментарии с ID больше COMMENT_ID.');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Лимит вывода (по умолчанию 20 последних); не заменяет бюджет обхода.');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Снять только лимит вывода; бюджет остаётся конечным.');
        $this->addOption('max-comments', null, InputOption::VALUE_REQUIRED, 'Бюджет просмотра комментариев (по умолчанию 10000).');
        $this->setHelp(implode(' ', [
            'Устаревший источник: лента комментариев старой карточки задачи. Метод REST 1.0 task.commentitem.getlist не развивается с tasks 25.700.0 и может быть отключён на портале. Текущее обсуждение задачи — task:chat:list.',
            'ID комментариев не являются ID сообщений чата и не подходят для task:chat:update/delete. Команд записи в группе task:legacy нет.',
            'Без --before/--after выводятся последние комментарии; вывод всегда по возрастанию id. --all читает ленту до --max-comments (10000).',
            'Неполное чтение: complete=false, reason comment-budget-exhausted, pagination-no-progress или page-error, exit3.',
            'Если метода нет на портале или задача недоступна, команда возвращает ошибку legacy-comments-unavailable, а не пустой список: прежняя лента в этом случае не проверена.',
            'JSON: data.items[] с id, authorId, authorName, createdDate, text (как в ответе API, без преобразования BBCode) и attachments[] (attachmentId, fileId, name, size); meta.source=legacy-comments, meta.legacy=true. Ссылки на скачивание и просмотр вложений не выводятся: в ответе API они содержат токен доступа.',
            'Столбцы TSV при --plain: id, createdDate, authorId, authorName, text, attachmentCount; пометка legacy — в stderr.',
            'Маршрут REST 1.0, область доступа task; strict-rest3 отклоняет вызов до подключения. Чтение не меняет задачу и прочитанность чата.',
            'Пример: b24cli task:legacy:comment:list 123 --all --json',
        ]));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): ListTaskLegacyCommentsRequest => $this->mapper->listTaskLegacyComments($input),
            fn (): array => $this->service->listRoutes(),
            fn (ListTaskLegacyCommentsRequest $request): PreparedOperation => $this->service->list($request),
        );
    }
}
