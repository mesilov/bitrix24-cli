# Spec Delta

## MODIFIED Requirements

### Requirement: Canonical calendar windows
task:list и task:find SHALL принимать --deadline-window, --created-window, --changed-window и --closed-window со значениями today, tomorrow, yesterday, day-before-yesterday, this-week, last-week, next-week, this-month, last-month, next-month, this-quarter, last-quarter, next-quarter, next-7-days или last-7-days. Окна относятся соответственно к deadline, дате создания, последнего изменения и фактического завершения. Неделя MUST начинаться в понедельник, кварталы — в январе, апреле, июле и октябре.

#### Scenario: Day windows
- **WHEN** дата команды в выбранном timezone равна 2026-10-13
- **THEN** today, tomorrow, yesterday и day-before-yesterday задают соответственно полные дни 13, 14, 12 и 11 октября, от полуночи включительно до следующей полуночи исключительно

#### Scenario: Calendar depth
- **WHEN** команда запускается 2026-10-13 с this-week, this-month или this-quarter
- **THEN** окна равны соответственно [2026-10-12, 2026-10-19), [2026-10-01, 2026-11-01), [2026-10-01, 2027-01-01) в выбранном timezone

#### Scenario: Previous and next calendar periods
- **WHEN** команда запускается 2026-11-13 с last-week, next-week, last-month, next-month, last-quarter или next-quarter
- **THEN** окна равны соответственно [2026-11-02, 2026-11-09), [2026-11-16, 2026-11-23), [2026-10-01, 2026-11-01), [2026-12-01, 2027-01-01), [2026-07-01, 2026-10-01), [2027-01-01, 2027-04-01) в выбранном timezone

#### Scenario: Period crosses a year boundary
- **WHEN** команда запускается 2027-01-05 с last-month, last-quarter или last-week
- **THEN** окна равны соответственно [2026-12-01, 2027-01-01), [2026-10-01, 2027-01-01) и [2026-12-28, 2027-01-04)

#### Scenario: Same mnemonics for creation and change dates
- **WHEN** команда запускается 2026-11-12 с --created-window this-quarter либо --changed-window this-week
- **THEN** окно дат создания равно [2026-10-01, 2027-01-01), окно дат изменения — [2026-11-09, 2026-11-16) в выбранном timezone; расчёт совпадает с --deadline-window того же значения

### Requirement: Server date filtering and API policy
Временные выборки SHALL передавать диапазоны в классический tasks.task.list REST1 через >= и < по DEADLINE (--deadline-window/--overdue), CREATED_DATE (--created-window), CHANGED_DATE (--changed-window) и CLOSED_DATE (--closed-window). Группа, исполнитель, постановщик и документированный статус SHALL уходить в GROUP_ID, RESPONSIBLE_ID, CREATED_BY и REAL_STATUS этого маршрута. REST3 date filter и скрытый fallback MUST NOT использоваться. strict-rest3 SHALL отклонять маршрут до запросов с policy-denied/exit4.

#### Scenario: Explicit classic route
- **WHEN** task:list --group-id 42 --deadline-window today выполняется при допускающей REST1 политике
- **THEN** API получает GROUP_ID=42 и обе границы DEADLINE; metadata показывает REST1 и фактически рассчитанный интервал

#### Scenario: Group tasks created this quarter
- **WHEN** task:list --group-id 42 --created-window this-quarter --timezone Asia/Bishkek выполняется при task-v3
- **THEN** API получает GROUP_ID=42, >=CREATED_DATE и <CREATED_DATE с явным offset и не получает DEADLINE; meta.createdFilter показывает window, timezone, now, fromInclusive, toExclusive и route REST1; дата создания каждой строки дополнительно проверяется локально

#### Scenario: Tasks closed this week
- **WHEN** task:list --group-id 42 --closed-window this-week --timezone Asia/Bishkek выполняется при task-v3
- **THEN** API получает GROUP_ID=42, >=CLOSED_DATE и <CLOSED_DATE с явным offset и не получает DEADLINE или REAL_STATUS; meta.closedFilter показывает window, timezone, now, fromInclusive, toExclusive и route REST1

#### Scenario: Combined windows and server predicates
- **WHEN** task:list --role responsible --status in_progress --changed-window this-week --deadline-window this-month выполняется при task-v3
- **THEN** один запрос REST1 содержит RESPONSIBLE_ID пользователя подключения, REAL_STATUS=3, границы CHANGED_DATE и границы DEADLINE; совпадают только строки, удовлетворяющие всем условиям; meta.listRoute показывает apiVersion 1.0 и serverFilter, meta.changedFilter и meta.deadlineFilter — свои интервалы

#### Scenario: Missing or invalid date in a row
- **WHEN** строка серверной выборки не содержит дату выбранного окна создания или изменения, содержит пустую или нераспознаваемую дату
- **THEN** задача не добавляется к совпадениям, результат помечается incomplete с reason missing-predicate-field и exit 3

#### Scenario: Strict REST3 policy
- **WHEN** --deadline-window, --overdue, --created-window, --changed-window или --closed-window задан при strict-rest3
- **THEN** CLI возвращает policy-denied/exit4 до сетевых запросов; локальная фильтрация не подменяет серверную

### Requirement: Filter combinations and invalid input
Временные критерии SHALL комбинироваться через AND между собой, с проектом, ролью, исполнителем, названием и допустимым статусом. Один --timezone SHALL применяться ко всем окнам команды. Неизвестные/повторные windows любого из четырёх флагов, неверный timezone, timezone без временного режима и смешение временных флагов с --due-before либо --where/--where-file SHALL приводить к usage-error/exit2 до запросов.

#### Scenario: Search by title and deadline
- **WHEN** task:find --title "отчёт" --group-id 42 --deadline-window this-month --overdue выполняется
- **THEN** совпадают все критерии; title остаётся буквальной Unicode подстрокой, формат результата — список id/title

#### Scenario: Explicit invalid combination
- **WHEN** --deadline-window today задан вместе с --due-before, advanced --where, неизвестным timezone либо повторным window
- **THEN** CLI возвращает usage-error/exit2 без API и называет неверный аргумент

#### Scenario: Invalid creation or change window
- **WHEN** --created-window, --changed-window или --closed-window получает неизвестное значение, повторяется, сочетается с --where/--where-file/--due-before, либо --timezone задан без временного флага
- **THEN** CLI возвращает usage-error/exit2 без API и называет неверный аргумент и допустимые значения

#### Scenario: Completed in a period and still closed
- **WHEN** task:list --closed-window last-week --status completed выполняется
- **THEN** совпадают только задачи с CLOSED_DATE в прошлой неделе и текущим статусом completed; оба условия передаются одним запросом REST1

### Requirement: Public skill translation
Skill SHALL переводить user stories в canonical public CLI arguments без самостоятельного расчёта границ или прямых API/SDK/MCP вызовов. «Должны были закрыть» SHALL означать deadline-window вместе с overdue. «Созданные», «изменённые» и «фактически завершённые» за период SHALL означать --created-window, --changed-window и --closed-window, а не окно дедлайна или текущий статус.

#### Scenario: Natural language mappings
- **WHEN** пользователь просит дедлайн завтра, просроченные задачи либо задачи, которые должны были закрыть позавчера
- **THEN** skill выбирает соответственно --deadline-window tomorrow, --overdue либо --overdue --deadline-window day-before-yesterday, добавляя известные проект/timezone

#### Scenario: Created or changed in a period
- **WHEN** пользователь просит задачи группы, созданные в текущем квартале, либо задачи, изменённые на этой неделе
- **THEN** skill выбирает соответственно --group-id с --created-window this-quarter либо --changed-window this-week и не заменяет их окном дедлайна

#### Scenario: Extended periods and actual completion
- **WHEN** пользователь просит задачи со сроком на следующей неделе, созданные в прошлом месяце, со сроком в ближайшие 7 дней либо фактически завершённые на этой неделе
- **THEN** skill выбирает соответственно --deadline-window next-week, --created-window last-month, --deadline-window next-7-days либо --closed-window this-week и не подменяет завершение дедлайном или --status

#### Scenario: Capability missing in installed CLI
- **WHEN** установленный CLI ещё не предоставляет новые аргументы
- **THEN** skill сообщает отсутствующий public CLI contract без обхода через другой интерфейс

## ADDED Requirements

### Requirement: Rolling day windows
next-7-days и last-7-days SHALL быть скользящими окнами из семи полных календарных дней выбранного timezone, включающими сегодняшний день: next-7-days — [00:00 сегодня, 00:00 через 7 дней), last-7-days — [00:00 шесть дней назад, 00:00 завтра). Длительность от now MUST NOT использоваться; границы MUST учитывать DST.

#### Scenario: Next seven days
- **WHEN** команда запускается 2026-11-13T15:00:00+06:00 с --deadline-window next-7-days --timezone Asia/Bishkek
- **THEN** окно равно [2026-11-13T00:00:00+06:00, 2026-11-20T00:00:00+06:00); задача со сроком сегодня в 09:00 входит в окно

#### Scenario: Last seven days
- **WHEN** команда запускается 2026-11-13 с --created-window last-7-days
- **THEN** окно равно [2026-11-07, 2026-11-14) в выбранном timezone

#### Scenario: Rolling window across DST
- **WHEN** next-7-days в Europe/Berlin запускается 2026-10-22, а окно содержит переход 2026-10-25
- **THEN** окно равно [2026-10-22T00:00:00+02:00, 2026-10-29T00:00:00+01:00) и содержит ровно семь календарных дней, а не 168 часов

#### Scenario: Rolling window with overdue
- **WHEN** задано --overdue --deadline-window next-7-days
- **THEN** верхняя граница обрезается до начального now: окно равно [00:00 сегодня, now)

### Requirement: Actual completion window
--closed-window SHALL выбирать задачи, у которых фактическая дата завершения лежит в окне, независимо от текущего статуса. Пустая или отсутствующая дата завершения SHALL означать несовпадение, а не неполноту. Окно MUST NOT подменяться deadline, REAL_STATUS или датой изменения.

#### Scenario: Never completed task
- **WHEN** строка серверной выборки содержит CLOSED_DATE null или пустую строку
- **THEN** задача не входит в результат, а результат не помечается incomplete по этой причине

#### Scenario: Reopened task
- **WHEN** задача была завершена в окне и затем возобновлена, а портал сохраняет её CLOSED_DATE
- **THEN** задача входит в результат --closed-window с текущим статусом в строке; добавление --status completed исключает её

#### Scenario: Unreadable completion date
- **WHEN** CLOSED_DATE строки непустой, но не распознаётся как дата
- **THEN** задача не добавляется к совпадениям, результат помечается incomplete с reason missing-predicate-field и exit 3
