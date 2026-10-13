# Spec Delta

## MODIFIED Requirements

### Requirement: Exact MVP command registration
Runtime SHALL предоставлять 34 task-команды (30 исходных MVP и четыре task:result команды) и четыре users-команды user-directory, отдельно от локальных connection-команд connection-configuration и встроенной справки/completion. Регистрация SHALL строиться напрямую из единственного явного CommandCatalog без tags/compiler pass и массового сканирования. Исключённые операции не SHALL возвращаться через aliases.

#### Scenario: Root command inventory
- **WHEN** пользователь запускает b24cli без имени команды либо list task без профиля подключения
- **THEN** видны все 34 согласованные task-команды, включая result:add/list/update/delete; остальные исключённые команды отсутствуют, включая task:subtask:add; API и чтение credentials не выполняются

#### Scenario: Explicit catalog consistency
- **WHEN** запускаются unit-tests регистрации приложения
- **THEN** проверяются все 34 task-имени и четыре users-имени и уникальных Command-классов, совпадение AsCommand и отсутствие aliases; compiler pass и console.command tags не требуются, credentials и API не читаются

#### Scenario: Users have their own namespace
- **WHEN** вызывается list users
- **THEN** видны только users:current/find/colleagues/manager; list task сохраняет свои 34 команды, а local connection-команды остаются отдельными

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

#### Scenario: Local attachment mixed route plan
- **WHEN** task:file:attach выбрана с --file/--folder-id
- **THEN** task-v3 допускает только disk.folder.uploadFile REST1 и tasks.task.file.attach REST3; strict-rest3 возвращает policy-denied/exit4 до credentials/API, включая dry-run; режим --file-id сохраняет только REST3

## ADDED Requirements

### Requirement: Command registration validation
Unit-tests SHALL проверять уникальность имён и Command-классов, совпадение AsCommand с единственным явным каталогом и отсутствие исключённых task-операций, включая aliases.

#### Scenario: Validate the declared command registry
- **WHEN** запускаются unit-tests регистрации
- **THEN** проверяются имена, классы, AsCommand и excluded operations/aliases для согласованных task/users namespaces.

### Requirement: Default policy selected route admission
task-v3 SHALL допускать выбранные REST3, IM companion, согласованные REST1 task-маршруты, users-маршруты, opt-in назначение наблюдателя после task:add, выбранное обогащение участников task:show и ролевое чтение участников для task:list/task:find.

#### Scenario: Selected documented default-policy routes
- **WHEN** операция в task-v3 использует согласованный task/users/IM маршрут, opt-in observer или выбранное participant enrichment/role read
- **THEN** policy допускает только выбранный маршрут и проверяет полный план по method/API version и allowed fields.

### Requirement: Local attachment upload route admission
task-v3 SHALL допускать REST1 disk.folder.uploadFile только в локальном режиме task:file:attach.

#### Scenario: Upload route is confined to local attachment mode
- **WHEN** план task:file:attach включает disk.folder.uploadFile
- **THEN** REST1 upload допускается только для локального режима; strict-rest3 отклоняет весь non-v3 план до первого запроса.
