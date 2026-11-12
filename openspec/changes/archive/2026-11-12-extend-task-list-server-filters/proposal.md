# Proposal

## Why

`task:list`/`task:find` по-прежнему обходят портал локально, когда в запросе нет группы, исполнителя или постановщика. `--status` всегда проверяется на клиенте, поэтому `task:list --status in_progress --limit 5` на большом портале читает до 10 000 задач (`--max-scan`) и молчит минутами. Кроме того, задачи нельзя выбрать по дате создания или изменения: запрос «задачи группы за квартал» сейчас заменяют окном по дедлайну, а это другой смысл.

Состояние на `dev` 8c21f45 (после [#183](https://github.com/mesilov/bitrix24-cli/issues/183)):

- Без временных флагов при `task-v3` на сервер REST 1.0 `tasks.task.list` уже уходят `GROUP_ID`, `RESPONSIBLE_ID`, `CREATED_BY` и `ID` (`meta.listRoute`). Пример из issue (`--group-id <ID> --select id --select title --limit 5`) на `dev` идёт этим маршрутом. Выпущенная 0.2.0 этого ещё не умеет.
- `--status` и `where.status` проверяются только локально. Запрос только со статусом выбирает REST 3.0 и полный обход (`reason: no-server-predicate`).
- Временной маршрут (`--deadline-window`/`--overdue`) передаёт на сервер только `DEADLINE`, `ID` и `GROUP_ID`. Исполнитель, роль и статус на нём проверяются локально.
- Фильтров по `CREATED_DATE`/`CHANGED_DATE` нет.
- Локальный обход REST 3.0 ничего не пишет в stderr до конца. Пользователь не видит, что идёт полный обход.

## What Changes

- `--status` и `where.status` при `task-v3` передаются в серверный фильтр REST 1.0 `REAL_STATUS` (код 2–6). Значение `declined` (код 7) не задокументировано для фильтра `REAL_STATUS`, поэтому остаётся локальной проверкой. Локальная проверка статуса сохраняется и на серверном маршруте как AND-подтверждение.
- Временной маршрут передаёт на сервер те же условия, что и маршрут без временных флагов: `GROUP_ID`, `RESPONSIBLE_ID`, `CREATED_BY` (в том числе из `--role responsible|creator`) и `REAL_STATUS`. Локальная проверка сохраняется.
- Новые флаги `--created-window` и `--changed-window` у `task:list` и `task:find` принимают те же значения, что `--deadline-window` (`today`, `tomorrow`, `yesterday`, `day-before-yesterday`, `this-week`, `this-month`, `this-quarter`). Они задают полуоткрытый интервал `[from, to)` в `--timezone` и передаются как `>=CREATED_DATE`/`<CREATED_DATE` и `>=CHANGED_DATE`/`<CHANGED_DATE`. Окна комбинируются между собой, с `--deadline-window`/`--overdue` и с остальными критериями через AND. `meta.createdFilter` и `meta.changedFilter` показывают границы по образцу `meta.deadlineFilter`.
- Новые флаги — временные: они подчиняются тем же правилам, что `--deadline-window` (REST 1.0, `strict-rest3` → `policy-denied`/exit 4 до запросов, запрет смешивания с `--where`/`--where-file`/`--due-before`, проверка `--select`/`--order` по полям REST 1.0).
- `meta.listRoute` (`apiVersion`, `serverFilter`, `reason`) появляется и на временном маршруте. Существующие ключи `meta`, включая `deadlineFilter.groupApplied`, сохраняются.
- Когда команда выбирает локальный обход REST 3.0 с локальными условиями, она пишет в stderr предупреждение с причиной (`meta.listRoute.reason`) и бюджетом `--max-scan`, а затем прогресс просмотра каждые 1000 задач. Предупреждение и прогресс подавляются `--quiet`/`--silent` и не попадают в stdout; JSON-конверт не меняется.
- Обновляются справка `task:list`/`task:find`, `docs/task-usage.md`, публичный skill задач и `CHANGELOG.md`.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `cli-experience`: требование «Explicit filtering and completeness of lists» — серверный `REAL_STATUS`, серверные условия на временном маршруте, `meta.listRoute` на временном маршруте; новое требование о предупреждении и прогрессе локального обхода в stderr.
- `task-deadline-windows`: требования «Canonical calendar windows», «Server date filtering and API policy», «Filter combinations and invalid input», «Public skill translation» — окна по дате создания и изменения, общие правила временных флагов, серверные условия временного маршрута.
- `task-console-runtime`: требование «Policy covers the complete route plan» — план со `--status` и с `--created-window`/`--changed-window` содержит REST 1.0 `tasks.task.list`; `strict-rest3` отклоняет новые окна до запросов.

## Impact

- Код: `TaskListRoute` (кандидат `status` → `REAL_STATUS`, план временного маршрута), `TaskListScanner` (фильтр временного маршрута, проверка окон created/changed, события прогресса), `DeadlineWindowResolver`/`DeadlineWindow` (окна для трёх полей), `TaskQuery`, `TaskInputMapper`, `ListTaskCommand`/`FindTaskCommand` (флаги и справка), консольный вывод прогресса в stderr.
- API: используется уже разрешённый REST 1.0 `tasks.task.list`. Новые поля фильтра — `REAL_STATUS`, `CREATED_DATE`, `CHANGED_DATE`; все задокументированы как фильтруемые. Новых методов и scopes нет.
- Тесты: unit-тесты плана маршрута, фильтра и окон; `ListTaskCommandTest`/`FindTaskCommandTest`; read-only приёмка на портале.
- Документация: справка, `docs/task-usage.md`, skill задач, `CHANGELOG.md`.
- Не затрагивает `task:my`, `task:handover`, `task:report` и другие команды, использующие сканер без `apiPolicy`.

## GitHub issues

- [#186](https://github.com/mesilov/bitrix24-cli/issues/186) — `task:list`: `--group-id` фильтруется локально, нет фильтра по дате создания. Change покрывает три предложения issue: серверный `--status` (группа и исполнитель уже на сервере после #183) и серверные условия временного маршрута, окна `--created-window`/`--changed-window`, предупреждение и прогресс локального обхода в stderr.
