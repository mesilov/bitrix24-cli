# Spec Delta

## ADDED Requirements

### Requirement: Planned opt in unread routes
План task:list --unread SHALL заранее включать REST1 im.counters.get с effect=read и необходимые существующие task list/get маршруты. Стандартная policy SHALL допускать этот выбранный маршрут; strict-rest3 SHALL отклонять весь план до credentials и API. Проверка scopes SHALL требовать im только для вариантов, которые используют IM.

#### Scenario: Strict policy rejects unread even for an empty selection
- **WHEN** `task:list --unread --api-policy strict-rest3` выбрана до получения данных портала
- **THEN** policy-denied/exit4 возвращается без credentials/API независимо от потенциального числа задач

#### Scenario: Missing IM scope affects the opt in operation
- **WHEN** подключение имеет task/tasks, но не имеет im
- **THEN** task:list --unread блокируется существующим scope gate до task/IM чтений
- **AND** task:list без --unread сохраняет прежний допуск; task:chat:list по-прежнему требует im
- **AND** connection:check перечисляет task:list в potentiallyAffectedCommands с im как условную зависимость опции

#### Scenario: Temporal and participant routes remain declared
- **WHEN** --unread сочетается с deadline-window/overdue либо --role auditor/accomplice
- **THEN** план включает im.counters.get и соответствующий существующий REST1 task маршрут без скрытого fallback
- **AND** фактически использованные API versions отражаются в meta

### Requirement: One isolated bounded unread observation
В одном запуске task:list --unread runtime SHALL получать не более одного снимка IM-счётчиков и соединять его с существующим ограниченным обходом задач. Постоянный кэш, чтение истории для подсчёта и запросы состояния каждого найденного чата SHALL не добавляться. Дополнительное чтение выбранной задачи допустимо только для неподтверждённого chatId.

#### Scenario: Several pages share one snapshot
- **WHEN** подходящие задачи находятся на нескольких страницах
- **THEN** im.counters.get вызывается один раз; im.dialog.get, im.recent.list и im.dialog.messages.get не вызываются этим фильтром
- **AND** `--limit` применяется после unread, не обрезает выборку до проверки этого критерия

#### Scenario: No extra binding request for a confirmed association
- **WHEN** task API вернул валидный chatId выбранной задачи
- **THEN** счётчик присоединяется по этому ID без отдельного IM-запроса на строку
- **AND** чтение task get для отсутствующей связи ограничено задачами, прошедшими обычные критерии

#### Scenario: Cancellation and failures preserve boundaries
- **WHEN** получен Ctrl-C, отказ API либо превышен бюджет
- **THEN** последующие запросы прекращаются; Ctrl-C возвращает 130, исходные API/transport ошибки сохраняют классификацию
- **AND** неполные данные не приводят к повторной записи, смене API version или отметке сообщений прочитанными

#### Scenario: Two invocations use independent state
- **WHEN** следующий запуск использует другое подключение или пользователь прочитал сообщения между запусками
- **THEN** runtime получает новый снимок в контексте выбранного подключения и не использует предыдущие счётчики
