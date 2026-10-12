# Spec Delta

## MODIFIED Requirements

### Requirement: Discoverable help without remote operations
CLI SHALL поддерживать `-h`/`--help`, `b24cli help COMMAND`, `b24cli list [NAMESPACE]` и `--version`/`-V`. Справка SHALL показывать краткое назначение, примеры обычных действий, обязательные флаги, значения по умолчанию, ограничения и ссылку на документацию. Справка и версия SHALL работать без авторизации и запросов на портал.

#### Scenario: Root or group invocation
- **WHEN** пользователь вызывает `b24cli` либо `b24cli list task`
- **THEN** CLI выполняет встроенную list и показывает доступные команды целиком либо в namespace task, возвращает 0 и не выполняет удалённых операций

#### Scenario: Command help
- **WHEN** пользователь вызывает `b24cli task:update --help` или `b24cli help task:update`
- **THEN** оба вызова показывают одну справку с примерами изменения заголовка, ответственного и описания, без требования TASK_ID

#### Scenario: Examples precede advanced details
- **WHEN** пользователь открывает task:find --help или help обычной команды задач
- **THEN** после назначения SHALL идти обычные корректные примеры, затем сведения о флагах/defaults и advanced input/API ограничениях; пример task:find SHALL объединять --title с --project и --responsible, справка не выполняет portal requests

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

#### Scenario: Ordered task card without rendering requests
- **WHEN** пользователь читает task:show TASK_ID в human режиме
- **THEN** карточка SHALL показывать доступные данные в порядке title/ID, status, responsible/project/deadline, description и link; неизвестные selected поля сохраняются после основных данных. Имя SHALL показываться с ID только при наличии в полученном результате, иначе ID; оформление не вызывает API и не выбирает незапрошенные relations

#### Scenario: Long Unicode text and terminal controls
- **WHEN** human карточка или таблица содержит длинное русское описание, заголовок, markup либо управляющие символы в узком терминале
- **THEN** CLI SHALL переносить текст по доступной ширине, сохранять читаемость Unicode, показывать markup как данные и обезвреживать управляющие последовательности; non-TTY/NO_COLOR/TERM=dumb/--no-ansi не содержат ANSI, native quiet/silent сохраняются

#### Scenario: Stable machine formats after presentation changes
- **WHEN** пользователь повторяет существующие вызовы task:show/list/find в --plain или --json
- **THEN** CLI SHALL сохранять schemaVersion 1, data/meta/error и текущие формы данных/TSV columns; human пояснения не добавляются в stdout machine форматов, а обязательная plain диагностика полноты идёт в stderr

### Requirement: Explicit filtering and completeness of lists
Списки SHALL различать серверный фильтр id и локальные фильтры REST3; --limit ограничивает результат, --all снимает этот предел, --max-scan ограничивает просмотр. CLI SHALL сообщать scope доступных задач, scanned count и полноту. Достигнутый scan cap, ошибка страницы или недостающие данные SHALL означать partial; полнота видимых задач не SHALL выдаваться за полноту проекта.

#### Scenario: Responsible or project filter
- **WHEN** пользователь задаёт `task:list --responsible 42 --project 7`
- **THEN** CLI фильтрует соответствующие selected fields локально, проверяет все страницы в установленном scan budget и не отправляет неподдерживаемый v3 filter или скрытый legacy request

#### Scenario: Scan cap reached
- **WHEN** просмотр прекращён из-за --max-scan до исчерпания доступных страниц
- **THEN** CLI возвращает partial с кодом 3, сохраняет полученные данные, указывает scanned count и причину в обычном, plain и JSON режимах

#### Scenario: Empty complete result
- **WHEN** полная разрешённая выборка не содержит совпадений
- **THEN** CLI возвращает 0, пустые данные и complete=true; пустой результат не считается ошибкой

#### Scenario: Visible complete scan with limited output
- **WHEN** task:list или task:find завершила обход видимой выборки, но returned меньше matched из-за --limit
- **THEN** human вывод SHALL явно показывать scope, returned, matched, scanned, complete=true и limitApplied=true; код остаётся 0, ограничение вывода не называется неполным обходом, размер проекта не выдумывается

#### Scenario: Readable partial explanation and next step
- **WHEN** scan cap, page failure, missing predicate data или отсутствие progress прервали task:list/find
- **THEN** CLI SHALL сохранить полученные записи и exit 3, human/plain диагностика SHALL назвать причину и следующий шаг: увеличить --max-scan для cap, повторить чтение для page failure либо проверить данные/доступ для отсутствующих полей; --all не обещает снять scan cap

#### Scenario: Complete empty selection explained to a person
- **WHEN** complete=true и matched=0 в human task:list/find
- **THEN** CLI SHALL сообщить об отсутствии совпадений в scope подключения, показать counters и вернуть 0; JSON data.items остаётся [], plain stdout не содержит поясняющей строки

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

#### Scenario: Safe concrete parser error
- **WHEN** пользователь вызывает task:find --titel договор либо задаёт отсутствующий/конфликтующий аргумент
- **THEN** CLI SHALL вернуть usage-error/exit 2 до подключения, назвать безопасное имя неверного или нужного флага/аргумента и корректный пример; если доступна однозначная suggestion, она SHALL быть подсказкой без автоматического выполнения

#### Scenario: Sensitive argument values are not echoed
- **WHEN** argv ошибочного вызова содержит webhook URL, token или произвольный текст с секретом
- **THEN** CLI SHALL строить диагностику из безопасных имён, категорий и известных примеров, без raw argv/секретных значений/stack trace; JSON сохраняет code usage-error, остальные error codes и exit status не переопределяются

### Requirement: Bounded task search by title
MVP SHALL включать task:find --title TEXT: буквальная подстрока только title без регистра Unicode, trim запроса; пустой запрос отклоняется до API call. Результат SHALL всегда быть списком: таблица ID/TITLE, plain TSV id/title или JSON data.items array. Поиск SHALL соблюдать scan budget и limit/all/partial; scope, scanned/matched/returned counts, limitApplied и полнота показываются явно. Description/chat и wildcard/regex/fuzzy не SHALL участвовать.

#### Scenario: Find tasks by a title fragment
- **WHEN** пользователь вызывает b24cli task:find --title "ДОГОВОР"
- **THEN** CLI находит заголовок "Согласовать договор" и другие совпадения без учёта регистра; совпадение только в description не включает задачу, порядок результата — id ASC

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

#### Scenario: Title and convenient filters compose with AND
- **WHEN** пользователь вызывает task:find --title "договор" --project 7 --responsible 42 либо добавляет --status/--due-before/повторяемый --id
- **THEN** CLI SHALL принять фильтры с validation и семантикой task:list и вернуть только задачи, удовлетворяющие title и всем критериям; --id использует документированный серверный id filter, остальные unsupported server predicates проверяются локально на всех просмотренных страницах

#### Scenario: Advanced search filters preserve conflicts and explicit stdin
- **WHEN** task:find объединяет --title с --where JSON либо --where-file PATH/- или пользователь смешивает where с convenient predicate flags
- **THEN** title SHALL применяться через AND с валидированными equality predicates; conflicting where/convenient flags и несколько stdin consumers SHALL дать usage-error/exit 2 без API, файл/stdin не читаются неявно

#### Scenario: Predicate fields do not change search result shape
- **WHEN** combined search выбирает дополнительные поля для проверки project/responsible/status/deadline, а поле отсутствует либо совпадение находится после первой страницы
- **THEN** CLI SHALL проверить следующие страницы в budget, отсутствие обязательного predicate field SHALL означать partial/exit 3; результат SHALL оставаться id/title array в JSON, двумя TSV columns или таблицей ID/TITLE, без auto-select и новых API routes
