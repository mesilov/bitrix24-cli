## MODIFIED Requirements

### Requirement: Explicit filtering and completeness of lists
Без новых временных флагов списки SHALL передавать на сервер id, а при task-v3 также группу, исполнителя, постановщика и документированный статус через REST1 tasks.task.list; остальные условия SHALL проверяться локально. Маршрут SHALL выбираться до запросов и показываться в meta. --limit ограничивает результат, --all снимает этот предел, --max-scan ограничивает просмотр. CLI SHALL сообщать scope, scanned и полноту; cap, ошибка страницы или недостающие данные SHALL означать partial.

#### Scenario: Responsible or project filter
- **WHEN** пользователь задаёт `task:list --responsible 42 --group-id 7` без новых временных флагов при task-v3
- **THEN** CLI отправляет REST1 tasks.task.list с filter GROUP_ID=7 и RESPONSIBLE_ID=42, просматривает только серверную выборку в scan budget, не вызывает REST3 tasks.task.list и tasks.task.get для проверки группы; meta.listRoute содержит apiVersion 1.0 и serverFilter [groupId, responsibleId]

#### Scenario: Large portal with a small project
- **WHEN** на портале больше 10000 видимых задач, а в группе 1096 их три и ни в одной текущий пользователь не ответственный
- **THEN** `task:list --role responsible --group-id 1096 --all` возвращает data.items=[], complete=true и exit 0 после серверной выборки, а не scan-budget-exhausted

#### Scenario: Unsupported fields keep the local route
- **WHEN** серверные условия сочетаются с --select, --order или --where по полю, которого нет в REST1 списке
- **THEN** CLI до запросов выбирает REST3 маршрут с локальной фильтрацией текущей семантики и указывает его в meta.listRoute с причиной; маршрут не меняется после ошибки запроса

#### Scenario: Remaining local predicates on the server route
- **WHEN** серверные условия сочетаются с --due-before, --title (task:find), --status declined или другими поддерживаемыми полями --where
- **THEN** эти условия проверяются локально по строкам серверной выборки через AND; формы data.items, TSV колонки и значения status/groupId/responsibleId совпадают с REST3 маршрутом

#### Scenario: Status filtered by the server
- **WHEN** `task:list --status in_progress` или where status со значением pending, in_progress, supposedly_completed, completed либо deferred выполняется без новых временных флагов при task-v3
- **THEN** CLI отправляет REST1 tasks.task.list с filter REAL_STATUS равным коду 2, 3, 4, 5 или 6 соответственно, не вызывает REST3 tasks.task.list; meta.listRoute содержит apiVersion 1.0 и serverFilter со status; статус каждой строки дополнительно проверяется локально, и строка с другим или неизвестным статусом не добавляется к совпадениям

#### Scenario: Undocumented status value stays local
- **WHEN** запрошен --status declined без других серверных условий при task-v3
- **THEN** REAL_STATUS не отправляется, CLI выбирает REST3 маршрут с локальной проверкой статуса и reason no-server-predicate; вместе с другими серверными условиями declined проверяется локально на REST1 маршруте

#### Scenario: Strict policy keeps local filtering
- **WHEN** `task:list --responsible 42 --group-id 7` выполняется при strict-rest3
- **THEN** CLI сохраняет REST3 маршрут с локальной фильтрацией и scan budget, meta.listRoute показывает apiVersion 3.0 без серверных условий кроме id; policy-denied не возвращается

#### Scenario: Scan cap reached
- **WHEN** просмотр прекращён из-за --max-scan до исчерпания доступных страниц
- **THEN** CLI возвращает partial с кодом 3, сохраняет полученные данные, указывает scanned count и причину в обычном, plain и JSON режимах

#### Scenario: Empty complete result
- **WHEN** полная разрешённая выборка не содержит совпадений
- **THEN** CLI возвращает 0, пустые данные и complete=true; пустой результат не считается ошибкой

#### Scenario: Fresh tasks are scanned first
- **WHEN** task:list запускается без --order и новых временных флагов на портале с более чем 10000 задач
- **THEN** сервер получает order {"id":"DESC"} на выбранном маршруте; новые задачи просматриваются первыми, вывод сохраняет порядок страниц; без группы, исполнителя и постановщика --id остаётся единственным серверным фильтром

#### Scenario: Explicit list order
- **WHEN** task:list получает повторяемый --order title:asc --order id:desc без новых временных флагов
- **THEN** указанный порядок передаётся выбранному маршруту без замены на default; если id не указан, добавляется id ASC для устойчивого порядка

#### Scenario: Incomplete duplicate check diagnostics
- **WHEN** task:list либо task:find исчерпывает --max-scan, в том числе без совпадений
- **THEN** JSON содержит meta.complete=false, reason=scan-budget-exhausted, scanned и фактический meta.order; error.code=partial-result и exit3 сохраняются; диагностика во всех форматах содержит причину, scanned, order и совет увеличить --max-scan; task:find предупреждает, что неполный поиск не доказывает отсутствие дублей, а task:list — что он не доказывает отсутствие подходящих задач; --all не снимает budget

#### Scenario: Explicit deadline route
- **WHEN** task:list получает --deadline-window или --overdue
- **THEN** выбирается заранее объявленный REST1 tasks.task.list с серверными границами DEADLINE по task-deadline-windows и теми же серверными условиями группы, исполнителя, постановщика и статуса, что и без временных флагов; metadata показывает маршрут, интервал и meta.listRoute с apiVersion 1.0 и serverFilter; strict-rest3 запрещает его до запросов; limit/all/max-scan/partial продолжают действовать

#### Scenario: Visible complete scan with limited output
- **WHEN** task:list или task:find завершила обход видимой выборки, но returned меньше matched из-за --limit
- **THEN** human вывод SHALL явно показывать scope, returned, matched, scanned, complete=true и limitApplied=true; код остаётся 0, ограничение вывода не называется неполным обходом, размер проекта не выдумывается

#### Scenario: Readable partial explanation and next step
- **WHEN** scan cap, page failure, missing predicate data или отсутствие progress прервали task:list/find
- **THEN** CLI SHALL сохранить полученные записи и exit 3, human/plain диагностика SHALL назвать причину и следующий шаг: увеличить --max-scan для cap, повторить чтение для page failure либо проверить данные/доступ для отсутствующих полей; --all не обещает снять scan cap

#### Scenario: Complete empty selection explained to a person
- **WHEN** complete=true и matched=0 в human task:list/find
- **THEN** CLI SHALL сообщить об отсутствии совпадений в scope подключения, показать counters и вернуть 0; JSON data.items остаётся [], plain stdout не содержит поясняющей строки

#### Scenario: Failure boundaries and current partial reasons
- **WHEN** task:list/find получает ошибку первой страницы, Ctrl-C либо после чтения части выборки page-error/role-read-error/missing-id/missing-predicate-field/pagination-no-progress
- **THEN** CLI SHALL сохранять execution error/exit1 для первой страницы и interrupted/exit130 для Ctrl-C; остальные неполные результаты SHALL сохранять exit3, scope/counters/order, доступные данные и конкретный следующий шаг по причине; неполная выборка не доказывает отсутствие дублей

#### Scenario: Completeness summaries obey streams and verbosity
- **WHEN** task:list/find выводит summary в human/plain/JSON при обычной verbosity, --quiet или --silent
- **THEN** human SHALL показывать summary рядом с таблицей, plain SHALL сохранять только TSV в stdout и отправлять summary в stderr, JSON SHALL сохранять один envelope без декоративных строк; --quiet SHALL подавлять обычные summaries/stdout, сохранять существенную partial диагностику в stderr, --silent SHALL подавлять весь вывод при неизменном exit status

## ADDED Requirements

### Requirement: Visible local scan in stderr
Когда task:list или task:find обходит видимые задачи по REST3 маршруту без явного --id, CLI SHALL до первого запроса сообщить в stderr о локальном обходе, его причине и бюджете --max-scan, а во время обхода SHALL сообщать число просмотренных задач. Сообщения MUST NOT попадать в stdout и MUST NOT менять JSON-конверт.

#### Scenario: Warning before a local scan
- **WHEN** `task:list --status declined --limit 5` выполняется без других серверных условий при task-v3
- **THEN** stderr до первого запроса содержит строку о локальном обходе с причиной no-server-predicate, бюджетом 10000 и советом добавить серверный критерий (--group-id, --responsible, --role responsible|creator, --status или временное окно); stdout содержит только результат

#### Scenario: Progress during a long local scan
- **WHEN** локальный обход просматривает больше 1000 задач
- **THEN** stderr получает строку прогресса после каждых 1000 просмотренных задач с числом просмотренных задач, совпадений и бюджетом; итоговые meta.scanned и meta.matched не меняются

#### Scenario: No warning on the server route or for explicit IDs
- **WHEN** команда идёт серверным маршрутом REST1 либо REST3 выборка ограничена явным --id
- **THEN** предупреждение и прогресс локального обхода не выводятся

#### Scenario: Quiet and silent modes
- **WHEN** локальный обход выполняется с --quiet или --silent
- **THEN** предупреждение и прогресс не выводятся; partial диагностика и коды завершения сохраняют текущее поведение
