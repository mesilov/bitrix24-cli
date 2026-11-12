## MODIFIED Requirements

### Requirement: Canonical calendar windows
task:list и task:find SHALL принимать --deadline-window, --created-window и --changed-window со значениями today, tomorrow, yesterday, day-before-yesterday, this-week, this-month или this-quarter. Окна относятся соответственно к deadline, дате создания и дате последнего изменения задачи. Неделя MUST начинаться в понедельник, кварталы — в январе, апреле, июле и октябре. Скользящие длительности MUST NOT заменять календарные периоды.

#### Scenario: Day windows
- **WHEN** дата команды в выбранном timezone равна 2026-10-13
- **THEN** today, tomorrow, yesterday и day-before-yesterday задают соответственно полные дни 13, 14, 12 и 11 октября, от полуночи включительно до следующей полуночи исключительно

#### Scenario: Calendar depth
- **WHEN** команда запускается 2026-10-13 с this-week, this-month или this-quarter
- **THEN** окна равны соответственно [2026-10-12, 2026-10-19), [2026-10-01, 2026-11-01), [2026-10-01, 2027-01-01) в выбранном timezone

#### Scenario: Same mnemonics for creation and change dates
- **WHEN** команда запускается 2026-11-12 с --created-window this-quarter либо --changed-window this-week
- **THEN** окно дат создания равно [2026-10-01, 2027-01-01), окно дат изменения — [2026-11-09, 2026-11-16) в выбранном timezone; расчёт совпадает с --deadline-window того же значения

### Requirement: Server date filtering and API policy
Временные выборки SHALL передавать диапазоны в классический tasks.task.list REST1: --deadline-window/--overdue через >=DEADLINE и <DEADLINE, --created-window через >=CREATED_DATE и <CREATED_DATE, --changed-window через >=CHANGED_DATE и <CHANGED_DATE. Группа, исполнитель, постановщик и документированный статус SHALL уходить в GROUP_ID, RESPONSIBLE_ID, CREATED_BY и REAL_STATUS этого маршрута. REST3 date filter и скрытый fallback MUST NOT использоваться. strict-rest3 SHALL отклонять маршрут до запросов с policy-denied/exit4.

#### Scenario: Explicit classic route
- **WHEN** task:list --group-id 42 --deadline-window today выполняется при допускающей REST1 политике
- **THEN** API получает GROUP_ID=42 и обе границы DEADLINE; metadata показывает REST1 и фактически рассчитанный интервал

#### Scenario: Group tasks created this quarter
- **WHEN** task:list --group-id 42 --created-window this-quarter --timezone Asia/Bishkek выполняется при task-v3
- **THEN** API получает GROUP_ID=42, >=CREATED_DATE и <CREATED_DATE с явным offset и не получает DEADLINE; meta.createdFilter показывает window, timezone, now, fromInclusive, toExclusive и route REST1; дата создания каждой строки дополнительно проверяется локально

#### Scenario: Combined windows and server predicates
- **WHEN** task:list --role responsible --status in_progress --changed-window this-week --deadline-window this-month выполняется при task-v3
- **THEN** один запрос REST1 содержит RESPONSIBLE_ID пользователя подключения, REAL_STATUS=3, границы CHANGED_DATE и границы DEADLINE; совпадают только строки, удовлетворяющие всем условиям; meta.listRoute показывает apiVersion 1.0 и serverFilter, meta.changedFilter и meta.deadlineFilter — свои интервалы

#### Scenario: Missing or invalid date in a row
- **WHEN** строка серверной выборки не содержит дату выбранного окна, содержит пустую или нераспознаваемую дату
- **THEN** задача не добавляется к совпадениям, результат помечается incomplete с reason missing-predicate-field и exit 3

#### Scenario: Strict REST3 policy
- **WHEN** --deadline-window, --overdue, --created-window или --changed-window задан при strict-rest3
- **THEN** CLI возвращает policy-denied/exit4 до сетевых запросов; локальная фильтрация не подменяет серверную

### Requirement: Filter combinations and invalid input
Временные критерии SHALL комбинироваться через AND между собой, с проектом, ролью, исполнителем, названием и допустимым статусом. Один --timezone SHALL применяться ко всем окнам команды. Неизвестные/повторные windows любого из трёх флагов, неверный timezone, timezone без временного режима и смешение временных флагов с --due-before либо --where/--where-file SHALL приводить к usage-error/exit2 до запросов.

#### Scenario: Search by title and deadline
- **WHEN** task:find --title "отчёт" --group-id 42 --deadline-window this-month --overdue выполняется
- **THEN** совпадают все критерии; title остаётся буквальной Unicode подстрокой, формат результата — список id/title

#### Scenario: Explicit invalid combination
- **WHEN** --deadline-window today задан вместе с --due-before, advanced --where, неизвестным timezone либо повторным window
- **THEN** CLI возвращает usage-error/exit2 без API и называет неверный аргумент

#### Scenario: Invalid creation or change window
- **WHEN** --created-window или --changed-window получает неизвестное значение, повторяется, сочетается с --where/--where-file/--due-before, либо --timezone задан без временного флага
- **THEN** CLI возвращает usage-error/exit2 без API и называет неверный аргумент и допустимые значения

### Requirement: Public skill translation
Skill SHALL переводить user stories в canonical public CLI arguments без самостоятельного расчёта границ или прямых API/SDK/MCP вызовов. «Должны были закрыть» SHALL означать deadline-window вместе с overdue; фактическая дата закрытия SHALL отличаться и оставаться вне этого интерфейса. «Созданные» и «изменённые» за период SHALL означать --created-window и --changed-window, а не окно дедлайна.

#### Scenario: Natural language mappings
- **WHEN** пользователь просит дедлайн завтра, просроченные задачи либо задачи, которые должны были закрыть позавчера
- **THEN** skill выбирает соответственно --deadline-window tomorrow, --overdue либо --overdue --deadline-window day-before-yesterday, добавляя известные проект/timezone

#### Scenario: Created or changed in a period
- **WHEN** пользователь просит задачи группы, созданные в текущем квартале, либо задачи, изменённые на этой неделе
- **THEN** skill выбирает соответственно --group-id с --created-window this-quarter либо --changed-window this-week и не заменяет их окном дедлайна

#### Scenario: Capability missing in installed CLI
- **WHEN** установленный CLI ещё не предоставляет новые аргументы
- **THEN** skill сообщает отсутствующий public CLI contract без обхода через другой интерфейс
