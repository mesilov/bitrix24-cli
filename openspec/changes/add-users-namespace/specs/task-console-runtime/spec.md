# Runtime Spec Delta

## MODIFIED Requirements

### Requirement: Exact MVP command registration
Runtime SHALL предоставлять 34 task-команды (30 исходных MVP и четыре task:result команды) и четыре users-команды capability user-directory, отдельно от локальных connection-команд capability connection-configuration и встроенных команд справки/completion. Регистрация SHALL строиться напрямую из единственного явного CommandCatalog без tags/compiler pass и массового сканирования. Unit-tests SHALL проверять уникальность имён/классов, совпадение AsCommand с каталогом и отсутствие исключённых task-операций. Исключённые операции не SHALL возвращаться через aliases.

#### Scenario: Root command inventory
- **WHEN** пользователь запускает b24cli без имени команды либо list task без профиля подключения
- **THEN** видны все 34 согласованные task-команды, включая result:add/list/update/delete; остальные исключённые команды отсутствуют, включая task:subtask:add; API и чтение credentials не выполняются

#### Scenario: Explicit catalog consistency
- **WHEN** запускаются unit-tests регистрации приложения
- **THEN** проверяются все 34 task-имени и четыре users-имени и уникальных Command-классов, совпадение AsCommand и отсутствие aliases; compiler pass и console.command tags не требуются, credentials и API не читаются

#### Scenario: Users have their own namespace
- **WHEN** вызывается list users
- **THEN** видны только users:current/find/colleagues/manager; list task сохраняет свои 34 команды, а local connection-команды остаются отдельными


### Requirement: Integration happy-path coverage for every CLI command
Каждая прикладная команда CommandCatalog SHALL иметь отдельный integration test-класс и минимум один собственный happy-path test-case через production bootstrap/provider и реальный портал. Каждый case SHALL выполнять только свою CLI-команду и проверять её результат. Fixtures, readback и cleanup SHALL использовать SDK. Unit, mock, negative case и skip не SHALL заменять live happy-path; встроенные Symfony команды проверяются офлайн.

#### Scenario: A new command has no integration happy-path test
- **WHEN** unit-suite сравнивает CommandCatalog с test-классами tests/Integration/Task и tests/Integration/Users, их единственной Group `command:<имя>` и методами Group `happy-path`
- **THEN** отсутствующая/неизвестная команда, общий класс нескольких команд либо отсутствие собственного happy-path метода вызывает отказ; каждый Command соответствует своему CommandTest

#### Scenario: A command gets more integration cases
- **WHEN** для task:update добавляют проверку явного очищения description или отказа прав
- **THEN** case располагается в UpdateTaskCommandTest, запускается независимо от остальных cases и сохраняет отдельный happy-path этой команды; ошибка одной команды не отменяет выполнение тестов другой

#### Scenario: A test attempts to invoke another CLI command
- **WHEN** case одной команды передаёт invoke имя другой CLI-команды
- **THEN** test harness отклоняет такой вызов до выполнения команды; создание fixtures и проверочные чтения происходят через SDK

#### Scenario: Select cases of one command
- **WHEN** выполняется make integration TEST_ARGS='--group command:task:update'
- **THEN** запускаются только cases UpdateTaskCommandTest; TEST_ARGS='--group happy-path' отдельно выбирает собственные happy-path всех команд, отсутствие фильтра запускает всю suite

#### Scenario: Dedicated happy-path is executed
- **WHEN** запускается собственный happy-path case команды
- **THEN** проверяются фактический вызов этой команды, exit0/error=null и ожидаемые данные; один тег без выполнения либо пропущенный case не считается успешной интеграцией

#### Scenario: Successful command with a disposable portal fixture
- **WHEN** интеграционный тест создаёт собственную задачу и выполняет покрываемую команду
- **THEN** тест проверяет содержимое read-результата либо confirmed mutation и доступный readback, явно связан с именем команды и удаляет собственные fixtures без вывода credentials

#### Scenario: An API method has no independent readback
- **WHEN** для task:file:attach сервер возвращает успешный ACK, а REST3 карточка не доказывает file membership
- **THEN** тест проверяет confirmed/taskId/fileId и явно фиксирует ограничение; этот результат не объявляется независимым доказательством membership

#### Scenario: Title search happy-path returns a list of matches
- **WHEN** две собственные задачи имеют уникальную Unicode подстроку title и task:find вызывается с этой подстрокой в другом регистре
- **THEN** тест требует exit0, complete=true и data.items с обеими ожидаемыми парами id/title; неполная выборка без найденных задач не засчитывается

#### Scenario: Documented read completeness boundary
- **WHEN** портал не возвращает выбранное elapsedTime либо history navigation не доказывает полноту полученной записи изменения
- **THEN** интеграционный тест проверяет данные, точную причину, complete=false и partial-result/exit3; произвольный partial/API error не принимается за happy-path, полная история или суммарное время не объявляются доказанными

#### Scenario: User command integration ownership
- **WHEN** новая users-команда добавлена в каталог
- **THEN** отдельный integration class в Users namespace вызывает только свою CLI-команду через production bootstrap и сверяет данные SDK без изменения сотрудников или оргструктуры
- **AND** общий fixture harness не создаёт task fixtures для read-only users тестов; отсутствие нужных live данных/scope фиксируется как skip с причиной, а не pass


### Requirement: Policy covers the complete route plan

Runtime SHALL заранее проверять все потенциальные маршруты операции, включая preflight, по паре method/API version и разрешённым полям. task-v3 SHALL допускать выбранные REST3, IM companion, согласованные REST1 task-маршруты, users-маршруты, opt-in назначение наблюдателя после task:add и выбранное обогащение участников task:show; strict-rest3 SHALL отклонять любой non-v3 план до первого запроса. Совпадение имени метода не SHALL означать взаимозаменяемость версий или скрытый fallback.

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


## ADDED Requirements

### Requirement: Opt-in default observer on task creation
Task:add SHALL применять default.users.observerId только при autoAddObserver=true фактически выбранного сохранённого профиля. Сначала SHALL создаваться задача через REST3; после подтверждённого ID SHALL читаться текущие наблюдатели и выполняться REST1 назначение с сохранением их ID. False/omission SHALL не менять task:add план.

#### Scenario: Create with an opted-in observer
- **WHEN** work имеет observerId=5, autoAddObserver=true и task:add успешно создаёт ID 123
- **THEN** CLI читает наблюдателей 123 через REST1 tasks.task.get и отправляет REST1 tasks.task.update с taskId=123, fields.AUDITORS равным объединению существующих ID и 5
- **AND** task.add/update REST3 fields не содержат auditors, AUDITORS, observerId или autoAddObserver; роли не выводятся из managerId
- **AND** метаданные сообщают профильный источник observerId, ledger и фактические API versions; successful data.operation.taskId остаётся 123

#### Scenario: Existing observers are retained
- **WHEN** новая задача уже содержит наблюдателя 6 либо одновременно 5 и 6
- **THEN** итоговый список сохраняет 6 и содержит 5 ровно один раз; если 5 уже присутствует, лишняя запись update не выполняется
- **AND** невалидная/неполная выборка участников не заменяется пустым списком и не вызывает удаление существующих наблюдателей

#### Scenario: Disabled or unrelated source
- **WHEN** autoAddObserver=false/omitted, или выбран env/file/process источник вместо профиля
- **THEN** task:add не читает и не назначает default observer, не вызывает дополнительные REST1 routes и сохраняет текущие REST3 fields/defaults/output

#### Scenario: Opt-in profile without an observer
- **WHEN** autoAddObserver=true без валидного observerId
- **THEN** metadata validation возвращает configuration-error до API и создания задачи, без подстановки текущего пользователя или managerId

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
- **AND** политика проверяет полный потенциальный план и advanced-field preflight сохраняет собственный существующий контракт

#### Scenario: Tags and observer share one creation
- **WHEN** task:add receives --tag and an enabled default observer
- **THEN** the plan and execution contain create, TAGS write, AUDITORS read and union update; each step uses the same created task ID
- **AND** a failed tag write stops observer calls and retains the created task ID with skipped observer steps; a failed observer step retains the confirmed tag write
