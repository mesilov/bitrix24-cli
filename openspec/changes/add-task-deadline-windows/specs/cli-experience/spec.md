# Spec Delta

## MODIFIED Requirements

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

