<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Console\Command\Task;

use Bitrix24\CLI\Application\Task\Handler\FindTasksHandler;
use Bitrix24\CLI\Application\Task\Request\FindTasksRequest;
use Bitrix24\CLI\Application\Task\TaskRole;
use Bitrix24\CLI\Console\CommandRunner;
use Bitrix24\CLI\Console\TaskInputMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'task:find', description: 'Найти задачи по буквальной подстроке title')]
final class FindTaskCommand extends Command
{
    public function __construct(private readonly FindTasksHandler $handler, private readonly TaskInputMapper $mapper, private readonly CommandRunner $runner)
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
        $this->addOption('status', null, InputOption::VALUE_REQUIRED, 'Статус жизненного цикла (например pending, completed); при task-v3 — серверный фильтр REAL_STATUS, кроме declined.');
        $this->addOption('role', null, InputOption::VALUE_REQUIRED, 'Своя роль: responsible — делаю, accomplice — помогаю, creator — поручил, auditor — наблюдаю.', suggestedValues: array_column(TaskRole::cases(), 'value'));
        $this->addOption('title', null, InputOption::VALUE_REQUIRED, 'Обязательный буквальный фрагмент заголовка; Unicode без регистра.');
        $this->addOption('group-id', null, InputOption::VALUE_REQUIRED, 'Положительный ID группы/проекта; при task-v3 — серверный GROUP_ID через REST 1.0, при strict-rest3 — локальный groupId в REST3.');
        $this->addOption('id', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'ID для серверной выборки; флаг можно повторять.');
        $this->addOption('responsible', null, InputOption::VALUE_REQUIRED, 'ID ответственного; при task-v3 — серверный RESPONSIBLE_ID через REST 1.0.');
        $this->addOption('due-before', null, InputOption::VALUE_REQUIRED, 'Локальный фильтр срока, ISO8601 с часовым поясом.');
        $this->addOption('where', null, InputOption::VALUE_REQUIRED, 'Фильтр равенств JSON-объект; groupId/responsibleId/creatorId при task-v3 идут на сервер; отдельный режим.');
        $this->addOption('where-file', null, InputOption::VALUE_REQUIRED, 'Локальный JSON-фильтр из PATH или явного stdin -.');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Лимит вывода (по умолчанию 50); не заменяет бюджет обхода.');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Снять только лимит вывода; бюджет остаётся конечным.');
        $this->addOption('order', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Сортировка FIELD:asc|desc; по умолчанию id:desc (новые сначала).');
        $this->addOption('max-scan', null, InputOption::VALUE_REQUIRED, 'Максимум просмотренных задач (по умолчанию 10000).');
        $this->setHelp('Новые --deadline-window today|tomorrow|yesterday|day-before-yesterday|this-week|this-month|this-quarter и --overdue используют серверный диапазон DEADLINE через REST1; strict-rest3 запрещает этот маршрут до запросов. Окна [from,to), неделя с понедельника, полный месяц/квартал. --timezone IANA: по умолчанию TZ или часовой пояс PHP; meta.deadlineFilter показывает now, границы и route. --overdue исключает completed/declined, включает deferred/supposedly_completed; это дедлайн, не дата фактического закрытия. --created-window и --changed-window с теми же значениями задают окно даты создания (CREATED_DATE) и последнего изменения (CHANGED_DATE) через REST1; meta.createdFilter/meta.changedFilter показывают границы; окна сочетаются между собой и с --deadline-window через логическое И, один --timezone для всех. Не смешивайте временные флаги с --where/--due-before. Пример: b24cli task:list --group-id 42 --deadline-window this-week --overdue --timezone Asia/Bishkek --json. JSON: структура data/meta/error; столбцы TSV при --plain: id, title. Обязательный title ищется буквально без регистра Unicode; title, --id, обычные критерии/where и --role применяются через логическое И. Без временных флагов --group-id, --responsible, --role responsible|creator, --status (кроме declined) и where groupId/responsibleId/creatorId/status при task-v3 фильтруются на сервере через REST 1.0 tasks.task.list вместе с --id; временные флаги передают те же условия рядом с границами дат. title, declined и deadline проверяются локально. REST 3.0 с локальной фильтрацией остаётся при strict-rest3, без серверных условий и для полей вне REST 1.0; meta.listRoute показывает маршрут. Локальный обход REST 3.0 без --id пишет в stderr причину и прогресс каждые 1000 задач; --quiet/--silent подавляют эти строки. --where/--where-file взаимоисключающие; where нельзя смешивать с --group-id/--responsible/--status/--due-before, но можно с --title/--id/--role. --where-file=- читает stdin явно и не может делить его с --config=-. Обход и вывод по умолчанию id DESC (новые сначала); --order меняет оба порядка. meta.order показывает сортировку, meta.complete=false/exit3 означает неполный поиск и не доказывает отсутствие дублей; увеличьте --max-scan. --all снимает только лимит вывода. Пример: b24cli task:find --title "договор" --group-id 42 --json. --role относится к владельцу выбранного вебхука и сочетается с другими критериями через логическое И; без явного --status роль не ограничивает статус. при strict-rest3 responsible/creator проверяются локально по REST3; accomplice/auditor требуют REST1 чтения участников и недоступны при strict-rest3. Пример: b24cli task:find --title "договор" --group-id 42 --role accomplice --json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (\Bitrix24\CLI\Application\InvocationContext $context): FindTasksRequest => $this->mapper->findTasks($input, $context->policy),
            fn (FindTasksRequest $request): array => $this->handler->routes($request),
            fn (FindTasksRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
