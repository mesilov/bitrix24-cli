# task-weekly-report Delta

## Purpose

Дать руководителю проверяемый недельный отчёт по выбранному проекту: выполненная работа за период, очередь приёмки и текущая нагрузка с явными ограничениями доступа и полноты.

## ADDED Requirements

### Requirement: Project report with optional responsible filter
CLI SHALL предоставлять read-only `task:report` с обязательным положительным `--group-id ID`. Без `--responsible` отчёт MUST охватывать доступные задачи всего выбранного проекта; положительный `--responsible ID` SHALL сужать все четыре секции через AND. Отчёт MUST показывать область и ограничения видимости подключения.

#### Scenario: Whole project by default
- **WHEN** задан `task:report --group-id 234` без исполнителя
- **THEN** все четыре секции охватывают доступные задачи проекта 234 без неявного фильтра текущего пользователя, автора или участника

#### Scenario: One responsible within the project
- **WHEN** заданы `--group-id 234 --responsible 14`
- **THEN** каждая секция содержит или считает только задачи этого проекта с текущим ответственным 14; задачи сотрудника из других проектов не входят

#### Scenario: Missing or invalid scope
- **WHEN** проект не указан, ID неположительный, повторён одиночный аргумент или передан неизвестный параметр
- **THEN** CLI возвращает usage-error/exit 2 до credentials/API; профильные defaults ответственного не сужают отчёт неявно

### Requirement: Calendar week and deterministic time
CLI SHALL принимать `--week this-week|last-week|YYYY-MM-DD`, по умолчанию last-week. Дата SHALL выбирать содержащую её неделю. Неделя MUST быть интервалом [понедельник 00:00, следующий понедельник 00:00) в IANA timezone из `--timezone`, TZ или PHP. CLI MUST фиксировать reportAt один раз и выводить timezone и обе границы.

#### Scenario: Default previous week
- **WHEN** reportAt равен 2026-10-16T19:00:00+06:00 в Asia/Bishkek без --week
- **THEN** период равен [2026-10-05T00:00:00+06:00, 2026-10-12T00:00:00+06:00)

#### Scenario: Date belongs to the selected week
- **WHEN** задана дата 2026-10-14
- **THEN** период равен [2026-10-12, 2026-10-19) в выбранном timezone; дата не трактуется как скользящие семь суток

#### Scenario: DST and year boundary
- **WHEN** выбранная неделя пересекает DST либо начало года
- **THEN** календарные понедельники вычисляются в IANA timezone, offsets сохраняются; длительность не подменяется фиксированными 604800 секундами

#### Scenario: Current or future week
- **WHEN** выбрана текущая неделя
- **THEN** новые и закрытые учитываются в [from, min(to, reportAt)); отчёт показывает effectiveTo и признак незавершённого периода
- **AND** если начало выбранной недели позже reportAt, CLI возвращает usage-error/exit 2 до API

#### Scenario: Invalid calendar input
- **WHEN** переданы несуществующая дата, неизвестный week token или неверный IANA timezone
- **THEN** CLI возвращает usage-error/exit 2 до API; неверная дата не нормализуется в следующий месяц

### Requirement: Completed tasks during the week
Секция closedTasks SHALL содержать уникальные задачи с текущим логическим статусом completed и непустой фактической closedDate в [from, effectiveTo). CLI MUST отличать завершение от дедлайна и передачи на контроль. Список SHALL включать id, title, responsibleId, groupId, status и closedDate в стабильном порядке id ASC.

#### Scenario: Completed before the deadline
- **WHEN** closedDate находится в выбранной неделе, а deadline — вне неё
- **THEN** задача со статусом completed входит в closedTasks независимо от deadline

#### Scenario: Reopened task
- **WHEN** задача ранее закрывалась на этой неделе, но при чтении снова in_progress либо supposedly_completed
- **THEN** она не входит в closedTasks; прошлые события закрытия не восстанавливаются из истории

#### Scenario: One task and date boundaries
- **WHEN** задача закрывалась несколько раз либо closedDate равна from или effectiveTo
- **THEN** задача считается один раз; from включается, effectiveTo исключается

### Requirement: Current awaiting control list
Секция awaitingControlTasks SHALL содержать уникальные доступные задачи с текущим supposedly_completed независимо от недели создания и передачи на контроль. Список SHALL включать id, title, responsibleId, groupId и status в порядке id ASC. Completed, pending и deferred MUST не попадать в эту секцию.

#### Scenario: Old task still awaiting review
- **WHEN** задача создана до выбранной недели и при чтении ожидает контроля
- **THEN** она входит в awaitingControlTasks; фильтр даты создания к этой секции не применяется

### Requirement: Created task count during the week
Секция newTasks SHALL показывать количество уникальных доступных задач с createdDate в [from, effectiveTo) независимо от текущего статуса. «Новые» MUST означать созданные задачи; автор задачи MUST не подменять фильтр ответственного. Созданная и завершённая за неделю задача SHALL участвовать в обеих соответствующих секциях.

#### Scenario: Created and completed in one week
- **WHEN** createdDate и closedDate задачи попадают в период, а текущий статус completed
- **THEN** задача увеличивает newTasks и присутствует в closedTasks, без дублирования внутри каждой секции

### Requirement: Current in progress task count
Секция inProgressTasks SHALL показывать количество уникальных доступных задач с текущим in_progress независимо от недели создания. Pending, deferred, supposedly_completed и completed MUST не увеличивать это количество.

#### Scenario: Workload is distinct from all open tasks
- **WHEN** проект содержит по одной задаче in_progress, pending, deferred и supposedly_completed
- **THEN** inProgressTasks.count равен 1 при полной выборке; awaitingControlTasks содержит только supposedly_completed

### Requirement: Observed state is distinct from historical snapshot
CLI MUST явно обозначать два текущих показателя как наблюдения во время чтения, в том числе при отчёте за прошлую неделю. ReportAt SHALL ограничивать временной период, а observedUntil SHALL показывать конец сбора. CLI MUST не обещать атомарный снимок или восстановление ответственного, проекта и статусов на конец прошлой недели.

#### Scenario: Report for an earlier week
- **WHEN** отчёт за прошлую неделю формируется сегодня
- **THEN** newTasks и closedTasks используют даты прошлой недели и доступное текущее состояние задач; контроль и in_progress показывают наблюдаемые сейчас статусы с явной подписью

### Requirement: Native read route and policy
CLI SHALL заранее объявлять только `tasks.task.list` REST1 со scope task. Фильтры MUST использовать GROUP_ID, необязательный RESPONSIBLE_ID, REAL_STATUS=3/4/5 и диапазоны CREATED_DATE/CLOSED_DATE. Записи, общий REST-вызов, скрытый REST3 fallback и расширение других команд MUST отсутствовать. Strict-rest3 SHALL отклонять план до credentials/API с policy-denied/exit 4.

#### Scenario: Logical statuses use REAL_STATUS
- **WHEN** строятся запросы для закрытых, контроля и работы
- **THEN** серверные фильтры используют соответственно REAL_STATUS=5, 4 и 3; STATUS с мета-статусами не используется как фильтр логического состояния

#### Scenario: Read policy and scopes
- **WHEN** включён strict-rest3 либо подтверждённо отсутствует scope task
- **THEN** CLI отклоняет операцию с policy-denied либо scope-missing / exit 4 и не делает запросы отчёта; восстановление прав выполняется штатным workflow подключения

### Requirement: Bounded completeness and partial results
CLI SHALL применять общий конечный `--max-scan N` (по умолчанию 10000), проверять пагинацию и сохранять полноту каждой секции. При неполноте count затронутой секции MUST быть null, observedCount SHALL сохранять число подтверждённых уникальных совпадений. Неполные списки MUST быть помечены; весь отчёт SHALL иметь meta.complete=false и exit 3.

#### Scenario: Budget or page failure
- **WHEN** бюджет исчерпан при наличии продолжения, страница после накопленных данных не прочитана либо навигация не продвигается
- **THEN** незавершённые секции неполны, подтверждённые строки и observedCount сохраняются; точный ноль не подставляется

#### Scenario: Exact budget boundary
- **WHEN** последняя прочитанная страница завершает все секции ровно на max-scan
- **THEN** подтверждённое отсутствие продолжения допускает complete=true; ровное число строк само по себе не доказывает ни полноту, ни неполноту

#### Scenario: Missing fields and conflicting observations
- **WHEN** отсутствует обязательное поле, дата невалидна, статус противоречит фильтру или один ID наблюдается в несовместимых текущих статусах
- **THEN** затронутые секции помечаются partial с причинами; одинаковые повторные строки не удваивают количество, конфликтующие не исправляются молча

#### Scenario: Access and empty results
- **WHEN** обход доступной выборки полностью завершён без совпадений
- **THEN** соответствующий count равен 0 и список пуст, с подписью «доступные подключению задачи»; это не утверждение об отсутствии скрытых задач проекта
- **AND** явный отказ доступа не преобразуется в успешный пустой отчёт

### Requirement: Consistent public output
Human, plain и JSON SHALL передавать одни и те же четыре секции, область, период, время наблюдения и полноту. JSON MUST сохранять envelope data/meta/error; plain SHALL использовать TSV с типом секции и явными summary-строками. CLI MUST не обрезать списки по умолчанию скрытым лимитом вывода.

#### Scenario: Successful and partial formatting
- **WHEN** один результат выводится в human, plain и JSON
- **THEN** ID, четыре показателя, причины partial и неизвестные count согласованы; human показывает «неизвестно, найдено N», plain не превращает null в 0
