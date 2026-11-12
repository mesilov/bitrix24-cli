<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\ListTasksHandler;
use Bitrix24\CLI\Application\Task\Request\ListTasksRequest;
use Bitrix24\CLI\Application\Task\TaskRole;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:list', description: 'Показать доступные задачи')]
final class ListTaskCommand extends Command
{
    public function __construct(private readonly ListTasksHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('deadline-window', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Календарное окно дедлайна (задайте один раз). REST 1.0.', suggestedValues: \Bitrix24\CLI\Application\Task\DeadlineWindowResolver::WINDOWS);
        $this->addOption('created-window', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Календарное окно даты создания (задайте один раз); значения как у --deadline-window. REST 1.0.', suggestedValues: \Bitrix24\CLI\Application\Task\DeadlineWindowResolver::WINDOWS);
        $this->addOption('changed-window', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Календарное окно даты последнего изменения (задайте один раз); значения как у --deadline-window. REST 1.0.', suggestedValues: \Bitrix24\CLI\Application\Task\DeadlineWindowResolver::WINDOWS);
        $this->addOption('overdue', null, InputOption::VALUE_NONE, 'Дедлайн прошёл; исключить completed/declined, включая deferred/supposedly_completed. REST 1.0.');
        $this->addOption('timezone', null, InputOption::VALUE_REQUIRED, 'Часовой пояс IANA для временных фильтров; по умолчанию TZ или часовой пояс окружения PHP.');
        $this->addOption('role', null, InputOption::VALUE_REQUIRED, 'Своя роль: responsible — делаю, accomplice — помогаю, creator — поручил, auditor — наблюдаю.', suggestedValues: array_column(TaskRole::cases(), 'value'));
        $this->addOption('id', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'ID для серверной выборки; флаг можно повторять.');
        $this->addOption('responsible', null, InputOption::VALUE_REQUIRED, 'ID ответственного; при task-v3 — серверный фильтр REST 1.0.');
        $this->addOption('group-id', null, InputOption::VALUE_REQUIRED, 'Положительный ID группы/проекта (groupId); при task-v3 — серверный фильтр REST 1.0.');
        $this->addOption('status', null, InputOption::VALUE_REQUIRED, 'Логический status: pending, in_progress, supposedly_completed, completed, deferred, declined; при task-v3 — серверный фильтр REAL_STATUS, кроме declined.');
        $this->addOption('due-before', null, InputOption::VALUE_REQUIRED, 'Локальный фильтр срока, ISO8601 с часовым поясом.');
        $this->addOption('where', null, InputOption::VALUE_REQUIRED, 'Фильтр равенств JSON-объект; groupId/responsibleId/creatorId при task-v3 идут на сервер.');
        $this->addOption('where-file', null, InputOption::VALUE_REQUIRED, 'Локальный JSON-фильтр из PATH или -.');
        $this->addOption('select', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Выбранное поле; флаг можно повторять.');
        $this->addOption('order', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'FIELD:asc или FIELD:desc; флаг можно повторять; по умолчанию id:desc (новые сначала).');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Лимит вывода; не заменяет бюджет обхода.');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Снять только лимит вывода; бюджет остаётся конечным.');
        $this->addOption('max-scan', null, InputOption::VALUE_REQUIRED, 'Максимум просмотренных задач (по умолчанию 10000).');
        $this->setHelp(' Новые --deadline-window today|tomorrow|yesterday|day-before-yesterday|this-week|this-month|this-quarter и --overdue используют серверный диапазон DEADLINE через REST1; strict-rest3 запрещает этот маршрут до запросов. Окна [from,to), неделя с понедельника, полный месяц/квартал. --timezone IANA: по умолчанию TZ или часовой пояс PHP; meta.deadlineFilter показывает now, границы и route. --overdue исключает completed/declined, включает deferred/supposedly_completed; это дедлайн, не дата фактического закрытия. --created-window и --changed-window с теми же значениями задают окно даты создания (CREATED_DATE) и последнего изменения (CHANGED_DATE) через REST1; meta.createdFilter/meta.changedFilter показывают границы; окна сочетаются между собой и с --deadline-window через логическое И, один --timezone для всех. Не смешивайте временные флаги с --where/--due-before. Пример: b24cli task:list --group-id 42 --deadline-window this-week --overdue --timezone Asia/Bishkek --json. JSON: структура data/meta/error; столбцы TSV при --plain: id, title, responsibleId, groupId, status, deadline. Без временных флагов --group-id, --responsible, --role responsible|creator, --status (кроме declined) и where groupId/responsibleId/creatorId/status при task-v3 фильтруются на сервере через REST 1.0 tasks.task.list вместе с --id; временные флаги передают те же условия рядом с границами дат. due-before, declined и роли участников проверяются локально. REST 3.0 с локальной фильтрацией остаётся при strict-rest3, без серверных условий и если --select/--order/--where содержит поле, которого нет в REST 1.0; meta.listRoute показывает apiVersion, serverFilter и причину. --max-scan считает строки серверной выборки. Локальный обход REST 3.0 без --id пишет в stderr причину и прогресс каждые 1000 задач; --quiet/--silent подавляют эти строки. Обход и вывод по умолчанию id DESC (новые сначала); --order меняет оба порядка. meta.order показывает сортировку; meta.complete=false/exit3 означает неполный обход. Увеличьте --max-scan для просмотра большего числа задач; --all снимает только лимит вывода. Пример: b24cli task:list --responsible 2 --group-id 42 --all. --role относится к владельцу выбранного вебхука и сочетается с другими критериями через логическое И; статус не ограничивается. при strict-rest3 responsible/creator проверяются локально по REST3; accomplice/auditor требуют REST1 чтения участников и недоступны при strict-rest3. Пример: b24cli task:list --role responsible; b24cli task:list --role auditor --group-id 42 --all');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (\Bitrix24\CLI\Application\InvocationContext $context): ListTasksRequest => $this->mapper->listTasks($input, $context->policy),
            fn (ListTasksRequest $request): array => $this->handler->routes($request),
            fn (ListTasksRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
