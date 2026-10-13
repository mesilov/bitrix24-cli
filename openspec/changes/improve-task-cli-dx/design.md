# Design

## Context

Мотивация и release scope — в [proposal](proposal.md), наблюдаемое поведение — в [дельте cli-experience](specs/cli-experience/spec.md). База сверена 2026-10-13: origin/dev `0aeb25b68dad641be57e6356222373d4fc9782d3`, milestone 0.2.0. PR #11 и planning PR #13 уже merged. ResultPresenter по-прежнему сортирует task-card по API-ключам и сериализует relations как JSON; B24Application заменяет parser context общей фразой. FindTaskCommand уже имеет --group-id, --role, --order, но не предоставляет остальные фильтры task:list; общий src/Console/TaskInputMapper.php отключает их в ветви find, хотя TaskQuery/TaskListScanner уже поддерживают predicates, dueBefore, роли и order.

Существующий чистый worktree /private/tmp/bitrix24-cli-issue-12 обновлён fast-forward к этой dev; основной dirty checkout сохранён. Canonical cli-experience уже содержит recent-first/group-scoped search и explicit order; MODIFIED blocks должны сохранить каждый её сценарий. Профили, users, роли, results, lifecycle и local upload уже входят в dev и сохраняются независимо от O1–O3.

Воспроизведение на FixturePortal/FakeApiTransport: task:show 123 выдаёт field/value с responsible/group JSON; task:find --titel договор возвращает общую usage-error/exit2 и 0 calls; --title договор --group-id 7 --responsible 42 также отклоняется с exit2/0 calls. Для task:list --limit 1 при трёх совпадениях human выводит одну строку без summary, JSON показывает scanned=matched=3/returned=1/complete=true/limitApplied=true и order id DESC. Это offline evidence, без live portal proof.

## Goals / Non-Goals

**Goals:**

- Human formatting и parser diagnostics остаются отдельным слоем над неизменным application result и transport.
- Search использует общие query validation/predicates и scanner, без отдельного search backend.
- JSON/TSV, HTTP маршруты и права подключения сохраняют прежнее значение; вывод делает полноту заметной.

**Non-Goals:**

- Новая функциональность O4–O8 из issue #12, новые API exceptions, silent fallback, TUI и implicit personal scope. Уже действующие auth profiles, роли, lifecycle, results и local upload не переопределяются; общий task:update не получает status write.
- Получение дополнительных names/relations для улучшения оформления: renderer использует только уже доступные данные. Изменение portal enrichment в PR #11 принимается как отдельная база, не копируется сюда.
- Перенос Rust libraries в PHP или установка новой библиотеки ради сходства с Linear CLI.

## Decisions

### 1. Уточнить существующую cli-experience

Копируем пять requirement blocks целиком из актуальной canonical cli-experience и добавляем DX scenarios: help, human output, visible completeness, errors и bounded title search. Сохраняем Fresh tasks are scanned first, Explicit list order, Incomplete duplicate check diagnostics, Fresh group task within a small scan budget и Explicit search order. В task-role-filtering целиком сохраняем block Role filters compose with existing query criteria, уточняя новые критерии find и устаревший сценарий Role and title search. Другие ролевые blocks, task-results, task-workflows и task-file-upload не изменяются. Альтернатива — новая общая DX capability — рассредоточила бы контракт stdout/filter/error между двумя местами.

### 2. Pure human presentation поверх существующего result

ResultPresenter выбирает human карточку для task-card и сохраняет JSON/plain ветви. Основные поля идут в semantic order, остальные selected поля — после них. Names отображаются из уже нормализованного responsible/group result с ID; отсутствующий name означает ID fallback. Default FieldSchema::CARD запрашивает responsible.id/group.id, а не имена: не расширяем select ради оформления. Explicit --select заменяет default projection: примеры с именами включают id/title и responsible.id/responsible.name/group.id/group.name; не подразумеваем их добавление renderer. Неизвестный status отображается raw; известный документированный enum имеет читаемую подпись рядом с raw value. Deadline сохраняет явный offset, без выдумывания timezone или срока. Link отображается только при наличии допустимого значения в результате.

Ширина вывода учитывает доступную ширину терминала и Unicode display width. Длинный title/description переносится; полное описание сохраняется. При отсутствии терминальной ширины действует конечный documentable default. Данные задачи не исполняются как Symfony markup; escape/control handling предотвращают terminal sequences. Нативные NO_COLOR/non-TTY/quiet/silent правила применяются ко всем новым строкам.

Используем существующие Symfony Console Table, OutputFormatter и terminal width utilities. Отдельные pure helpers допустимы при необходимости, без внедрения network ports в presenter. Альтернативы: оставить API key/value для human либо изменить application object ради оформления — обе увеличивают зависимость человека/скриптов от внутренних деталей.

### 3. Completeness summary не меняет данные или exit status

Для task-list/title-matches читаем существующие scope/returned/matched/scanned/complete/limitApplied/reason/order и roleFilter при наличии. Human summary идёт рядом с результатом; summary полноты plain идёт в stderr и подчиняется native verbosity, TSV stdout остаётся прежним. Существенная partial диагностика остаётся видимой при quiet, silent подавляет весь вывод. JSON получает только действующий envelope/meta, без декоративных строк; дополнительные predicate fields не становятся output columns.

Различаем complete scan + limitApplied (exit 0), complete empty (exit 0) и incomplete scan (exit 3). Для scan-budget-exhausted сохраняем scanned/order и совет --max-scan, предупреждение о недоказанном отсутствии дублей; для page-error — повторное чтение, для missing-id/missing-predicate-field — проверку данных/доступа, для pagination-no-progress — повторное чтение с проверкой пагинации, для role-read-error — проверку чтения участников и прав. Ошибка первой страницы остаётся execution error/exit1, Ctrl-C — exit130; их не превращаем в partial. --all не снимает scan cap. Не печатаем total проекта, которого API не доказал. Альтернатива — скрывать counters в JSON — оставляет человека без информации о границах выборки.

### 4. Parser context строится из безопасных metadata

B24Application сохраняет общую error boundary и usage-error/exit 2, но различает unknown option, missing argument/option value и конфликт. Формируем сообщения из известных имён command/options и статических примеров, а не копируем raw exception/argv. Неизвестное имя проходит sanitization; произвольные значения не отражаются. Действующие application Failure сохраняют стабильные codes/details и masking.

Suggestions — только текст: известная однозначная команда/опция либо направление к help/list при неоднозначности. Ни correction execution, ни prompt/network до локальной validation не добавляются. Help task commands начинается с обычных примеров, затем flags/defaults, advanced usage и ограничения; root list/version остаются credential-free. Альтернатива raw parser getMessage раскрывает ввод и не гарантирует безопасную диагностику.

### 5. Shared query contract для title AND filters

FindTaskCommand получает повторяемый --id, --responsible, --status, --due-before и --where/--where-file. Уже действующие --group-id/--role/--order сохраняются; --project отклоняется без alias. --title остаётся обязательным trim literal Unicode substring. Общий TaskInputMapper собирает TaskQuery с validation и conflicts task:list: where/where-file взаимоисключающие; where нельзя смешивать с --responsible/--group-id/--status/--due-before, но можно с обязательным --title, --id и явным --role. Source reader сохраняет явный stdin и single-consumer rule; дополнительный потребитель stdin для find не добавляется.

TaskListScanner уже проверяет title и predicates через AND; используем этот механизм, включая выбор необходимых predicate fields и id server filter. groupId/responsibleId/status/deadline/title продолжают локальный scan: narrowing scope не является обещанием меньшего числа API calls. --role responsible/creator сохраняет local predicate относительно владельца выбранного webhook; accomplice/auditor сохраняет существующее явное REST1 чтение участников после остальных критериев, запрещённое при strict-rest3. Новые fallback/REST1 routes не добавляются. Missing predicate fields, ошибка следующей страницы, role-read-error или cap сохраняют partial.

Выход find содержит только id/title. Default обхода и результата — id DESC; существующий повторяемый --order сохраняется с allowlist FieldSchema::SORTABLE и id ASC tie-breaker при отсутствии id. Default limit=50/max-scan=10000, --all снимает только output limit; scope explicit-task-ids используется при --id, иначе tasks-visible-to-connection. Нужные для predicates поля не расширяют data.items/TSV. Новый --select или search в description/chat не добавляется. Альтернатива отдельного JSON filtering после команды заставляет пользователя собирать запрос вручную и теряет единый completeness contract.

Примеры будущего combined search:

```sh
b24cli task:find --title "договор" --group-id 7 --responsible 42
b24cli task:find --title "договор" --status in_progress --order id:desc --limit 25
b24cli task:find --title "договор" --where-file filters.json --role responsible --all --json
b24cli task:find --title "договор" --id 123 --id 124 --due-before 2026-10-15T18:00:00+06:00 --json
```

## Official API evidence and limits

- 2026-10-13: effective config проверен через codex mcp get bitrix24_dev --json; выполнены реальные bitrix-search и bitrix-method-details(tasks.task.list), bitrix-article-details("Поля задачи в REST 3.0"). MCP доступен; токены/вебхуки не передавались.
- [Поля REST3](https://apidocs.bitrix24.ru/api-reference/tasks/fields-rest-v3.html): responsibleId/groupId — integer в list, responsible/group — связанные объекты get; status — string enum, deadline — datetime/null. API keys не переименовываются для human labels.
- [tasks.task.list REST3](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-list-rest-v3.html): filter документирован только для id, order — ASC/DESC, pagination — offset/limit. MCP method summary отдаёт устаревший URL /rest-v3/tasks/tasks-task-list.html и смешанные legacy параметры; точные ограничения повторно проверены на действующей официальной странице. Дополнительные server filters не выводятся из summary.
- Это проверка документации и offline fixtures, без live portal вызовов, доказательства прав или universal runtime support.

## Risks / Trade-offs

- Dev продолжает меняться → перед apply повторно сверяем сохранённый base SHA, canonical scenarios, renderer/query и issue milestone; при конфликте согласуем change до code edits.
- Narrow human layout теряет значения → wrapping и сохранение дополнительных selected fields проверяются на длинном русском тексте и вложенных данных; JSON/plain остаются источником стабильной структуры.
- Более подробная ошибка отражает секрет → whitelist metadata/static examples, фикстуры с secret-bearing argv и отсутствие raw exception output.
- Сводка путает scan completeness с output limit → отдельные complete/limitApplied messages и tests для complete empty, limited complete и capped partial.
- Локальные фильтры сканируют множество задач → сохраняются finite --max-scan и явный scope; server optimizations остаются отдельным API change.
- Отличается human оформление → это ожидаемое изменение; machine contracts и native stream/verbosity behavior проверяются отдельно.

## Migration Plan

1. Использовать уже принятую dev базу и этот согласованный план; перед apply обновить отдельную issue ветку от актуальной dev без перезаписи пользовательских изменений.
2. Реализовать O1–O3 и проверки, обновить README/help и ссылки на issue/change.
3. Выполнить make test, make check, make lint-all и strict OpenSpec validation, отправить implementation PR в dev. Live portal acceptance #42 остаётся отдельным процессом. README описывает текущие команды и конфигурацию первой сборки без migration/обратной совместимости предварительных версий.
4. После проверки реализации, подтверждённого merge в dev и закрытия issue архивировать change, доставить синхронизацию specs/архив отдельным MR при необходимости, обновить путь в issue и безопасно удалить worktree. При откате implementation commit базовые --group-id/--role/--order и уже принятые возможности сохраняются.

## Pinned Linear references

Это сторонние CLI; проверены публичные sources/docs, а не live workspace поведение.

| Источник | Применение |
| --- | --- |
| [schpet list_view](https://github.com/schpet/linear-cli/blob/63463ac8a31d7a14b7a7d5964c363853d0089842/crates/linear-cli/src/commands/issue/list_view.rs), [view](https://github.com/schpet/linear-cli/blob/63463ac8a31d7a14b7a7d5964c363853d0089842/crates/linear-cli/src/commands/issue/view.rs) | Human tables/cards, O1. |
| [schpet query](https://github.com/schpet/linear-cli/blob/63463ac8a31d7a14b7a7d5964c363853d0089842/crates/linear-cli/src/commands/issue/query.rs), [usage](https://github.com/schpet/linear-cli/blob/63463ac8a31d7a14b7a7d5964c363853d0089842/docs/usage.md) | Composable search и ordinary examples, O2/O3. |
| [schpet errors](https://github.com/schpet/linear-cli/blob/63463ac8a31d7a14b7a7d5964c363853d0089842/crates/linear-cli/src/error.rs), [CLI error tests](https://github.com/schpet/linear-cli/blob/63463ac8a31d7a14b7a7d5964c363853d0089842/crates/linear-cli/tests/cli/errors.rs) | Error categories/observable CLI tests, O2. |
| [schpet dependencies](https://github.com/schpet/linear-cli/blob/63463ac8a31d7a14b7a7d5964c363853d0089842/crates/linear-cli/Cargo.toml) | clap help/error-context/suggestions/wrap_help; console, terminal_size, unicode-width, pulldown-cmark. inquire/clap_complete — дальнейшие O5/completion, вне scope. |
| [nesszer output](https://github.com/nesszer/linear-cli/blob/e100db11a3959a528cf449f2befb505cf5d1a342/src/output.rs), [issues](https://github.com/nesszer/linear-cli/blob/e100db11a3959a528cf449f2befb505cf5d1a342/src/commands/issues.rs), [dependencies](https://github.com/nesszer/linear-cli/blob/e100db11a3959a528cf449f2befb505cf5d1a342/Cargo.toml) | Human/machine formatting, issue filters; tabled/colored/clap. dialoguer — дальнейший O5, вне scope. |

Для URL lookup, интерактивности, output selection, auth context и proof workflows используются дополнительные ссылки O4–O8 в [issue #12](https://github.com/mesilov/bitrix24-cli/issues/12). Эти ref не добавляют implementation tasks или зависимости этого change.
