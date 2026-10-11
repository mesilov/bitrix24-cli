# Design

## Context

Мотивация и release scope — в [proposal](proposal.md), наблюдаемое поведение — в [дельте cli-experience](specs/cli-experience/spec.md). Дата исследования — 2026-10-11. Базовый task runtime закреплён на PR #11 / `7c14c85d4ab28f91dcbfb56bc90b282f3fb32551`: ResultPresenter имеет общие таблицы и field/value карточки; B24Application теряет parser context; FindTaskCommand не предоставляет list filters, хотя TaskQuery/TaskListScanner уже поддерживают необходимые predicates.

Planning worktree создан от `dev`; task runtime и canonical cli-experience ещё находятся в открытом PR #11. Дельта MODIFIED адресует именно эту capability. Перед apply требуется принятие базы и сверка её актуального варианта; текущая OpenSpec validation проверяет структуру планирования, не наличие базы в dev. Незакоммиченные изменения соседних worktrees не включаются в этот change.

## Goals / Non-Goals

**Goals:**

- Human formatting и parser diagnostics остаются отдельным слоем над неизменным application result и transport.
- Search использует общие query validation/predicates и scanner, без отдельного search backend.
- JSON/TSV, HTTP маршруты и права подключения сохраняют прежнее значение; вывод делает полноту заметной.

**Non-Goals:**

- O4–O8 из issue #12, новые API exceptions, silent fallback, TUI, auth profiles, implicit personal scope, status mutations и local upload.
- Получение дополнительных names/relations для улучшения оформления: renderer использует только уже доступные данные. Изменение portal enrichment в PR #11 принимается как отдельная база, не копируется сюда.
- Перенос Rust libraries в PHP или установка новой библиотеки ради сходства с Linear CLI.

## Decisions

### 1. Уточнить существующую cli-experience

Сохраняем пять исходных requirement blocks целиком и добавляем DX scenarios: help, human output, visible completeness, errors и bounded title search. Не создаём почти дублирующую capability task-cli-dx. Остальные requirements и task-mvp-scope сохраняются. Альтернатива — новая общая DX capability — рассредоточила бы контракт stdout/filter/error между двумя местами.

### 2. Pure human presentation поверх существующего result

ResultPresenter выбирает human карточку для task-card и сохраняет прежние JSON/plain ветви. Основные поля идут в semantic order, остальные selected поля — после них. Names отображаются из уже нормализованного result с ID; отсутствующий name означает ID fallback. Неизвестный status отображается raw; известный локально документированный enum может иметь читаемую подпись рядом с raw value. Deadline сохраняет явный offset, без выдумывания timezone или срока. Link отображается только при наличии допустимого значения в результате.

Ширина вывода учитывает доступную ширину терминала и Unicode display width. Длинный title/description переносится; полное описание сохраняется. При отсутствии терминальной ширины действует конечный documentable default. Данные задачи не исполняются как Symfony markup; escape/control handling предотвращают terminal sequences. Нативные NO_COLOR/non-TTY/quiet/silent правила применяются ко всем новым строкам.

Используем существующие Symfony Console Table, OutputFormatter и terminal width utilities. Отдельные pure helpers допустимы при необходимости, без внедрения network ports в presenter. Альтернативы: оставить API key/value для human либо изменить application object ради оформления — обе увеличивают зависимость человека/скриптов от внутренних деталей.

### 3. Completeness summary не меняет данные или exit status

Для task-list/title-matches читаем существующие scope/returned/matched/scanned/complete/limitApplied/reason. Human summary идёт рядом с результатом; обязательная plain диагностика — в stderr, TSV stdout остаётся прежним. JSON получает только действующий envelope/meta, без декоративных строк.

Различаем complete scan + limitApplied (exit 0), complete empty (exit 0) и incomplete scan (exit 3). Для scan-budget-exhausted подсказываем --max-scan; для page-error — повторное чтение, для missing predicate data — проверку доступных полей/прав. --all не снимает scan cap. Не печатаем total проекта, которого API не доказал. Альтернатива — скрывать counters в JSON — оставляет человека без информации о границах выборки.

### 4. Parser context строится из безопасных metadata

B24Application сохраняет общую error boundary и usage-error/exit 2, но различает unknown option, missing argument/option value и конфликт. Формируем сообщения из известных имён command/options и статических примеров, а не копируем raw exception/argv. Неизвестное имя проходит sanitization; произвольные значения не отражаются. Действующие application Failure сохраняют стабильные codes/details и masking.

Suggestions — только текст: известная однозначная команда/опция либо направление к help/list при неоднозначности. Ни correction execution, ни prompt/network до локальной validation не добавляются. Help task commands начинается с обычных примеров, затем flags/defaults, advanced usage и ограничения; root list/version остаются credential-free. Альтернатива raw parser getMessage раскрывает ввод и не гарантирует безопасную диагностику.

### 5. Shared query contract для title AND filters

FindTaskCommand получает повторяемый --id, --responsible, --project, --status, --due-before и --where/--where-file. --title остаётся обязательным trim literal Unicode substring. TaskInputMapper собирает общий TaskQuery с теми же allowlists/positive ID/date/where conflict rules, что task:list; source reader сохраняет явный stdin и single-consumer rule.

TaskListScanner проверяет title и все predicates через AND; selected fields для проверки не пропускаются. Только id передаётся как документированный server filter. Project/responsible/status/deadline/title продолжают локальный scan: narrowing scope не является обещанием меньшего числа API calls. Наличие missing predicate fields, page error или cap остаётся partial.

Выход find по-прежнему содержит только id/title, сортировка id ASC и прежние defaults лимитов. Нужные для predicates поля не расширяют data.items/TSV. Новые --order/--select или search в description/chat не добавляются. Альтернатива отдельного JSON filtering после команды заставляет пользователя собирать запрос вручную и теряет единый completeness contract.

## Risks / Trade-offs

- База PR #11 меняется → первый implementation task сверяет merged canonical specs, renderer и query; при конфликте план пересматривается до code edits.
- Narrow human layout теряет значения → wrapping и сохранение дополнительных selected fields проверяются на длинном русском тексте и вложенных данных; JSON/plain остаются источником стабильной структуры.
- Более подробная ошибка отражает секрет → whitelist metadata/static examples, фикстуры с secret-bearing argv и отсутствие raw exception output.
- Сводка путает scan completeness с output limit → отдельные complete/limitApplied messages и tests для complete empty, limited complete и capped partial.
- Локальные фильтры сканируют множество задач → сохраняются finite --max-scan и явный scope; server optimizations остаются отдельным API change.
- Отличается human оформление → это ожидаемое изменение; machine contracts и native stream/verbosity behavior проверяются отдельно.

## Migration Plan

1. Принять task runtime PR #11 в dev, сверить capability/контракты, обновить отдельную issue ветку от актуальной dev без перезаписи пользовательских изменений.
2. Реализовать O1–O3 и проверки, обновить README/help и ссылки на issue/change.
3. Выполнить offline suite, repo quality gates и strict OpenSpec validation, отправить отдельный implementation PR в dev. Runtime acceptance #10 остаётся отдельным процессом.
4. После подтверждения реализации/соответствия spec архивировать change и обновить путь в issue. При откате revert implementation commit сохраняет task runtime базы; новые фильтры больше не доступны, JSON/TSV до и после остаются совместимыми.

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
