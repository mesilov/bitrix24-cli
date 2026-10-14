# task-console-runtime Specification

## Purpose

Определить проверяемый контракт запуска команд задач и обращения к Bitrix24: предсказуемую регистрацию MVP, проверку связанных объектов, явные версии API и единое поведение при ошибках. Требования дополняют cli-experience и task-mvp-scope; документ не подтверждает реализацию.

## Requirements

### Requirement: Exact MVP command registration
Runtime SHALL предоставлять 43 task-команды (42 текущие, включая семь lifecycle команд и task:my, и task:status:durations) и четыре users-команды user-directory, отдельно от локальных connection-команд connection-configuration и встроенной справки/completion. Регистрация SHALL строиться напрямую из единственного явного CommandCatalog без tags/compiler pass и массового сканирования. Исключённые операции не SHALL возвращаться через aliases.

#### Scenario: Root command inventory
- **WHEN** пользователь запускает b24cli без имени команды либо list task без профиля подключения
- **THEN** видны все 43 согласованные task-команды, включая result:add/list/update/delete, семь lifecycle команд, task:my и task:status:durations; остальные исключённые команды отсутствуют, включая task:subtask:add; API и чтение credentials не выполняются

#### Scenario: Explicit catalog consistency
- **WHEN** запускаются unit-tests регистрации приложения
- **THEN** проверяются все 43 task-имени и четыре users-имени и уникальных Command-классов, совпадение AsCommand и отсутствие aliases; compiler pass и console.command tags не требуются, credentials и API не читаются

#### Scenario: Users have their own namespace
- **WHEN** вызывается list users
- **THEN** видны только users:current/find/colleagues/manager; list task сохраняет свои 43 команды, а local connection-команды остаются отдельными

#### Scenario: Lifecycle command integration coverage
- **WHEN** проверяются каталог и tests/Integration/Task
- **THEN** каждая из семи lifecycle команд имеет собственный CommandTest с group command:<имя> и happy-path; общий класс всех переходов не заменяет эти cases

### Requirement: Command registration validation
Unit-tests SHALL проверять уникальность имён и Command-классов, совпадение AsCommand с единственным явным каталогом и отсутствие исключённых task-операций, включая aliases.

#### Scenario: Validate the declared command registry
- **WHEN** запускаются unit-tests регистрации
- **THEN** проверяются имена, классы, AsCommand и excluded operations/aliases для согласованных task/users namespaces.

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

#### Scenario: Documented write contract instead of editable gate
- **WHEN** an ordinary documented add/update patch has correct types but portal editable is false/null/absent
- **THEN** CLI validates exact names and operation-specific local rules without a metadata gate; remote rights/validation failures retain their classifications

#### Scenario: Offline ordinary advanced preview
- **WHEN** ordinary --fields is used in dry-run
- **THEN** CLI checks the local REST3 contract without credentials/API reads; custom creation uses its explicit native REST1 contract

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

#### Scenario: Explicit lifecycle routes
- **WHEN** выбрана одна из семи lifecycle команд
- **THEN** task-v3 допускает только соответствующий REST1 tasks.task.<action>, REST3 tasks.task.get/access.get и условный tasks.task.result.list для complete; strict-rest3 отклоняет весь план до credentials и первого запроса

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
Runtime SHALL выбирать подключение по connection-configuration и отложенно читать credentials/создавать SDK после validation/API policy. Root .env.local/.env SHALL быть последним source-only fallback; standalone не SHALL искать dotenv в cwd/рядом с executable. Webhook не SHALL попадать в argv/output/diagnostics. Integration tests SHALL использовать production provider/выбранный файл и запускаться отдельно.

#### Scenario: Root local environment and explicit file precedence
- **WHEN** source invocation не имеет profile/env-file selector, B24CLI_PROFILE или saved default, а в project root доступны .env и .env.local либо выбран B24CLI_ENV_FILE
- **THEN** без process webhook выбранный B24CLI_ENV_FILE используется первым, иначе .env.local перед .env; process webhook имеет приоритет над этими неявными файлами, глобальное окружение не изменяется

#### Scenario: Offline help with an unreadable environment file
- **WHEN** пользователь запускает help/list/version/completion без доступного env файла
- **THEN** команда работает без чтения credentials и API calls

#### Scenario: Explicit connection selector
- **WHEN** profile/env-file selector задан вместе с process BITRIX24_WEBHOOK
- **THEN** явный selector имеет приоритет по connection-configuration; ошибки выбранного источника не переключают портал

#### Scenario: Standalone ignores implicit dotenv
- **WHEN** standalone executable запускается в каталоге с .env.local/.env без явно выбранного файла
- **THEN** эти файлы не читаются; используются process env или выбранный/saved default профиль согласно connection-configuration

#### Scenario: Explicit integration suite without credentials
- **WHEN** запускается integration suite без webhook
- **THEN** live cases явно пропускаются без портальных mutations; это не объявляется portal acceptance

### Requirement: Explicit unit and integration test entrypoints
Локальная suite SHALL называться unit-tests и находиться в tests/Unit. Make unit SHALL запускать её без внешнего портала. Make integration SHALL отдельно запускать integration-tests через реальный provider и выбранный env. CI SHALL явно запускать make unit без портальных credentials; прежние test/test-integration SHALL оставаться совместимыми aliases.

#### Scenario: Separate test runs
- **WHEN** запускается make unit либо make integration
- **THEN** запускается только соответствующая PHPUnit suite; отсутствие webhook явно пропускает portal cases, unit success не объявляется portal acceptance

### Requirement: Explicit task tag input
CLI SHALL принимать повторяемую опцию `--tag NAME` в task:add/task:update и `--clear-tags` в task:update. Указанный набор SHALL заменять теги; отсутствие опций SHALL сохранять их. Пустые/пробельные значения и конфликт tag/clear SHALL отклоняться до credentials/API. Названия SHALL сохранять Unicode, пробелы и # без преобразования.

#### Scenario: Create with multiple tags
- **WHEN** пользователь создаёт задачу с `--tag '#плановая' --tag 'Этап 1'`
- **THEN** CLI планирует создание и запись двух переданных названий без удаления # или изменения регистра

#### Scenario: Replace tags without ordinary fields
- **WHEN** task:update 123 получает только `--tag '#ошибка'`
- **THEN** операция допустима и заменяет набор тегов выбранной задачи без пустого REST3 update

#### Scenario: Clear and preserve are distinct
- **WHEN** task:update 123 получает `--clear-tags`
- **THEN** CLI явно планирует пустой набор тегов
- **AND** обычное изменение title без tag/clear не выполняет запись тегов

#### Scenario: Invalid or conflicting input
- **WHEN** значение tag пустое/пробельное либо tag задан вместе с clear-tags, advanced fields или fields-file
- **THEN** CLI возвращает usage-error/exit2 без чтения credentials и сетевого запроса

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

### Requirement: Account for partial task tag mutations
CLI SHALL учитывать подтверждённые, неуспешные и неизвестные шаги записи тегов. После подтверждённого первого шага и отказа второго SHALL возвращаться partial/exit3 с TASK_ID и выполненными изменениями; Ctrl-C SHALL сохранять exit130. Потеря ответа SHALL выставлять outcomeUnknown без повторной записи или автоматического удаления задачи.

#### Scenario: Creation succeeded and tag write failed
- **WHEN** REST3 add вернул ID, а REST1 TAGS update отклонён
- **THEN** результат содержит ID созданной задачи, подтверждённый add, неуспешный шаг тегов, complete=false/exit3 и рекомендацию повторить только task:update с тегами

#### Scenario: Response lost and cancellation
- **WHEN** после подтверждённого add ответ TAGS update потерян либо поступил Ctrl-C
- **THEN** CLI сохраняет известный TASK_ID, outcomeUnknown для отправленного неподтверждённого шага, прекращает запросы и не обещает откат; отмена возвращает 130

#### Scenario: No mutation was confirmed
- **WHEN** единственная tags-only запись отклонена либо её ответ потерян
- **THEN** CLI возвращает исходную ошибку/exit1 и outcomeUnknown только при неизвестном результате; успех или подтверждённый partial не заявляется

### Requirement: Independent tag readback
CLI SHALL поддерживать проверку тегов через существующий task:show с select tags.id/tags.name. Теги SHALL возвращаться как REST3 объекты id/name. ACK записи SHALL означать подтверждение операции, а доказательство сохранённых названий SHALL фиксироваться отдельным чтением в portal acceptance.

#### Scenario: Read saved tags
- **WHEN** после записи пользователь запускает task:show 123 с select tags.id и tags.name
- **THEN** CLI возвращает доступные объекты тегов выбранной задачи без переименования API-полей и без преобразования их в строковый массив записи

#### Scenario: Portal verification is unavailable
- **WHEN** unit/codec проверки успешны, но чтение реального портала не выполнено
- **THEN** отчёт подтверждает локальный контракт и явно оставляет portal acceptance непроверенным

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
- **AND** политика проверяет полный потенциальный план и ordinary advanced fields are checked locally; custom input has its explicitly declared schema read

#### Scenario: Tags and observer share one creation
- **WHEN** task:add receives --tag and an enabled default observer
- **THEN** the plan and execution contain create, TAGS write, AUDITORS read and union update; each step uses the same created task ID
- **AND** a failed tag write stops observer calls and retains the created task ID with skipped observer steps; a failed observer step retains the confirmed tag write
## Решение о приёмке RC 0.1.0 — 12.10.2026

По явному решению пользователя непроверенные части portal tasks 5.2–5.4 и связанного `define-task-jtbd` task 2.3 перенесены в milestone **0.2.0**, [#42](https://github.com/mesilov/bitrix24-cli/issues/42), change `complete-task-portal-acceptance` (`openspec/changes/complete-task-portal-acceptance/`). Они исключены из обязательной приёмки RC 0.1.0; перенос не является новым runtime proof. Уже выполненные 30 собственных happy-path и исходные records сохраняются.

Границы RC: denied-role matrix не подтверждена; history/elapsedTime completeness частична; file attachment ACK без независимого membership; reassignment только на текущего пользователя; native REST3 filled auditors limitation сохраняется для task:list, task:show использует заранее выбранную композицию чтений без error-triggered fallback/retry. Binding/policy/partial/outcomeUnknown/cancellation и 29 post-MVP исключений сохраняются. #19/#23/native packaging остаются критериями RC. [Полный список ограничений](../../../docs/rc-0.1.0.md).
