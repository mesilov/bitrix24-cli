# Design

## Context

См. proposal.md — Why. На dev `2b24edd` зарегистрированы 42 task-команды, включая семь lifecycle команд из #60 и task:my из #118; исходный planning snapshot `5ed0349` содержал 34. Канонический task-console-runtime пока описывает 34, а доставленные deltas add-task-lifecycle/add-task-my-command — 41/42: при реализации/архивировании сохранять обе добавки и существующий lifecycle integration scenario. HistoryScanner обходит TaskHistoryGateway с budget 10000, дедуплицирует по ID, сортирует createdDate/ID и сохраняет неполноту; Rest1TaskHistoryAdapter нормализует `value.from/to` в `from/to`. ListTaskHistoryCommandTest проверяет TITLE event, не status lifecycle. ResultPresenter имеет kind task-history с TSV id/createdDate/userId/field/from/to; TaskHumanRenderer содержит имена REST3 статусов.

Официальная [история](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-history-list.html) описывает REAL_STATUS и STATUS, но не гарантирует их эквивалентность/отсутствие дублей или атомарный snapshot. [Поля REST3](https://apidocs.bitrix24.ru/api-reference/tasks/fields-rest-v3.html), проверенные MCP search/article-details 14.10.2026: status — строковый lifecycle; statusChanged — datetime|null; created отсутствует в tasks.task.get (возвращается list), changed — дата общего изменения. Не планировать select created в get. Параметры get имеют id/select в REST3; taskId относится к REST1, версии не смешиваются.

## Goals / Non-Goals

**Goals:** чистый повторяемый калькулятор, evidence на каждой границе, проверка полноты до распределения времени, сохранение действующего history:list/public error contract.

**Non-Goals:** менять API statuses, извлекать даты из произвольного чата, моделировать рабочие часы, получать атомарную историю из API без такого контракта. Изменение состояния задачи для расчёта запрещено; мутации допустимы лишь в отдельных disposable fixtures тестов.

## Decisions

### 1. Отдельная команда и калькулятор

Предложенные StatusDurationsTaskCommand/StatusDurationsTaskHandler/StatusDurationsTaskRequest вызывают шлюзы через PreparedOperation/CommandRunner; не запускают task:history:list или task:show как вложенные CLI-команды. Чистый StatusDurationCalculator получает нормализованные события, completeness reasons, snapshot evidence и единую границу asOf. Это позволяет проверять арифметику фиксированными timestamps независимо от сети. Альтернатива — менять history:list — нарушила бы существующий контракт события/TSV.

Все потенциальные маршруты объявлены заранее: REST1 tasks.task.history.list и REST3 tasks.task.get/tasks.task.list. Сканировать полную доступную историю без FIELD-фильтра: нужны NEW и проверка смешанных событий. Текущие scanner/gateway/adapter переиспользуются, однако статусному расчёту нужен внутренний evidence collection result: исходные offsets, конфликтующие дубликаты и сырые значения нельзя потерять при дедупликации/сортировке. Дополнение внутренних структур не меняет публичный history:list; regression tests подтверждают это. Не ослаблять current navigation gate и budget ради exit0.

### 2. Статусы, создание и доказательства

REAL_STATUS — основной поток; явное отображение кодов истории в REST3 lifecycle проверяется на портале и хранится вместе с raw values. Подтверждённый положительный numeric STATUS subset 2/3/4/5 разрешён при отсутствии REAL_STATUS; неизвестные/мета-коды и неподтверждённые string aliases дают partial. Если оба поля описывают один подтверждённый переход, сохраняются оба evidence ID, начисление одно. STAGE_ID/мета-статусы исключены.

Для начала использовать стабильный REST3 list.created и from первого перехода только при полной непротиворечивой истории; pure calculator также поддерживает уникальный NEW как отдельно предоставленное доказательство. При отсутствии первого перехода требуется отдельное доказательство начального статуса; сегодняшний status сам по себе его не доказывает. Если доказательства нет, leading boundary unknown и итог за всю жизнь null. Маршрут list читает id/created до/после истории; несовпадение ID или created не позволяет отсечь старые события и даёт partial. Альтернатива — всегда предполагать pending/создание по первому событию — создаёт выдуманные интервалы.

### 3. Временная ось и повторные открытия

DateTimeImmutable timestamps сравниваются как абсолютные instants; исходные ISO strings сохраняются для evidence. Сортировка по timestamp, для равных меток — только доказанный event order/единственная согласованная chain; порядок по ID без подтверждения API не обещается. from=to — no-op; переходы в одну секунду могут создавать интервалы нулевой длительности при доказанном порядке. Противоречие разбивает цепочку и делает exact totals неизвестными.

Каждый переход закрывает [enteredAt,eventAt) и открывает новый интервал; completed/deferred/declined обрабатываются наравне с другими статусами. Повторное открытие продолжает общий обход. Суммы по статусу складывают все подтверждённые интервалы, а не разницу первого/последнего входа.

Фиксированный пример полного доказанного ряда (секунды от создания): pending@0 → in_progress@10 → completed@30 → pending@40 → in_progress@50 → completed@80 → pending@90 → completed@100, asOf=120. Итоги: pending=30, in_progress=50, completed=40, всего=120. NEW/initial evidence и полнота обязательны, иначе это только unit fixture, не пример portal proof.

### 4. asOf и проверка конкурентных изменений

Зафиксировать now до API; default asOf=now, явный timezone-bearing asOf<=now. После чтений get(id, select=[status,statusChanged,changed]) сравнить доступное состояние с контрольным get до обхода и концом полной цепочки. Исторический asOf определяется историей, не текущей карточкой. События после asOf помогают проверить цепочку/состояние, но время начисляется только до asOf; при asOf до NEW — usage-error.

Изменение контрольных полей, расхождение chain/readback, отсутствие нужного состояния или сомнительная граница истории → partial. Стабильные два чтения — ограниченная проверка гонки, не транзакционный snapshot: receipt сохраняет collectedAt/route/source/reasons. Не выполнять скрытые retries и не пытаться исправить задачу. Альтернатива — брать now после обхода — добавила бы неподтверждённое время хвоста.

### 5. Строгая семантика неполноты

Source complete=false нельзя улучшать до true по короткой странице, пустому ответу неизвестного доступа, совпадению from/to или statusChanged. Source true означает доказанный обход либо отдельно подтверждённый полный controlled-action scope; exact result true требует creation/initial state, валидные dates/status chain и tail evidence.

Сохранять наблюдаемые transitions и интервальные границы. При partial `seconds=null`, `verified=false`, exact totals null. `elapsedBetweenEventsSeconds` показывает лишь геометрическую разницу известных меток, без гарантии непрерывного пребывания в статусе и без трактовки как lower bound: пропущенные reopen циклы могут произвольно изменить распределение. Неизвестное начало/конец не заменяются нулями. Консервативно при любом отсутствии доказательства полных lifetime totals ни один агрегат не показывается как точный; отдельные доказанные интервалы могут сохранять verified=true, если полнота именно их диапазона независимо подтверждена.

JSON envelope data: taskId/asOf/intervals/totals/observedTransitions/unknownBoundaries; meta: complete/historyComplete/reasons/collectedAt/actual routes. Human: таблицы интервалов и сумм с «неизвестно»; plain: детерминированные строки интервалов с status/enteredAt/leftAt/seconds/verified/evidenceIds. Причины включают исходную navigation/page/budget причину; не дублировать ошибку как успех. Невозможность прочитать что-либо сохраняет error exit1; с полезными данными partial exit3; cancellation exit130.

### 6. Проверки, inventory и документация

Unit: фиксированный reopening пример, conservation total, no-op, дубли, offset/DST (секунды instants), равные даты, conflict IDs/chain, неизвестные коды, отсутствующее начало/хвост, partial source с согласованной цепочкой, historical asOf и race. Дополнить runtime contracts/formatter/profile/API-policy/deferred-validation tests.

Отдельный tests/Integration/Task/StatusDurationsTaskCommandTest.php, Group command:task:status:durations, собственный happy-path через production provider. SDK создаёт/переводит/повторно открывает task fixture и читает историю/состояние, test invokes только новую команду. Проверка exact exit0 возможна только при независимом полном status evidence; документированная navigation partial проверяется отдельным case и receipt. Skip/partial не зачёркивают exact acceptance. Cleanup собственной fixture обязателен.

Регистрация: baseline42 + новая=43; уже доставленная #118 task:my сохраняется. Перед apply/merge перепроверить актуальные specs/CommandCatalog и согласовать число/полный блок регистрации, сохранив все уже доставленные команды и lifecycle integration scenario. Текущее число — снимок планирования, не повод удалить parallel command. В реализации обновить русскую справку, README, CLI map, CHANGELOG и docs/task-jtbd.md для PM-02/AM-03 с прежними ID, едиными таблицами, filename теста и датой/sourceSHA. Это добавляет средство анализа к существующим JTBD, не доказывает весь PM-02/AM-03.

## Risks / Trade-offs

- Неподтверждённая live navigation → команда часто возвращает partial; не обходить completeness gate ради красивых сумм, сохранить конкретное ограничение.
- История может смешивать числовые и строковые коды/status events → обязательный live receipt mapping; неизвестное значение даёт partial.
- Нет NEW/история усечена → неизвестный начальный интервал; не обещать lifetime totals.
- Гонки и timestamps с точностью до секунды → проверять snapshots/chain, показывать неоднозначность; атомарность не заявлять.
- Чтение полной истории дороже FIELD-filter → ограниченный существующий budget, причины неполноты сохраняются; массовый проектный обход не входит в change.

## Migration Plan

Аддитивная команда без persisted schema/dependencies. Поставить через issue MR в dev после verify; откат удаляет новую регистрацию/команду, не изменяя задачи или историю. После подтверждённой реализации и merge архивировать change и обновить путь в issue по maintainer workflow. Этот план публикуется draft PR и не запускает реализацию.

## Live constraints recorded during apply

See live-contract-check.md: STATUS-only numeric 2/3/5 is observable but equivalence to REAL_STATUS remains unverified. Canonical STATUS-only reports remain partial. Multiple NEW/DELETE boundaries are ambiguous. Current dev 2b24edd includes task:my and 42 task commands; adding this command gives 43. Exact live acceptance remains open if navigation cannot prove completeness.

## Follow-up: rejection and successful review cycle

User requested a live task lifecycle simulation followed by inspection of its history. Add a separate test in StatusDurationsTaskCommandTest: controlled SDK fixture, start/complete/disapprove/start/complete/approve, REST3 readback and REST1 history after each action. Keep baseline and incremental event IDs, timestamps/offsets and raw fields/values in a sanitized receipt. Do not infer global completeness from incremental captures; delete only the owned fixture. Existing controlledTask helper supplies separate creator/executor roles from configured test credentials/user IDs. Exact duration acceptance remains independent. Official MCP search/details answered for start/complete/disapprove/approve; approve was not returned by search but exact method-details resolved its current contract at https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-approve.html.

## Follow-up: root cause and completeness investigation

User requests investigation of old NEW/DELETE and a defensible exact calculation. Compare SDK raw HTTP body to SDK DTO/adapter, task IDs and creation dates, separate active fixtures and deleted/nonexistent IDs. Exercise start/filter/order and a controlled >50-event history; record complete capture only for the known fixture, not an inferred global guarantee. Research official endpoint/navigation contract via required MCP and public official sources. Preserve prior partial receipts. No production completeness gate may be loosened based on page size/chain consistency alone.

## Follow-up: five-second lifecycle output example

Add a separate integration test reusing the controlled review-cycle helper with 5-second pauses before each of start/complete/disapprove/start/complete/approve and before the final report asOf. Measure actual pauses with a monotonic clock; server event timestamps also include API/readback latency and need not differ by exactly five seconds. Retain the complete CLI result, raw incremental history and snapshots in a separate sanitized receipt. The existing faster scenario keeps its separate receipt. Production arithmetic/completeness gates are unchanged.

## Follow-up: current task lifetime and controlled exact sums

The user requests correct sums for the fixture whose actions we recorded. The CLI does not receive that independently captured action journal. Implement current-lifetime scope in production using two REST3 tasks.task.list reads filtered by exact id, select id/created, limit 2. Validate identity and unchanged strict timezone-aware created; get cannot select created. Declare this route in advance. Events strictly before proven created are excluded from calculation but retained in excludedHistoryEvidence; events at created, malformed dates, current DELETE and extra current NEW remain subject to integrity checks. Initial occupancy starts at actual created, even if NEW is logged later. Preserve all raw lifecycle historyEvidence.

Accept only the positive numeric STATUS values 2/3/4/5 confirmed by REST1 status documentation and independent live REST3 readbacks. Official tasks.task.list describes STATUS as REAL_STATUS plus negative meta codes; negative/unknown codes and unverified string STATUS aliases remain partial. REAL_STATUS still has priority; matching aliases add provenance without a second transition. Source/navigation/page/budget gates are unchanged.

For controlled live arithmetic, pass actual created readbacks to the same pure calculator. Independently verify that current-lifetime event IDs equal the initial current NEW plus all action-by-action captured status events, each action's before/after status and timestamp, and no unexpected status events. Then the test can supply historyComplete=true for that explicitly controlled scope, compare totals against independent interval arithmetic, and mark metadata coverage=controlled-fixture-actions. Do not inject invented total/navigation into the live transport or present this as successful production CLI completeness. The CLI report and controlled exact result are retained separately; ordinary history can still be partial. Exact CLI acceptance 3.3 stays open.

## Follow-up: long status-history acceptance probe

Run eight controlled review cycles with five-second pauses: start/complete/disapprove/start/complete/approve, then renew between cycles. This gives 55 STATUS transitions and seven returns from completed, plus eight rejections. Retain checkpointed action-by-action state/history and a separate final receipt, proving whether the history API truncates status rows at the generic fifty-row boundary. No kanban STAGE_ID mutations are inferred from the user's reference to stages; the scenario continues the established task lifecycle. Continue independent completeness research while the bounded fixture runs.

## Corrected REST1 exhaustion contract

Official required MCP article-details and https://apidocs.bitrix24.ru/settings/how-to-call-rest-api/list-methods-pecularities.html explicitly define absent next as the last page and total as optional when a method counts rows. The prior adapter incorrectly required total even on the terminal response. Correct history Page completeness to follow next until absent, reject contradictions with any supplied total, malformed list shapes, non-progress and budget exhaustion. This applies to history:list too: its shape/deduplication stay unchanged while the incorrect terminal partial becomes complete. Do not infer termination from row count (the real endpoint returns >50 status records); use the documented protocol. Source complete means the authenticated API collection was exhausted under its contract, not an assertion that server-side retention/permissions cannot exist. Stable current created/chain/tail and other calculator integrity gates remain mandatory.

Exact ordinary CLI acceptance uses independent readback arithmetic over two reopenings and the long 55-transition fixture, without injected pagination or controlled-only source assertions. Add a concurrent-change live partial case, real no-transition pending acceptance, and counter/budget/error unit cases. Preserve historical partial receipts and append the protocol correction; the earlier external blocker about absent next is superseded by the official exhaustion rule.
