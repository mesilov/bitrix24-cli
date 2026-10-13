# cli-experience Specification

## Purpose

Определить удобный и предсказуемый интерфейс будущего b24cli CLI для работы человека в терминале и автоматизации. Референс — [Command Line Interface Guidelines](https://github.com/cli-guidelines/cli-guidelines/tree/697d6a29fc8c93d3981a755c0c7683507ad39c3e), проверенный 2026-10-09; конкретные имена, значения и коды ниже являются решениями проекта, а не дословными требованиями гайда. Эта спецификация не подтверждает наличие реализации.

## Requirements

### Requirement: Direct and consistent command structure
CLI SHALL использовать b24cli и Symfony имена task:<action>/task:<resource>:<action>. Добавление задачи SHALL называться task:add; имена отражают операцию без маппинга REST 1:1. Команда одной задачи SHALL принимать позиционный TASK_ID, связанные ID — флагами; task:list/find задают критерии флагами. Для пунктов SHALL использоваться task:checklist:item:<action>. Документация SHALL содержать полные имена; однозначные сокращения Symfony сохраняются.

#### Scenario: Task editing from the terminal
- **WHEN** пользователь вызывает `b24cli task:update 123 --title "Отчёт за октябрь"`
- **THEN** интерфейс адресует задачу 123 и явное изменение заголовка без необходимости использовать Make или JSON

#### Scenario: Two related entities
- **WHEN** пользователь вызывает `b24cli task:chat:update 123 --message 456 --text "Уточнение"`
- **THEN** идентификатор задачи и идентификатор сообщения различаются; проверка привязки сообщения к задаче обязательна

#### Scenario: Mistyped write command
- **WHEN** пользователь вводит неизвестное или неоднозначное имя команды записи
- **THEN** CLI завершает вызов с ошибкой использования и предлагает известное имя без выполнения записи

### Requirement: Discoverable help without remote operations
CLI SHALL поддерживать `-h`/`--help`, `b24cli help COMMAND`, `b24cli list [NAMESPACE]` и `--version`/`-V`. Справка SHALL показывать краткое назначение, примеры обычных действий, обязательные флаги, значения по умолчанию, ограничения и ссылку на документацию. Справка и версия SHALL работать без авторизации и запросов на портал.

#### Scenario: Root or group invocation
- **WHEN** пользователь вызывает `b24cli` либо `b24cli list task`
- **THEN** CLI выполняет встроенную list и показывает доступные команды целиком либо в namespace task, возвращает 0 и не выполняет удалённых операций

#### Scenario: Command help
- **WHEN** пользователь вызывает `b24cli task:update --help` или `b24cli help task:update`
- **THEN** оба вызова показывают одну справку с примерами изменения заголовка, ответственного и описания, без требования TASK_ID

### Requirement: Convenient and explicit task field updates
CLI SHALL предоставлять `--title`, `--creator`, `--responsible`, `--group-id`, `--deadline`, `--description` и `--description-file` для создания и применимые флаги для изменения. `task:update` SHALL принимать хотя бы одно явное изменение и отправлять только заданные поля. `task:assign` SHALL изменять только responsibleId; остальные флаги SHALL отображаться на документированные writable REST3 поля.

#### Scenario: Basic task creation
- **WHEN** пользователь задаёт title, creator и responsible флагами команды task:add
- **THEN** CLI формирует title, creatorId и responsibleId; отсутствующие обязательные значения приводят к ошибке до записи, без молчаливого выбора сотрудника

#### Scenario: Several ordinary changes
- **WHEN** пользователь вызывает `b24cli task:update 123 --title "Отчёт" --responsible 84 --description "Добавить сравнение"`
- **THEN** CLI изменяет три заданных поля одним update-запросом и не отправляет значения остальных полей

#### Scenario: Empty and omitted description
- **WHEN** пользователь явно передаёт `--description ""` либо не передаёт описание
- **THEN** первый вызов задаёт пустое описание, а второй сохраняет существующее; пустой заголовок отклоняется

### Requirement: Explicit file and structured input
CLI SHALL различать литеральный текст, JSON в `--fields` и файл JSON в `--fields-file`; текстовые и JSON файловые флаги SHALL принимать PATH или `-` для stdin. Бинарный `task:file:attach --file` SHALL принимать только локальный PATH с ограничениями task-file-upload, без stdin `-`. `--fields` и `--fields-file` SHALL быть отдельным advanced режимом без смешивания с флагами редактирования полей. CLI SHALL проверять формат и доступность ввода до записи, не читать stdin неявно и не разрешать два потребителя stdin.

#### Scenario: Description from a file or pipe
- **WHEN** пользователь задаёт `--description-file brief.md` либо передаёт текст в pipe и задаёт `--description-file -`
- **THEN** CLI использует содержимое выбранного источника как описание; одновременный `--description` отклоняется

#### Scenario: Advanced update input
- **WHEN** пользователь передаёт `--fields '{"title":"Отчёт"}'`, `--fields-file changes.json` либо `--fields-file -` с JSON в stdin
- **THEN** CLI принимает один JSON object; неизвестные/read-only поля, конфликтующие источники и gated status changes отклоняются до записи

#### Scenario: Terminal is not a file payload
- **WHEN** файловый флаг содержит `-`, но stdin является терминалом, либо два файловых флага требуют stdin
- **THEN** CLI немедленно завершает вызов с ошибкой использования и объясняет способ передать файл или pipe

#### Scenario: Local binary attachment input
- **WHEN** task:file:attach получает --file PATH либо --file -
- **THEN** обычный локальный файл проверяется по task-file-upload; `-` отклоняется с usage-error/exit2 без чтения stdin/credentials/API, текстовые и JSON файловые флаги сохраняют собственный stdin режим

### Requirement: Human output and composable structured output
При обычной verbosity CLI SHALL выводить компактные карточки или таблицы для человека; --quiet/--silent подавляют вывод по нативным правилам Symfony. `--plain` SHALL давать стабильный текст без оформления, `--json` — один JSON object с data, meta и error; meta SHALL содержать schemaVersion. Результат SHALL идти в stdout, диагностика и progress — в stderr. `--json` и `--plain` SHALL исключать друг друга.

#### Scenario: Successful ordinary edit
- **WHEN** пользователь успешно изменяет задачу без флагов формата
- **THEN** stdout кратко сообщает ID задачи и изменённые поля; API payload и debug trace не выводятся по умолчанию

#### Scenario: JSON in a pipeline
- **WHEN** пользователь вызывает команду с `--json` и передаёт stdout в jq
- **THEN** stdout содержит только валидный JSON object, включая empty result; progress и пояснения не нарушают JSON

#### Scenario: Plain list output
- **WHEN** пользователь вызывает список с `--plain`
- **THEN** вывод содержит стабильные TSV columns без заголовка и по одной строке на запись; tabs/newlines в значениях экранируются, порядок и поля описаны в справке

### Requirement: Explicit filtering and completeness of lists
Без новых временных флагов списки SHALL различать серверный фильтр id и локальные фильтры REST3; --limit ограничивает результат, --all снимает этот предел, --max-scan ограничивает просмотр. CLI SHALL сообщать scope доступных задач, scanned count и полноту. Достигнутый scan cap, ошибка страницы или недостающие данные SHALL означать partial; полнота видимых задач не SHALL выдаваться за полноту проекта.

#### Scenario: Responsible or project filter
- **WHEN** пользователь задаёт `task:list --responsible 42 --group-id 7` без новых временных флагов
- **THEN** CLI фильтрует соответствующие selected fields локально, проверяет все страницы в установленном scan budget и не отправляет неподдерживаемый v3 filter или скрытый legacy request

#### Scenario: Scan cap reached
- **WHEN** просмотр прекращён из-за --max-scan до исчерпания доступных страниц
- **THEN** CLI возвращает partial с кодом 3, сохраняет полученные данные, указывает scanned count и причину в обычном, plain и JSON режимах

#### Scenario: Empty complete result
- **WHEN** полная разрешённая выборка не содержит совпадений
- **THEN** CLI возвращает 0, пустые данные и complete=true; пустой результат не считается ошибкой

#### Scenario: Fresh tasks are scanned first
- **WHEN** task:list запускается без --order и новых временных флагов на портале с более чем 10000 задач
- **THEN** сервер получает order {"id":"DESC"}; новые задачи просматриваются первыми, вывод сохраняет порядок страниц, --id остаётся единственным серверным фильтром

#### Scenario: Explicit list order
- **WHEN** task:list получает повторяемый --order title:asc --order id:desc без новых временных флагов
- **THEN** указанный порядок передаётся в REST3 без замены на default; если id не указан, добавляется id ASC для устойчивого порядка

#### Scenario: Incomplete duplicate check diagnostics
- **WHEN** task:list либо task:find исчерпывает --max-scan, в том числе без совпадений
- **THEN** JSON содержит meta.complete=false, reason=scan-budget-exhausted, scanned и фактический meta.order; error.code=partial-result и exit3 сохраняются; диагностика во всех форматах содержит причину, scanned, order и совет увеличить --max-scan, предупреждая, что неполный поиск не доказывает отсутствие дублей; --all не снимает budget

#### Scenario: Explicit deadline route
- **WHEN** task:list получает --deadline-window или --overdue
- **THEN** выбирается заранее объявленный REST1 tasks.task.list с серверными границами DEADLINE по task-deadline-windows; metadata показывает маршрут и интервал; strict-rest3 запрещает его до запросов; limit/all/max-scan/partial продолжают действовать

### Requirement: Actionable errors and stable exit status
CLI SHALL возвращать 0 при успехе, 1 при ошибке выполнения, 2 при неверном вводе, 3 при частичном результате, 4 при недоступном gated действии или запрещённой API политике, 130 при Ctrl-C. Ошибка SHALL объяснять действие, причину и возможное исправление без секретов. В JSON режиме error SHALL иметь стабильный code; новые коды не SHALL менять значение существующих.

#### Scenario: Missing required flag
- **WHEN** вызов task:add не содержит --responsible
- **THEN** CLI возвращает 2, называет отсутствующий флаг и показывает корректный пример без создания задачи

#### Scenario: Gated lifecycle action
- **WHEN** действие complete не прошло проверку semantics либо выбранная API политика запрещает IM route
- **THEN** CLI возвращает 4, объясняет ограничение и не выполняет fallback; --force не отменяет gate

#### Scenario: Permission or transport failure
- **WHEN** сервер отказывает в правах либо запрос завершается ошибкой
- **THEN** CLI возвращает 1, указывает операцию и безопасный следующий шаг; webhook/token и stack trace не попадают в обычный вывод

### Requirement: Controlled remote deletion and dry run
Удаление удалённого объекта SHALL требовать явного подтверждения в TTY либо --force без диалога. --force SHALL отменять только подтверждение, сохраняя validation, права и API gates. Команды записи SHALL поддерживать --dry-run: показать цель, поля и шаги без mutating requests; неизбранный provider или недоказанный маршрут SHALL оставаться gated и в dry-run.

#### Scenario: Deletion in automation
- **WHEN** пользователь вызывает `task:delete 123 --no-interaction` без --force
- **THEN** CLI возвращает 2 до записи и предлагает --dry-run для проверки или --force для явно выбранного удаления

#### Scenario: Ordinary edit
- **WHEN** пользователь явно меняет title, responsible или description
- **THEN** дополнительное подтверждение не требуется, если команда не удаляет объект и не добавляет неявных разрушительных действий

#### Scenario: Preview of an update
- **WHEN** пользователь задаёт `task:update 123 --title "Отчёт" --dry-run`
- **THEN** CLI показывает задачу, изменение и предполагаемый REST3 route; проверочные чтения допустимы и обозначены, запись не выполняется и server acceptance не заявляется

### Requirement: Predictable noninteractive and terminal behavior
CLI SHALL поддерживать нативные --no-interaction/-n без prompts, pager и иных интерактивных элементов. Диалоги допустимы только при интерактивном stdin; при отсутствии обязательного ввода CLI SHALL возвращать ошибку с нужным флагом. --no-ansi, непустой NO_COLOR, TERM=dumb и non-TTY SHALL отключать оформление соответствующего потока; JSON/plain SHALL всегда выводиться без ANSI и animations.

#### Scenario: CI invocation
- **WHEN** команда запущена с --no-interaction, redirected stdin/stdout и NO_COLOR=1
- **THEN** CLI не ждёт диалога, не запускает pager и не печатает ANSI sequences; все необходимые данные передаются флагами

#### Scenario: Quiet structured output
- **WHEN** пользователь задаёт --quiet вместе с --json
- **THEN** CLI подавляет progress и обычный stdout, включая JSON data, по нативным правилам --quiet; существенные ошибки остаются в stderr и код завершения сохраняется. Для получения JSON пользователь убирает --quiet

### Requirement: Bounded requests and honest recovery
CLI SHALL поддерживать --timeout с конечным документированным default, реагировать на Ctrl-C и прекращать новые шаги. При неизвестном результате записи CLI SHALL сообщать неопределённость и способ сверить состояние без автоматического повторения create/send/add. Для неатомарных сценариев CLI SHALL показывать выполненные и невыполненные шаги и возвращать partial при частичном выполнении.

#### Scenario: Response lost after task add
- **WHEN** соединение оборвалось после отправки task:add и результат сервера неизвестен
- **THEN** CLI возвращает ошибку с признаком outcomeUnknown и предлагает проверку состояния; повторное создание не выполняется автоматически

#### Scenario: Cancellation between workflow steps
- **WHEN** пользователь нажимает Ctrl-C после первого шага согласования, но до применения полей
- **THEN** CLI прекращает новые запросы, возвращает 130 и сообщает подтверждённые шаги и неопределённые результаты без обещания отката

### Requirement: Consistent global options and protected credentials
Общие флаги SHALL иметь одинаковые имена и значение во всех подкомандах и поддерживаться до либо после имени команды. Нечувствительные invocation flags SHALL иметь приоритет над явным config, environment и defaults. Секреты подключения не SHALL передаваться непосредственно в аргументах или выводиться в диагностике; механизм auth/storage определяется отдельно.

#### Scenario: Global option placement
- **WHEN** пользователь задаёт `b24cli --json task:show 123` либо `b24cli task:show 123 --json`
- **THEN** формат и поведение обоих вызовов совпадают

#### Scenario: Safe diagnostic information
- **WHEN** пользователь получает ошибку подключения или включает --verbose
- **THEN** CLI маскирует credentials и секретные части URL; поддержка --verbose не разрешает их раскрытие

### Requirement: Distinct checklist and item operations
CLI SHALL разделять task:checklist:add/list для корневых чек-листов и task:checklist:item:add/list/update/complete/renew/delete для пунктов. Создание пункта SHALL требовать --checklist; optional --parent SHALL принадлежать выбранному корню. CLI SHALL проверять принадлежность ID задаче и различать корень/пункт до записи. Согласование имён не SHALL отменять API gates.

#### Scenario: Create a checklist root
- **WHEN** пользователь задаёт task:checklist:add TASK_ID --title TEXT и маршрут допущен политикой
- **THEN** CLI создаёт отдельный корневой чек-лист с указанным названием, возвращает его ID и не добавляет пункт в существующий чек-лист

#### Scenario: Add an item to a selected checklist
- **WHEN** пользователь задаёт item:add TASK_ID --checklist CHECKLIST_ID --title TEXT с optional --parent ITEM_ID
- **THEN** пункт создаётся только внутри выбранного чек-листа; отсутствующий/чужой корень или parent не допускает запись и не приводит к неявному созданию другого чек-листа

#### Scenario: List roots and list items
- **WHEN** пользователь вызывает task:checklist:list TASK_ID либо task:checklist:item:list TASK_ID --checklist CHECKLIST_ID
- **THEN** первый вызов показывает корни задачи, второй — дочерние пункты выбранного корня, включая вложенные, с ID и PARENT_ID; пункты других чек-листов не смешиваются

#### Scenario: Edit a checklist item
- **WHEN** пользователь вызывает item:update/complete/renew/delete с --item ITEM_ID
- **THEN** проверяется пункт выбранной задачи; корневой ID не допускается. Update меняет только непустой title; delete сохраняет подтверждение и права, --force не отменяет gates

### Requirement: Bounded task search by title
MVP SHALL включать task:find --title TEXT: буквальная подстрока только title без регистра Unicode, trim запроса; пустой запрос отклоняется до API call. Результат SHALL всегда быть списком: таблица ID/TITLE, plain TSV id/title или JSON data.items array. Поиск SHALL соблюдать scan budget и limit/all/partial; scope, scanned/matched/returned counts, limitApplied и полнота показываются явно. Description/chat и wildcard/regex/fuzzy не SHALL участвовать.

#### Scenario: Find tasks by a title fragment
- **WHEN** пользователь вызывает b24cli task:find --title "ДОГОВОР"
- **THEN** CLI находит заголовок "Согласовать договор" и другие совпадения без учёта регистра; совпадение только в description не включает задачу, порядок обхода и результата по умолчанию — id DESC

#### Scenario: Literal title query and empty query
- **WHEN** пользователь передаёт --title "*договор*" либо пустой/пробельный --title
- **THEN** в первом случае звёздочки ищутся буквально; во втором CLI возвращает usage exit2 до запроса, не превращая поиск в выборку всех задач

#### Scenario: Search across pages within a scan budget
- **WHEN** совпадение находится после первой страницы либо исчерпан --max-scan до окончания доступной выборки
- **THEN** поиск просматривает следующие страницы в пределах budget; cap/ошибка/нет title дают partial exit3, complete=false и причину. --all не снимает scan cap; серверный title filter и legacy fallback не используются

#### Scenario: Complete empty title search and limited output
- **WHEN** просмотр всех доступных страниц не дал совпадений либо совпадений больше output limit
- **THEN** пустой complete результат возвращает exit0; при лимите CLI различает полную проверку видимой выборки и сокращённый вывод через matched/returned counts и limitApplied; прав сверх подключения не обещает

#### Scenario: Title search always returns a list
- **WHEN** task:find находит ноль, одну или несколько задач и пользователь выбирает обычный, --plain или --json формат
- **THEN** результат остаётся списком: обычная таблица ID/TITLE, plain TSV id/title без заголовка либо JSON data.items с массивом объектов id/title; при отсутствии совпадений массив пуст, а единственное совпадение не превращается в карточку или автоматически открытую задачу

#### Scenario: Fresh group task within a small scan budget
- **WHEN** без новых временных флагов среди более 10000 видимых задач самая новая задача содержит буквальный Unicode фрагмент title и относится к выбранной --group-id
- **THEN** task:find видит её на первой странице id DESC и возвращает её даже с малым --max-scan; groupId/title фильтруются локально, неполный обход сохраняет complete=false и exit3

#### Scenario: Explicit search order
- **WHEN** task:find без новых временных флагов получает повторяемый --order FIELD:asc|desc
- **THEN** поддерживается тот же allowlist и id ASC tie-breaker, что в task:list; порядок выдачи совпадает с порядком обхода, неподдерживаемое поле/направление отклоняется до API

#### Scenario: Search inside a deadline window
- **WHEN** task:find --title TEXT получает --deadline-window или --overdue
- **THEN** серверный интервал выбирается через явный REST1 маршрут по task-deadline-windows; title по-прежнему проверяется локально как буквальная Unicode подстрока, без серверного wildcard; id/title output и partial сохраняются; это объявленный маршрут, не legacy fallback

### Requirement: Consistent group identifier option
The CLI SHALL use --group-id as the only explicit group identifier option in every command that provides one. JSON and REST 3.0 fields and filters MUST remain groupId. The old --project option MUST be rejected with usage-error/exit 2 before API requests, without alias or fallback. Current help, completion and examples MUST present --group-id; migration notes MUST explain the replacement.

#### Scenario: Create or update with groupId
- **WHEN** task:add or task:update receives --group-id 42
- **THEN** its fields contain groupId 42 and existing positive integer validation and REST route semantics are preserved

#### Scenario: Group identifier list filter
- **WHEN** task:list receives --group-id 42
- **THEN** the list uses the existing groupId filter with unchanged selection, scan budget and completeness semantics

#### Scenario: Removed project option
- **WHEN** a command previously supporting --project receives --project alone or with --group-id
- **THEN** it returns usage-error/exit 2 before any API request without processing the old flag as an alias

#### Scenario: Invalid or absent group identifier
- **WHEN** --group-id is empty, nonnumeric, zero or negative, or is absent
- **THEN** invalid supplied values produce usage-error before API; absence preserves existing behavior without injecting a group field or filter

#### Scenario: Structured input compatibility
- **WHEN** advanced --fields or --fields-file contains groupId, or task:list uses a JSON groupId filter
- **THEN** existing validation, payloads and filtering remain unchanged
- **AND** mixing advanced JSON and ordinary field flags remains rejected; task:find keeps its existing title search when --group-id is omitted

### Requirement: Group-scoped title search
The CLI SHALL accept task:find --group-id as a positive integer and combine a local groupId filter for queries without new temporal options with the existing Unicode case-insensitive title substring search. Omitting the option MUST preserve existing behavior. The search MUST retain id/title output, scan budget, limit/all, visible-task scope and partial exit 3; groupId MUST NOT be sent as a REST3 server filter.

#### Scenario: Search only the selected group
- **WHEN** task:find --title "договор" --group-id 42 without new temporal options scans tasks from multiple groups
- **THEN** it returns only matching titles from groupId 42, selecting groupId locally while preserving id/title output

#### Scenario: Search without a group filter
- **WHEN** task:find receives a title without --group-id or new temporal options
- **THEN** it searches all visible tasks within the existing scan budget

#### Scenario: Invalid search group identifier
- **WHEN** task:find receives an empty, nonnumeric, zero or negative --group-id
- **THEN** it returns usage-error/exit 2 before any API request

#### Scenario: Search limits and incomplete scan
- **WHEN** a group-scoped search uses --limit, --all or reaches --max-scan before all pages are scanned
- **THEN** output limits and scan budgets remain independent and an incomplete scan returns partial/exit 3 with scanned count and reason

#### Scenario: Temporal group scope
- **WHEN** task:find --title TEXT --group-id 42 получает новый временной флаг
- **THEN** явный REST1 маршрут получает GROUP_ID=42 и диапазон DEADLINE; REST3 groupId filter не отправляется; результат нормализуется в public CLI contract
