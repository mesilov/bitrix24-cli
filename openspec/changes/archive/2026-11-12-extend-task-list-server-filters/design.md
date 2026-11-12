# Design

## Context

Мотивация и состояние `dev` 8c21f45 описаны в proposal.md, раздел «Why». Кратко о коде:

- `TaskListRoute::plan()` выбирает REST1 без временных флагов, если есть кандидат из `SERVER_FIELDS` (`groupId`, `responsibleId`, `creatorId`). Для временных флагов он возвращает `null`, и маршрут решает `TaskListScanner`.
- `TaskListScanner::scan()` на временном маршруте строит filter вручную: `DeadlineWindow::filter()`, `ID` и `GROUP_ID`. Исполнитель, роль и статус проверяются только локально.
- `DeadlineWindowResolver` рассчитывает `[from, to)` по мнемоникам и фиксирует `now` один раз; результат — `DeadlineWindow` с полем `overdue`.
- `Rest1TaskListAdapter` уже нормализует `status` (2–7), `created` (`CREATED_DATE`) и `changed` (`CHANGED_DATE`).
- `RuntimeState` общий для команды; у сканера нет способа писать в stderr.

Проверка документации REST 1.0 `tasks.task.list` (https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-list.html, 12.11.2026):

- `REAL_STATUS` — фильтр по статусу (поле ответа `status`), документированные значения 2, 3, 4, 5, 6. Значение 7 (отклонена) в списке фильтра не указано. `STATUS` содержит мета-статусы −1/−2/−3 (`subStatus`) и для этого change не подходит.
- `CREATED_DATE`, `CHANGED_DATE`, `CLOSED_DATE`, `DATE_START`, `DEADLINE` указаны как фильтруемые; префиксы `<`, `<=`, `>`, `>=`, `!`. `ACTIVITY_DATE` в списке фильтров нет.
- `GROUP_ID`, `RESPONSIBLE_ID`, `CREATED_BY` фильтруемые; страница — 50 записей, `start`.
- Формат даты в фильтре не указан. Маршрут `--deadline-window` уже передаёт `DATE_ATOM` с offset и проверен на портале; тот же формат используется для новых полей.

## Goals / Non-Goals

**Goals:**

- `--status` сужает серверную выборку при `task-v3` с маршрутом REST1, как группа и исполнитель.
- Временной маршрут передаёт на сервер все поддерживаемые условия, а не только группу.
- Окна по дате создания и изменения с теми же мнемониками и правилами, что у `--deadline-window`.
- Пользователь видит в stderr, что идёт локальный обход, почему и сколько просмотрено.

**Non-Goals:**

- Серверная фильтрация `declined`, `--due-before`, title, ролей участников.
- Окна по `closed`/`activity`, произвольные диапазоны дат (`--created-from/--created-to`).
- Изменение `task:my`, `task:handover`, `task:report` и других пользователей сканера без `apiPolicy`.
- Изменение `--max-scan` по умолчанию и семантики `--all`/`--limit`.

## Decisions

### 1. `status` — серверный кандидат через `REAL_STATUS`

`TaskListRoute` добавляет `status` в серверные поля с маппингом `pending→2`, `in_progress→3`, `supposedly_completed→4`, `completed→5`, `deferred→6`. Значение `declined` кандидатом не является: оно не задокументировано для `REAL_STATUS`. Если `declined` — единственное условие, маршрут остаётся REST3 с `reason: no-server-predicate`. В `meta.listRoute.serverFilter` поле называется `status` (имя поля CLI, как `groupId`).

Локальная проверка `status` по каждой строке сохраняется на обоих маршрутах (сейчас её выполняет общий цикл по `where`). Строка с другим статусом не добавляется к совпадениям. Так сервер только сужает выборку, а ответ подтверждается локально.

*Альтернатива.* Отправлять `REAL_STATUS=7` для `declined`. Отклонена: значение не задокументировано, а проектное правило требует сверять поля с документацией.

### 2. Единый план серверных условий для обоих маршрутов REST1

`TaskListRoute::plan()` перестаёт возвращать `null` для временных флагов. Для них он всегда возвращает `apiVersion: "1.0"`, `reason: null` и `serverFilter` из `id`, кандидатов решения 1 и полей окон (`deadline`, `created`, `changed`). `TaskListRoute::filter()` строит одинаковый filter для обоих маршрутов; временной маршрут добавляет к нему границы окон. Ручная сборка filter в `TaskListScanner` удаляется.

Проверка `--select`/`--order` на временном маршруте не меняется: неподдерживаемое поле даёт usage-error/exit 2 (`Rest1TaskListAdapter::validate()`), а не REST3. Без временных флагов неподдерживаемое поле по-прежнему оставляет REST3 с `reason: unsupported-field:<name>`.

`meta.deadlineFilter` сохраняет все ключи, включая `groupApplied`. `meta.listRoute` на временном маршруте — новый ключ.

### 3. Окна дат: общий калькулятор и три поля

`DeadlineWindowResolver` разделяется на расчёт окна и сборку значений:

- `CalendarWindow::bounds(string $window, \DateTimeImmutable $now): array{from, to}` — существующая логика мнемоник.
- `DateWindow` (`field`: `created`|`changed`, `native`: `CREATED_DATE`|`CHANGED_DATE`, `window`, `timezone`, `now`, `from`, `to`) с методами `filter()`, `matches()` и `metadata()` по образцу `DeadlineWindow`.
- Резолвер получает все три окна и `--overdue` за один вызов, проверяет `--timezone` один раз и фиксирует один `now` для всех окон.

`TaskQuery` получает `list<DateWindow> $dateWindows`. «Временной режим» означает: `deadlineWindow !== null || dateWindows !== []`. Все проверки маппера (повтор окна, смешение с `--where`/`--due-before`, `--timezone` без временного флага) применяются к новым флагам. Текст ошибки `--timezone` перечисляет все временные флаги.

`DeadlineWindow` остаётся отдельным классом: у него есть `overdue` и связанный с ним статусный фильтр.

### 4. Локальная проверка дат и скрытые поля

Сканер добавляет `created`/`changed` в `select` запроса, если выбрано соответствующее окно, и проверяет дату строки через `DateWindow::matches()` (момент времени, а не строку). Пустая или нераспознаваемая дата даёт `missing-predicate-field` и exit 3. Если пользователь не выбрал поле в `--select`, оно удаляется из строки результата. Существующий механизм `hiddenRoleField` обобщается в список скрытых полей.

### 5. Метаданные окон

`meta.createdFilter` и `meta.changedFilter`: `window`, `timezone`, `now`, `fromInclusive`, `toExclusive`, `route` (`tasks.task.list`, `1.0`) и `applied: "server"`. Ключи появляются только для выбранных окон. `meta.deadlineFilter` не меняется.

### 6. Предупреждение и прогресс в stderr

`RuntimeState` получает необязательный обработчик `?\Closure $notice` (строка → void). `CommandRunner` устанавливает его перед выполнением: запись в stderr (`ConsoleOutputInterface::getErrorOutput()`) с `VERBOSITY_NORMAL`, поэтому `--quiet`/`--silent` подавляют сообщения средствами Symfony Console. После команды обработчик сбрасывается.

`TaskListScanner` вызывает `notice` только если `TaskListRoute::plan()` вернул REST3-маршрут (то есть команда — `task:list`/`task:find` с `apiPolicy`) и `ids === []`:

- до первого запроса — строка о локальном обходе с `reason`, `--max-scan` и советом добавить серверный критерий;
- после каждых 1000 просмотренных строк — `scanned`, `matched`, бюджет.

Тексты на русском, как остальная human-диагностика. Сообщения не зависят от формата вывода: stdout в `--json` и `--plain` не меняется.

*Альтернатива.* Порт `ScanProgress` в DI-контейнере. Отклонена: сервис создаётся до выбора выхода команды, а `RuntimeState` уже служит каналом состояния выполнения между Console и Application.

*Альтернатива.* Прогресс только при TTY в stderr. Отклонена: агенты и скрипты запускают CLI без TTY и именно им нужен сигнал о долгом обходе. Объём ограничен: не более 10 строк при бюджете по умолчанию.

## Risks / Trade-offs

- [`REAL_STATUS` на портале ведёт себя иначе, чем в документации] → Локальная проверка статуса остаётся; read-only приёмка сравнивает выборку REST1 с `REAL_STATUS` и без него на одной группе.
- [Часовые пояса `CREATED_DATE`/`CHANGED_DATE`: портал отдаёт дату в поясе пользователя] → Фильтр с явным offset и локальная проверка по моменту времени, как у `DEADLINE`. Read-only приёмка проверяет границу окна.
- [Сообщения в stderr ломают скрипты, которые ожидают пустой stderr] → Подавляются `--quiet`/`--silent`; описано в справке и `CHANGELOG.md`.
- [Пользователь ожидает `--created-window` у `task:my`/`task:report`] → Вне scope; справка перечисляет команды с новыми флагами.

## Migration Plan

Миграция данных не нужна. Новые флаги аддитивны. Изменение маршрута для `--status` видно в `meta.listRoute`; вернуть прежнее поведение можно через `--api-policy strict-rest3`. Откат — revert MR.
