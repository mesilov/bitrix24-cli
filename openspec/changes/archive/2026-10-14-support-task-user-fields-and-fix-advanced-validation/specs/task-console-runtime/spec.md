## MODIFIED Requirements

### Requirement: Explicit patch and bounded advanced input
Runtime SHALL различать пропущенное поле, явное пустое значение и явное очищение. Advanced JSON SHALL проходить allowlist и проверку формы до записи. Разрешённый JSON объект не SHALL предоставлять произвольный REST метод, версии API, TASK_ID вне выбранной задачи либо исключённые действия. task:assign и task:deadline:set SHALL менять только своё поле.

#### Scenario: Restricted update convenience commands
- **WHEN** вызывается task:assign либо task:deadline:set
- **THEN** SDK получает sparse array только с responsibleId либо deadline; status и обязательные поля builder создания не добавляются

#### Scenario: Attempted raw API escape
- **WHEN** --fields, --where или --params пытаются задать неподдержанные ключи, другую цель, смену status либо произвольный маршрут
- **THEN** ввод отклоняется до mutating request; допуск REST1 для участников не превращается в общий legacy update

#### Scenario: Documented write contract instead of editable gate
- **WHEN** an ordinary documented add/update patch has correct types but portal editable is false/null/absent
- **THEN** CLI validates exact names and operation-specific local rules without a metadata gate; remote rights/validation failures retain their classifications

#### Scenario: Offline ordinary advanced preview
- **WHEN** ordinary --fields is used in dry-run
- **THEN** CLI checks the local REST3 contract without credentials/API reads; custom creation uses its explicit native REST1 contract


### Requirement: Planned task tag routes
Без custom creation CLI SHALL передавать обычные поля в REST3; custom creation SHALL использовать REST1 по task-user-fields, а теги — отдельным заранее объявленным REST1 update с полем TAGS. Dry-run SHALL показывать версии, порядок, поля и зависимость ID новой задачи, не выполняя записи. Strict-rest3 SHALL отклонять весь план с тегами до credentials/API; отказ REST3 не SHALL запускать fallback.

#### Scenario: Create preview with a dependent tag write
- **WHEN** task:add с тегами и без custom input выполняется с dry-run/json
- **THEN** план содержит REST3 tasks.task.add с обычными полями, затем REST1 tasks.task.update с `TAGS` и ссылкой на ID результата первого шага; оба шага не выполнены

#### Scenario: Mixed update and strict policy
- **WHEN** task:update получает обычные поля и теги
- **THEN** default policy планирует REST3 update выбранной задачи и REST1 update только TAGS той же задачи
- **AND** strict-rest3 возвращает policy-denied/exit4 до любых чтений/записей; вызов без тегов сохраняет REST3 маршрут

#### Scenario: Advanced tags remain read-only
- **WHEN** пользователь передаёт tags или TAGS через fields/fields-file
- **THEN** CLI отклоняет advanced-запись этих полей и предлагает явную опцию tag; ordinary advanced validation uses the documented local REST3 contract rather than portal editable

### Requirement: Creation and observer assignment have explicit outcomes
CLI SHALL сохранять подтверждённый ID созданной задачи при ошибке последующего чтения/назначения наблюдателя. Частичное выполнение SHALL возвращать exit3, complete=false и ledger подтверждённых/невыполненных шагов; Ctrl-C SHALL сохранять exit130. Неизвестный исход записи SHALL отмечаться outcomeUnknown без автоматического повторения или отката создания.

#### Scenario: Observer assignment fails after successful creation
- **WHEN** создание ID 123 подтверждено, затем чтение наблюдателей или REST1 update завершается отказом прав/API/transport
- **THEN** data.operation.taskId=123 сохраняется, creation подтверждено в ledger, observer step отмечен failed/unknown, error.code=partial-result и exit3
- **AND** вывод предлагает проверить существующую задачу/её участников; повторного create или автоматического удаления нет

#### Scenario: Creation is not confirmed
- **WHEN** tasks.task.add возвращает отказ либо ответ потерян и новый ID неизвестен
- **THEN** чтение/назначение наблюдателей не выполняется, сохраняются существующие error/outcomeUnknown semantics и создание не повторяется

#### Scenario: Cancellation between steps
- **WHEN** Ctrl-C получен после подтверждённого создания, но до назначения наблюдателя
- **THEN** дальнейшие запросы прекращаются, CLI возвращает 130 с ID задачи и ledger, без обещания назначенного наблюдателя или отката

#### Scenario: Offline creation plan
- **WHEN** basic task:add --dry-run использует профиль с включённой автодобавкой
- **THEN** вывод показывает создание, чтение существующих наблюдателей и возможное назначение через AUDITORS с ссылкой на будущий ID, без secrets/API calls
- **AND** политика проверяет полный потенциальный план и ordinary advanced fields are checked locally; custom input has its explicitly declared schema read

#### Scenario: Tags and observer share one creation
- **WHEN** task:add receives --tag and an enabled default observer
- **THEN** the plan and execution contain create, TAGS write, AUDITORS read and union update; each step uses the same created task ID
- **AND** a failed tag write stops observer calls and retains the created task ID with skipped observer steps; a failed observer step retains the confirmed tag write
