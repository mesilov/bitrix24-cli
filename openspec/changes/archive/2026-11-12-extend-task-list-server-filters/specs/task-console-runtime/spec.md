## MODIFIED Requirements

### Requirement: Policy covers the complete route plan
Runtime SHALL заранее проверять все потенциальные маршруты операции, включая preflight, по паре method/API version и разрешённым полям. strict-rest3 SHALL отклонять любой non-v3 план до первого запроса. Совпадение имени метода не SHALL означать взаимозаменяемость версий или скрытый fallback.

#### Scenario: Strict policy with participant enrichment
- **WHEN** task:show с auditors/accomplices выбран при strict-rest3
- **THEN** возвращается policy-denied с кодом 4 до credentials/API calls; task:show без этих relations сохраняет маршрут только REST3

#### Scenario: Strict policy before a mixed route plan
- **WHEN** task:chat:update, task:time:update или task:participants:set выбраны при strict-rest3
- **THEN** возвращается policy-denied с кодом 4 до любого preflight или записи, даже если первый плановый маршрут использует REST3

#### Scenario: Version-specific task update
- **WHEN** выполняется task:update либо task:participants:set в task-v3 режиме
- **THEN** первая операция обращается к REST3 update writable полей, вторая к REST1 update только указанных accomplice/auditor наборов; каждая отражает свою версию API

#### Scenario: Use REST1 for capabilities absent in REST3
- **WHEN** новая команда users либо opt-in назначение наблюдателя требует метод, отсутствующий в REST3
- **THEN** стандартная policy допускает заранее выбранный документированный REST1 маршрут и отражает версию в meta, без дополнительного пользовательского подтверждения или попытки сначала вызвать отсутствующий REST3 метод

#### Scenario: Existing explicitly selected strict mode
- **WHEN** пользователь явно выбрал strict-rest3 для users-команды либо task:add с включённой автодобавкой наблюдателя
- **THEN** существующий строгий режим отклоняет весь non-v3 план до credentials, чтения портала и создания задачи
- **AND** task:add без автодобавки сохраняет свой существующий план, включая REST1 запись TAGS при явном --tag

#### Scenario: Planned participant role filtering
- **WHEN** task:list или task:find получает --role accomplice либо --role auditor при task-v3
- **THEN** план заранее включает REST3 tasks.task.list и REST1 tasks.task.get только для ID соответствующей роли выбранной задачи; версии отражены в meta, user.get/user.current и mutating request не добавляются, ошибка не запускает fallback/retry

#### Scenario: Strict policy before participant role filtering
- **WHEN** task:list или task:find получает --role accomplice либо --role auditor при strict-rest3
- **THEN** policy-denied/exit 4 возвращается до чтения credentials и API calls, даже если доступная выборка могла бы оказаться пустой

#### Scenario: Strict scalar role filtering
- **WHEN** task:list или task:find получает --role responsible либо --role creator при strict-rest3
- **THEN** роль проверяется локально по REST3 responsibleId/creatorId, план содержит только REST3 tasks.task.list; отсутствие --role не добавляет companion request

#### Scenario: Planned server list filters
- **WHEN** task:list или task:find без временных флагов получает при task-v3 --group-id, --responsible, --role responsible, --role creator или --status с документированным для REAL_STATUS значением, а выбранные select/order/where поддерживаются REST1 списком
- **THEN** план заранее содержит REST1 tasks.task.list вместо REST3 tasks.task.list и не содержит tasks.task.get для проверки группы; для --role accomplice/auditor план дополнительно содержит REST1 tasks.task.get чтения участников; версии отражены в meta

#### Scenario: Strict policy keeps local list filters
- **WHEN** те же task:list или task:find выбраны при strict-rest3
- **THEN** план содержит только REST3 маршруты текущей локальной фильтрации; policy-denied для --group-id, --responsible, --role responsible и --role creator не возвращается


#### Scenario: Planned creation and change windows
- **WHEN** task:list или task:find получает --created-window или --changed-window
- **THEN** при task-v3 план заранее содержит REST1 tasks.task.list и не содержит REST3 tasks.task.list; при strict-rest3 весь план отклоняется с policy-denied/exit4 до credentials и API calls
#### Scenario: Local attachment mixed route plan
- **WHEN** task:file:attach выбрана с --file/--folder-id
- **THEN** task-v3 допускает только disk.folder.uploadFile REST1 и tasks.task.file.attach REST3; strict-rest3 возвращает policy-denied/exit4 до credentials/API, включая dry-run; режим --file-id сохраняет только REST3

#### Scenario: Explicit lifecycle routes
- **WHEN** выбрана одна из семи lifecycle команд
- **THEN** task-v3 допускает только соответствующий REST1 tasks.task.<action>, REST3 tasks.task.get/access.get и условный tasks.task.result.list для complete; strict-rest3 отклоняет весь план до credentials и первого запроса
