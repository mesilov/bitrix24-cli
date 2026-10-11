# Spec Delta

## Purpose

Определить проверяемый контракт запуска команд задач и обращения к Bitrix24: предсказуемую регистрацию MVP, проверку связанных объектов, явные версии API и единое поведение при ошибках. Требования дополняют cli-experience и task-mvp-scope; документ не подтверждает реализацию.

## ADDED Requirements

### Requirement: Exact MVP command registration
Runtime SHALL предоставлять ровно 30 прикладных команд из MVP каталога define-task-jtbd, отдельно от встроенных команд справки и completion. Регистрация SHALL строиться напрямую из единственного явного CommandCatalog без tags/compiler pass и массового сканирования. Unit-tests SHALL проверять уникальность имён/классов, совпадение AsCommand с каталогом и отсутствие post-MVP операций. Исключённые операции не SHALL возвращаться через aliases.

#### Scenario: Root command inventory
- **WHEN** пользователь запускает b24cli без имени команды либо list task без профиля подключения
- **THEN** видны все 30 согласованных task-команд, отсутствуют 29 исключённых команд, включая task:subtask:add; API и чтение credentials не выполняются

#### Scenario: Explicit catalog consistency
- **WHEN** запускаются unit-tests регистрации приложения
- **THEN** проверяются все 30 имён и уникальных Command-классов, совпадение AsCommand и отсутствие aliases; compiler pass и console.command tags не требуются, credentials и API не читаются

### Requirement: Deferred connection resolution
Runtime SHALL проверять синтаксис и локальные ограничения ввода до получения credentials и создания API клиента. Help, list, version и completion SHALL работать без подключения. Доступность команд не SHALL зависеть от роли пользователя на портале; серверные права проверяются при выполнении выбранной операции.

#### Scenario: Invalid local input without credentials
- **WHEN** task:add вызвана без responsible либо task:find содержит пробельный title при отсутствующем профиле
- **THEN** возвращается usage-error с кодом 2, без чтения credentials и API-запроса

#### Scenario: Command visibility and rights
- **WHEN** пользователь открывает справку и затем выполняет команду с недостаточными правами
- **THEN** справка доступна офлайн, а отказ сервера при выполнении возвращается как permission-denied с кодом 1

### Requirement: Integration happy-path coverage for every CLI command
Каждая прикладная команда CommandCatalog SHALL иметь отдельный integration test-класс и минимум один собственный happy-path test-case через production bootstrap/provider и реальный портал. Каждый case SHALL выполнять только свою CLI-команду и проверять её результат. Fixtures, readback и cleanup SHALL использовать SDK. Unit, mock, negative case и skip не SHALL заменять live happy-path; встроенные Symfony команды проверяются офлайн.

#### Scenario: A new command has no integration happy-path test
- **WHEN** unit-suite сравнивает CommandCatalog с test-классами tests/Integration/Task, их единственной Group `command:<имя>` и методами Group `happy-path`
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

### Requirement: Consistent application error boundary
Ошибки выбора команды, разбора аргументов и опций SHALL следовать cli-experience так же, как ошибки выполнения. Неизвестное или неоднозначное имя SHALL возвращать код 2 без запуска предложенной альтернативы. При --json и обычной verbosity SHALL выводиться ровно один envelope, в том числе при ошибке до начала операции; текст ввода после -- не SHALL интерпретироваться как глобальные опции.

#### Scenario: Unknown option with JSON before or after the name
- **WHEN** task:show получает неизвестную опцию и --json расположен до либо после имени команды
- **THEN** оба вызова возвращают код 2 и один JSON object с error.code=usage-error; API не вызывается

#### Scenario: Mistyped write in an interactive terminal
- **WHEN** неизвестное имя похоже на одну команду записи и stdin интерактивен
- **THEN** CLI предлагает корректное имя в ошибке, не задаёт вопрос о запуске альтернативы и не выполняет запись

### Requirement: Explicit patch and bounded advanced input
Runtime SHALL различать пропущенное поле, явное пустое значение и явное очищение. Advanced JSON SHALL проходить allowlist и проверку формы до записи. Разрешённый JSON объект не SHALL предоставлять произвольный REST метод, версии API, TASK_ID вне выбранной задачи либо исключённые действия. task:assign и task:deadline:set SHALL менять только своё поле.

#### Scenario: Restricted update convenience commands
- **WHEN** вызывается task:assign либо task:deadline:set
- **THEN** SDK получает sparse array только с responsibleId либо deadline; status и обязательные поля builder создания не добавляются

#### Scenario: Attempted raw API escape
- **WHEN** --fields, --where или --params пытаются задать неподдержанные ключи, другую цель, смену status либо произвольный маршрут
- **THEN** ввод отклоняется до mutating request; допуск REST1 для участников не превращается в общий legacy update

### Requirement: Participant relation select projections
Runtime SHALL раскрывать корневые participant selects в id/name. task:list SHALL использовать REST3 projections. task:show SHALL обогащать выбранные auditors/accomplices через заранее объявленные REST1 role IDs и user.get, сохраняя остальные поля карточки из REST3. IDs SHALL относиться к выбранной TASK_ID; lookup SHALL быть ограниченным и сохранять порядок ролей.

#### Scenario: Participant relation select projections
- **WHEN** task:list получает --select accomplices либо --select auditors
- **THEN** runtime локально раскрывает корень в соответствующие `.id` и `.name` проекции документированного user object; явно указанные nested select сохраняются

#### Scenario: Task card enriched with participant users
- **WHEN** task:show получает --select auditors либо accomplices, включая поддержанные nested поля id/name/email/gender/externalAuthId
- **THEN** карточка читается через REST3 без этих relations, ID выбранных ролей читаются через REST1 выбранной задачи, уникальные ID обогащаются user.get с минимальным select; все использованные версии отражены в meta, root name собирается из NAME/SECOND_NAME/LAST_NAME

#### Scenario: ID-only and empty participant roles
- **WHEN** выбран только auditors.id/accomplices.id либо обе выбранные роли пусты
- **THEN** user.get не вызывается; пустая подтверждённая роль возвращается как [], а ID-only объект содержит только нормализованный id

#### Scenario: Incomplete user enrichment
- **WHEN** после успешного чтения карточки отсутствуют user rows/selected fields либо companion read завершается ошибкой
- **THEN** доступная карточка сохраняется с complete=false, причиной и exit3; известные ID сохраняются, неизвестные данные обозначены null, непроверенный состав роли не подменяется пустым списком; interrupted остаётся exit130, повторов нет

#### Scenario: Bounded and validated user lookup
- **WHEN** требуется несколько пользователей либо API возвращает чужой/повторный ID или неверную форму ответа
- **THEN** user.get фильтруется только по выбранным ID порциями до 50 и общим budget 10000; чужие rows не добавляются к карточке, недоказанная полнота обозначается partial; неподдержанные projections отклоняются до credentials

### Requirement: Policy covers the complete route plan

Runtime SHALL заранее проверять все потенциальные маршруты операции, включая preflight, по паре method/API version и разрешённым полям. task-v3 SHALL допускать выбранные REST3, IM companion, 14 согласованных REST1 команд и выбранное обогащение участников task:show; strict-rest3 SHALL отклонять любой non-v3 план до первого запроса. Совпадение имени метода не SHALL означать взаимозаменяемость версий или скрытый fallback.

#### Scenario: Strict policy with participant enrichment
- **WHEN** task:show с auditors/accomplices выбран при strict-rest3
- **THEN** возвращается policy-denied с кодом 4 до credentials/API calls; task:show без этих relations сохраняет маршрут только REST3

#### Scenario: Strict policy before a mixed route plan
- **WHEN** task:chat:update, task:time:update или task:participants:set выбраны при strict-rest3
- **THEN** возвращается policy-denied с кодом 4 до любого preflight или записи, даже если первый плановый маршрут использует REST3

#### Scenario: Version-specific task update
- **WHEN** выполняется task:update либо task:participants:set в task-v3 режиме
- **THEN** первая операция обращается к REST3 update writable полей, вторая к REST1 update только указанных accomplice/auditor наборов; каждая отражает свою версию API

### Requirement: Verified task chat message binding
Перед изменением или удалением сообщения runtime SHALL получить chat выбранной TASK_ID и доказать принадлежность MESSAGE_ID этому чату. Проверка SHALL иметь конечный budget. Недостаточные данные, исчерпание budget либо отсутствие сообщения не SHALL разрешать mutation; права автора/администратора SHALL оставаться серверным условием.

#### Scenario: Message from a different task
- **WHEN** пользователь передаёт TASK_ID и MESSAGE_ID другого чата либо связь не доказана в пределах budget
- **THEN** CLI возвращает message-binding-unverified с кодом 4 и не вызывает im.message.update/delete; task:chat:list предлагается для проверки контекста

### Requirement: Verified task child resource binding
Runtime SHALL проверять принадлежность time entry, checklist root и item выбранной TASK_ID перед их изменением или удалением. Пункты SHALL принадлежать выбранному root; корень не SHALL приниматься за item. Неполные либо повреждённые данные дерева не SHALL допускать запись. --force SHALL отменять только диалог удаления.

#### Scenario: Wrong entry or checklist node
- **WHEN** ENTRY_ID отсутствует в задаче либо parent/item принадлежит другой задаче или чек-листу
- **THEN** CLI не выполняет mutation, сообщает resource-binding-unverified с кодом 4 и сохраняет исходное состояние

### Requirement: Prepared mutation preview
Команда записи SHALL сначала формировать проверенный план с целью, изменениями, маршрутами и безопасными чтениями. --dry-run SHALL возвращать этот план без mutation и без подтверждения удаления. Превью не SHALL обещать серверное принятие или отменять API policy, ID binding и запрет исключённых операций.

#### Scenario: Validated delete preview
- **WHEN** пользователь вызывает task:checklist:item:delete с корректными IDs и --dry-run
- **THEN** выполняются допустимые чтения для проверки пункта, показывается предполагаемое удаление без диалога и вызова delete; API version отражена явно

### Requirement: Complete result and execution accounting
Runtime SHALL нормализовать API ответы в пользовательские данные и meta, не выводя SDK объекты. Для списков SHALL сохраняться data.items array и признаки полноты; для многошаговой записи — выполненные/невыполненные шаги. При потере ответа после записи SHALL выставляться outcomeUnknown, без автоматического повтора; подтверждённая частичная запись SHALL возвращать код 3.

#### Scenario: Several files with a failure
- **WHEN** один из нескольких file attachments подтверждён, а следующий отклонён
- **THEN** CLI возвращает partial с кодом 3, перечисляет подтверждённые и невыполненные attachments и не заявляет откат

#### Scenario: Response lost after a single create
- **WHEN** API не вернул ответ после отправки task:add
- **THEN** CLI возвращает код 1 с outcomeUnknown=true и безопасным способом сверки; повторная задача автоматически не создаётся

#### Scenario: Method-specific legacy write acknowledgements
- **WHEN** REST1 elapseditem update/delete или checklistitem update возвращают успешный null, либо participants update возвращает объект task выбранной задачи
- **THEN** runtime принимает только соответствующий подтверждённый формат: SDK `[null]` для трёх void методов либо совпадающий положительный task.id для participants; false, отсутствующее подтверждение и чужой task.id отклоняются

### Requirement: Cancellation stops subsequent requests

Runtime SHALL ограничивать сетевые шаги документированным timeout и прекращать новые запросы после Ctrl-C. Отмена SHALL возвращать 130 с доступной информацией о выполненных шагах и неопределённости отправленной записи; она не SHALL обещать отмену уже принятого сервером действия.

#### Scenario: Interruption after an attachment
- **WHEN** Ctrl-C поступает после подтверждения первого attachment до следующего шага
- **THEN** второй attachment не отправляется, возвращается 130 и сведения о первом шаге без обещания отката

### Requirement: Unit acceptance and separate portal evidence
Runtime SHALL позволять проверять регистрацию, парсинг, вывод, ошибки, gates и планы с подставными API-ответами без портала. Успех этих проверок не SHALL считаться доказательством прав, схемы или поведения конкретного Bitrix24 подключения; portal acceptance SHALL фиксироваться отдельно с версией, ролью и результатом проверки.

#### Scenario: Passing command contract tests
- **WHEN** проверки команд и API планов прошли на подставных ответах
- **THEN** отчёт подтверждает локальный контракт и отдельно перечисляет ещё не выполненные portal cases, не объявляя команды принятыми на портале

#### Scenario: Portal internal server error without API fallback
- **WHEN** SDK оборачивает API INTERNAL_SERVER_ERROR в TransportException
- **THEN** CLI возвращает api-error и безопасный details.apiErrorCode, не повторяет запрос и не меняет API version; для отправленной записи сохраняется outcomeUnknown

#### Scenario: Confirmed chat send without message ID
- **WHEN** task:chat:send получает boolean подтверждение отправки без ID
- **THEN** возвращается success с кодом 0 и resourceId=null; outcomeUnknown не выставляется и ID не восстанавливается по тексту

#### Scenario: Time relation without an aggregate guarantee
- **WHEN** task:time:show читает elapsedTime из карточки
- **THEN** результат обозначает scope связанного поля и не объявляет его полной историей или total seconds без подтверждённого контракта и полноты

### Requirement: Deferred root environment webhook connection
Runtime SHALL поддерживать BITRIX24_WEBHOOK из выбранного B24CLI_ENV_FILE, иначе существующего root .env.local, иначе root .env, с приоритетом переменных процесса. Чтение env и создание SDK ServiceBuilder SHALL происходить после local validation и API policy. Webhook не SHALL попадать в argv, output или container config diagnostics. Integration tests SHALL запускаться отдельно и использовать тот же provider и выбранный файл для fixture settings.

#### Scenario: Root local environment and explicit file precedence
- **WHEN** в checkout доступны .env и .env.local, либо выбран B24CLI_ENV_FILE
- **THEN** credentials берутся из .env.local, а явный файл заменяет этот выбор; process credentials имеют высший приоритет, глобальное окружение не изменяется

#### Scenario: Offline help with an unreadable environment file
- **WHEN** пользователь запускает help/list/version/completion без доступного env файла
- **THEN** команда работает без чтения credentials и API calls

#### Scenario: Explicit integration suite without credentials
- **WHEN** запускается integration suite без webhook
- **THEN** live cases явно пропускаются без портальных mutations; это не объявляется portal acceptance

### Requirement: Explicit unit and integration test entrypoints
Локальная suite SHALL называться unit-tests и находиться в tests/Unit. Make unit SHALL запускать её без внешнего портала. Make integration SHALL отдельно запускать integration-tests через реальный provider и выбранный env. CI SHALL явно запускать make unit без портальных credentials; прежние test/test-integration SHALL оставаться совместимыми aliases.

#### Scenario: Separate test runs
- **WHEN** запускается make unit либо make integration
- **THEN** запускается только соответствующая PHPUnit suite; отсутствие webhook явно пропускает portal cases, unit success не объявляется portal acceptance
