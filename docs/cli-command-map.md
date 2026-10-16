# Карта CLI-вызовов

Каталог содержит 63 публичных команд. Проверяйте установленную сборку через `b24cli --version` и `b24cli list`. Полная справка: `b24cli COMMAND --help` или `b24cli help COMMAND`.

`b24cli --help`/`-h` показывают назначение, список команд, глобальные опции и примеры задач. Вся справка — на русском; имена команд, флагов и API/JSON-полей сохраняются. Единый первый блок «Общие» содержит `completion`, `help` и `list`; далее пространства имён и команды внутри блоков упорядочены по имени. `b24cli --about` показывает назначение, версию, автора, лицензию и ссылки на [приложение](https://github.com/mesilov/bitrix24-cli) и [скилл](https://github.com/mesilov/bitrix24-cli-skill). Эти вызовы не требуют подключения и не обращаются к порталу. `list --help` описывает именно list; вызов без аргументов выводит список команд.

## Команды

| Команда | Назначение | Эффект | Пример |
| --- | --- | --- | --- |
| `completion` | Вывести скрипт автодополнения для оболочки | offline | `b24cli completion bash` |
| [`connection:add`](../src/Console/Command/Connection/AddConnectionCommand.php) | Сохранить локальное подключение | local | `b24cli connection:add work --group-id 7 --creator-id 1 --responsible-id 42` |
| [`connection:check`](../src/Console/Command/Connection/CheckConnectionCommand.php) | Проверить разрешения выбранного вебхука и требования CLI | read | `b24cli connection:check work --json` |
| [`connection:list`](../src/Console/Command/Connection/ListConnectionsCommand.php) | Показать сохранённые подключения | local | `b24cli connection:list` |
| [`connection:remove`](../src/Console/Command/Connection/RemoveConnectionCommand.php) | Удалить локальный профиль и его учётные данные | local | `b24cli connection:remove work` |
| [`connection:show`](../src/Console/Command/Connection/ShowConnectionCommand.php) | Показать безопасные параметры выбранного подключения | local | `b24cli connection:show work` |
| [`connection:use`](../src/Console/Command/Connection/UseConnectionCommand.php) | Выбрать сохранённый профиль по умолчанию | local | `b24cli connection:use work` |
| [`doctor:problem-trajectory`](../src/Console/Command/Doctor/ProblemTrajectoryCommand.php) | Сохранить и разобрать обезличенные карточки нехватки CLI | local | `b24cli doctor:problem-trajectory --action status --json` |
| `help` | Показать справку команды | offline | `b24cli help task:add` |
| `list` | Показать список команд | offline | `b24cli list task` |
| [`task:access:show`](../src/Console/Command/Task/ShowTaskAccessCommand.php) | Показать права текущего пользователя | read | `b24cli task:access:show 123` |
| [`task:add`](../src/Console/Command/Task/AddTaskCommand.php) | Создать задачу | write | `b24cli task:add --title "Подготовить договор" --creator 1 --responsible 42 --dry-run` |
| [`task:approve`](../src/Console/Command/Task/ApproveTaskCommand.php) | Принять результат задачи | write | `b24cli task:approve 123 --dry-run` |
| [`task:assign`](../src/Console/Command/Task/AssignTaskCommand.php) | Сменить ответственного | write | `b24cli task:assign 123 --responsible 3 --dry-run` |
| [`task:chat:delete`](../src/Console/Command/Task/DeleteTaskChatMessageCommand.php) | Удалить сообщение чата задачи | delete | `b24cli task:chat:delete 123 --message 900 --force --dry-run` |
| [`task:chat:list`](../src/Console/Command/Task/ListTaskChatCommand.php) | Показать сообщения чата задачи | read | `b24cli task:chat:list 123 --limit 20` |
| [`task:chat:send`](../src/Console/Command/Task/SendTaskChatMessageCommand.php) | Отправить сообщение в чат задачи | write | `b24cli task:chat:send 123 --text "Договор готов" --dry-run` |
| [`task:chat:update`](../src/Console/Command/Task/UpdateTaskChatMessageCommand.php) | Изменить сообщение чата задачи | write | `b24cli task:chat:update 123 --message 900 --text "Договор согласован" --dry-run` |
| [`task:checklist:add`](../src/Console/Command/Task/AddTaskChecklistCommand.php) | Создать чек-лист | write | `b24cli task:checklist:add 123 --title "Подготовка" --dry-run` |
| [`task:checklist:item:add`](../src/Console/Command/Task/AddTaskChecklistItemCommand.php) | Добавить пункт чек-листа | write | `b24cli task:checklist:item:add 123 --checklist 10 --title "Проверить реквизиты" --dry-run` |
| [`task:checklist:item:complete`](../src/Console/Command/Task/CompleteTaskChecklistItemCommand.php) | Выполнить пункт чек-листа | write | `b24cli task:checklist:item:complete 123 --item 11 --dry-run` |
| [`task:checklist:item:delete`](../src/Console/Command/Task/DeleteTaskChecklistItemCommand.php) | Удалить пункт с дочерними пунктами | delete | `b24cli task:checklist:item:delete 123 --item 11 --dry-run` |
| [`task:checklist:item:list`](../src/Console/Command/Task/ListTaskChecklistItemsCommand.php) | Показать пункты чек-листа | read | `b24cli task:checklist:item:list 123 --checklist 10` |
| [`task:checklist:item:renew`](../src/Console/Command/Task/RenewTaskChecklistItemCommand.php) | Возобновить пункт чек-листа | write | `b24cli task:checklist:item:renew 123 --item 11 --dry-run` |
| [`task:checklist:item:update`](../src/Console/Command/Task/UpdateTaskChecklistItemCommand.php) | Изменить заголовок пункта | write | `b24cli task:checklist:item:update 123 --item 11 --title "Уточнить реквизиты" --dry-run` |
| [`task:checklist:list`](../src/Console/Command/Task/ListTaskChecklistsCommand.php) | Показать чек-листы | read | `b24cli task:checklist:list 123` |
| [`task:complete`](../src/Console/Command/Task/CompleteTaskCommand.php) | Завершить задачу или передать на контроль | write | `b24cli task:complete 123 --dry-run` |
| [`task:deadline:set`](../src/Console/Command/Task/SetTaskDeadlineCommand.php) | Изменить срок задачи | write | `b24cli task:deadline:set 123 --at 2026-12-01T12:00:00+06:00 --dry-run` |
| [`task:defer`](../src/Console/Command/Task/DeferTaskCommand.php) | Отложить задачу | write | `b24cli task:defer 123 --dry-run` |
| [`task:delete`](../src/Console/Command/Task/DeleteTaskCommand.php) | Удалить задачу | delete | `b24cli task:delete 123 --dry-run` |
| [`task:disapprove`](../src/Console/Command/Task/DisapproveTaskCommand.php) | Вернуть задачу на доработку | write | `b24cli task:disapprove 123 --dry-run` |
| [`task:fields:list`](../src/Console/Command/Task/ListTaskFieldsCommand.php) | Показать поля задачи | read | `b24cli task:fields:list --name title` |
| [`task:file:attach`](../src/Console/Command/Task/AttachTaskFilesCommand.php) | Прикрепить файл Диска или загрузить локальный файл | write | `b24cli task:file:attach 123 --file-id 50 --dry-run` |
| [`task:find`](../src/Console/Command/Task/FindTaskCommand.php) | Найти задачи по буквальной подстроке title | read | `b24cli task:find --title "договор" --group-id 7 --responsible 42` |
| [`task:handover`](../src/Console/Command/Task/HandoverTasksCommand.php) | Передать незавершённые задачи проекта другому участнику | write | `b24cli task:handover --group-id 234 --responsible 14 --source-status deferred --exclude-id 92888 --target-status deferred --dry-run` |
| [`task:history:list`](../src/Console/Command/Task/ListTaskHistoryCommand.php) | Показать историю изменений задачи | read | `b24cli task:history:list 123 --json` |
| [`task:list`](../src/Console/Command/Task/ListTaskCommand.php) | Показать доступные задачи | read | `b24cli task:list --group-id 7 --responsible 42` |
| [`task:my`](../src/Console/Command/Task/MyTaskCommand.php) | Показать задачи выбранного исполнителя | read | `b24cli task:my --user 42 --open --json` |
| [`task:participants:set`](../src/Console/Command/Task/SetTaskParticipantsCommand.php) | Заменить выбранные роли участников | write | `b24cli task:participants:set 123 --auditor 4 --clear-accomplices --dry-run` |
| [`task:pause`](../src/Console/Command/Task/PauseTaskCommand.php) | Приостановить выполнение задачи | write | `b24cli task:pause 123 --dry-run` |
| [`task:renew`](../src/Console/Command/Task/RenewTaskCommand.php) | Возобновить задачу | write | `b24cli task:renew 123 --dry-run` |
| [`task:result:add`](../src/Console/Command/Task/AddTaskResultCommand.php) | Добавить результат задачи | write | `b24cli task:result:add 123 --text "Договор готов" --dry-run` |
| [`task:result:delete`](../src/Console/Command/Task/DeleteTaskResultCommand.php) | Удалить результат задачи | delete | `b24cli task:result:delete 123 --result 17 --force --dry-run` |
| [`task:result:list`](../src/Console/Command/Task/ListTaskResultsCommand.php) | Показать результаты задачи | read | `b24cli task:result:list 123 --all` |
| [`task:result:update`](../src/Console/Command/Task/UpdateTaskResultCommand.php) | Изменить текст результата задачи | write | `b24cli task:result:update 123 --result 17 --text "Договор проверен" --dry-run` |
| [`task:show`](../src/Console/Command/Task/ShowTaskCommand.php) | Показать карточку задачи | read | `b24cli task:show 123` |
| [`task:start`](../src/Console/Command/Task/StartTaskCommand.php) | Начать работу над задачей | write | `b24cli task:start 123 --dry-run` |
| [`task:status:durations`](../src/Console/Command/Task/StatusDurationsTaskCommand.php) | Рассчитать календарное время задачи в статусах | read | `b24cli task:status:durations 123 --as-of 2026-10-01T12:00:00+03:00 --json` |
| [`task:template:add`](../src/Console/Command/Task/AddTaskTemplateCommand.php) | Создать шаблон задачи | write | `b24cli task:template:add --title "Типовая задача" --creator 1 --responsible 42 --dry-run` |
| [`task:template:delete`](../src/Console/Command/Task/DeleteTaskTemplateCommand.php) | Запросить удаление шаблона задачи | delete | `b24cli task:template:delete 139 --dry-run` |
| [`task:template:fields:list`](../src/Console/Command/Task/ListTaskTemplateFieldsCommand.php) | Показать поля шаблона задачи | read | `b24cli task:template:fields:list` |
| [`task:template:show`](../src/Console/Command/Task/ShowTaskTemplateCommand.php) | Показать шаблон задачи по ID | read | `b24cli task:template:show 139` |
| [`task:template:update`](../src/Console/Command/Task/UpdateTaskTemplateCommand.php) | Изменить шаблон задачи | write | `b24cli task:template:update 139 --description "Проверить реквизиты" --dry-run` |
| [`task:time:add`](../src/Console/Command/Task/AddTaskTimeEntryCommand.php) | Добавить запись времени | write | `b24cli task:time:add 123 --seconds 3600 --text "Подготовка договора" --dry-run` |
| [`task:time:delete`](../src/Console/Command/Task/DeleteTaskTimeEntryCommand.php) | Удалить запись времени | delete | `b24cli task:time:delete 123 --entry 30 --force --dry-run` |
| [`task:time:list`](../src/Console/Command/Task/ListTaskTimeEntriesCommand.php) | Показать записи времени | read | `b24cli task:time:list 123` |
| [`task:time:show`](../src/Console/Command/Task/ShowTaskTimeCommand.php) | Показать контекст учёта времени | read | `b24cli task:time:show 123` |
| [`task:time:update`](../src/Console/Command/Task/UpdateTaskTimeEntryCommand.php) | Исправить запись времени | write | `b24cli task:time:update 123 --entry 30 --seconds 1800 --dry-run` |
| [`task:update`](../src/Console/Command/Task/UpdateTaskCommand.php) | Изменить явные поля задачи | write | `b24cli task:update 123 --title "Договор для согласования" --dry-run` |
| [`users:colleagues`](../src/Console/Command/Users/ListColleaguesCommand.php) | Коллеги текущего отдела по оргструктуре | read | `b24cli users:colleagues --department-id 7 --json` |
| [`users:current`](../src/Console/Command/Users/CurrentUserCommand.php) | Пользователь текущего подключения | read | `b24cli users:current --json` |
| [`users:find`](../src/Console/Command/Users/FindUsersCommand.php) | Найти активных сотрудников по ФИО или email | read | `b24cli users:find --name "Иванов Иван" --json` |
| [`users:manager`](../src/Console/Command/Users/GetManagerCommand.php) | Непосредственный или настроенный руководитель | read | `b24cli users:manager --department-id 7 --json` |

## Ограничения и справка

ID в примерах — образцы, замените их доступными вам сущностями. `write`/`delete` примеры используют `--dry-run`: записи не выполняются, но проверочные чтения могут требовать подключения и прав. Проверка без записи не доказывает приёмку сервером. `local` читает или изменяет локальную конфигурацию; `connection:add` требует скрытого ввода вебхука в терминале либо `--webhook-stdin` с перенаправленным stdin, `connection:remove` удаляет локальный профиль. `read` зависит от прав, бюджета обхода и полноты данных.

Аргументы, опции, значения по умолчанию, формат вывода и ограничения смотрите в справке соответствующей команды. Параметры выбора подключения — в [конфигурации](config.md); запуск и тесты — в [разработке](development.md).

## JTBD и актуализация

Связь рабочих сценариев с командами и тестами — в [JTBD задач по ролям](task-jtbd.md). Планируемые команды и границы покрытия отмечены там отдельно; в этот каталог входят только зарегистрированные публичные команды.

Карта формируется из текущего каталога, примеров help и существующих файлов тестов. Обновление: `make cli-reference`; проверка: `make cli-reference-check`. JSON для сверки скилла: `sh scripts/compose.sh run --rm -T php-cli php scripts/cli-reference.php --json`. Повторная проверка финальной 0.2.0 — [issue #109](https://github.com/mesilov/bitrix24-cli/issues/109).
