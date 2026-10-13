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
        $this->addOption('overdue', null, InputOption::VALUE_NONE, 'Дедлайн прошёл; исключить completed/declined, включая deferred/supposedly_completed. REST 1.0.');
        $this->addOption('timezone', null, InputOption::VALUE_REQUIRED, 'IANA timezone для временных фильтров; default TZ или timezone PHP окружения.');
        $this->addOption('status', null, InputOption::VALUE_REQUIRED, 'Локальный фильтр lifecycle статуса (например pending, completed).');
        $this->addOption('role', null, InputOption::VALUE_REQUIRED, 'Своя роль: responsible — делаю, accomplice — помогаю, creator — поручил, auditor — наблюдаю.', suggestedValues: array_column(TaskRole::cases(), 'value'));
        $this->addOption('title', null, InputOption::VALUE_REQUIRED, 'Название.');
        $this->addOption('group-id', null, InputOption::VALUE_REQUIRED, 'Положительный ID группы/проекта; groupId локально в REST3, GROUP_ID на сервере во временном режиме REST1.');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Лимит вывода; не заменяет бюджет обхода.');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Снять только лимит вывода; бюджет остаётся конечным.');
        $this->addOption('order', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Сортировка FIELD:asc|desc; default id:desc (новые сначала).');
        $this->addOption('max-scan', null, InputOption::VALUE_REQUIRED, 'Максимум просмотренных задач (default 10000).');
        $this->setHelp(' Новые --deadline-window today|tomorrow|yesterday|day-before-yesterday|this-week|this-month|this-quarter и --overdue используют серверный DEADLINE range REST1; strict-rest3 запрещает этот маршрут до запросов. Окна [from,to), неделя с понедельника, полный месяц/квартал. --timezone IANA: default TZ или PHP timezone; meta.deadlineFilter показывает now/границы/route. --overdue исключает completed/declined, включает deferred/supposedly_completed; это дедлайн, не дата фактического закрытия. Не смешивайте temporal flags с --where/--due-before. Пример: b24cli task:list --group-id 42 --deadline-window this-week --overdue --timezone Asia/Bishkek --json. JSON: data/meta/error envelope; plain TSV columns: id, title. Без временных флагов REST 3.0. Поиск title без регистра Unicode; --group-id ограничивает поиск группой/проектом; без временных флагов фильтр локальный, с временными — серверный GROUP_ID. Обход и вывод по умолчанию id DESC (новые сначала); --order меняет оба порядка. meta.order показывает сортировку, meta.complete=false/exit3 означает неполный поиск и не доказывает отсутствие дублей; увеличьте --max-scan. --all снимает только лимит вывода. Пример: b24cli task:find --title "договор" --group-id 42 --json. --role относится к владельцу выбранного вебхука и сочетается с другими критериями через AND; статус не ограничивается. без временных флагов responsible/creator используют REST3; accomplice/auditor требуют REST1 чтения участников и недоступны при strict-rest3. Пример: b24cli task:find --title "договор" --group-id 42 --role accomplice --json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(
            $this,
            $input,
            $output,
            fn (): FindTasksRequest => $this->mapper->findTasks($input),
            fn (FindTasksRequest $request): array => $this->handler->routes($request),
            fn (FindTasksRequest $request): \Bitrix24\CLI\Application\PreparedOperation => $this->handler->prepare($request),
        );
    }
}
