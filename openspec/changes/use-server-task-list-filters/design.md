# Design

## Context

Мотивация и данные воспроизведения — в proposal.md, раздел «Why».

Текущее состояние (`dev` 787770e):

- `TaskListScanner::scan()` без временных флагов отправляет в REST3 `tasks.task.list` только `[['id','in',ids]]`. Группу, `responsibleId`, `creatorId`, статус, `--due-before`, `--where` и title он проверяет по каждой строке. Если в строке нет `groupId`, он запрашивает `tasks.task.get(['group.id'])` для каждого кандидата.
- REST 3.0 документирует фильтр только по `id`, а на портале отвечает `REQUESTVALIDATIONEXCEPTION` на фильтр по `groupId`/`responsibleId` (проверено 12.11.2026 на двух порталах).
- `Rest1TaskListAdapter` уже реализует `DeadlineTaskGateway` и `WeeklyReportTaskGateway`: маппинг полей, нормализацию статусов и чисел, `start`-пагинацию и проверку согласованности `total`/`next`. Маршрут `tasks.task.list@1` уже есть в `CommandCatalog::ROUTES` для `task:list`/`task:find`.
- `ApiPolicyGuard` отклоняет любой non-v3 маршрут при `strict-rest3`.

## Goals / Non-Goals

**Goals:**

- Полный и быстрый ответ `task:list`/`task:find` для группы, исполнителя и постановщика на портале любого размера.
- Маршрут выбирается до запросов и детерминирован по входным флагам; в `meta` видно, что отфильтровано на сервере.
- Для существующих вызовов без этих условий, а также для `strict-rest3`, поведение не меняется.

**Non-Goals:**

- Серверная фильтрация `accomplice`/`auditor`, статуса, `--due-before` и title. Они остаются локальными. Поддержку фильтров участников в REST1 нужно проверить отдельно.
- Изменение `task:handover`, `task:my`, `task:report`, `task:status-durations` и других пользователей сканера.
- Изменение значения `--max-scan` по умолчанию и семантики `--all`.

## Decisions

### 1. Серверные условия через существующий REST1-адаптер

На маршруте REST1 `TaskListScanner` строит filter `GROUP_ID`, `RESPONSIBLE_ID`, `CREATED_BY` и `ID` из `TaskQuery`: `--group-id`/`where.groupId`, `--responsible`/`where.responsibleId`, `where.creatorId` и ID владельца вебхука для `--role responsible|creator`. Если явное значение и роль задают одно поле с разными ID, на сервер уходит явное значение, а роль проверяется локально. Выборка получается полной и пустой, как требует сценарий «Contradictory criteria». Сокращать путь без запроса не нужно: счётчики остаются честными.

Страницы читаются через существующий порт `DeadlineTaskGateway::page()` с той же сигнатурой, его реализует `Rest1TaskListAdapter`. Нормализация строк общая с маршрутом временных окон, поэтому `status`, `groupId` и `responsibleId` совпадают по формату с REST3. Выбор маршрута и построение filter живут в `TaskListRoute`. Его списки `REST1_FIELDS`/`REST1_SORT_FIELDS` unit-тест сверяет с константами адаптера.

*Альтернативы.* REST3 с недокументированным фильтром отклонён: сервер возвращает ошибку валидации. Отдельный порт или адаптер ради того же вызова `page()` дублировал бы маппинг полей и статусов без новой абстракции; при реализации отказались и от порта `ServerFilteredTaskGateway`, предложенного в первой версии design.

### 2. Выбор маршрута до запросов

Маршрут REST1 выбирается, если выполнены все условия:

1. временных флагов нет;
2. политика — `task-v3`;
3. есть хотя бы одно серверное условие из решения 1;
4. все поля `--select`, `--order` и `--where` входят в `Rest1TaskListAdapter::FIELDS`/`SORT_FIELDS`.

Иначе остаётся текущий маршрут REST3. Причину выбора (`unsupported-field:<name>`, `strict-rest3`, `no-server-predicate`) handler записывает в `meta.listRoute`. `ListTasksHandler::routes()` и `FindTasksHandler::routes()` объявляют тот маршрут, который будет вызван, поэтому `ApiPolicyGuard` и `--dry-run`/план видят фактическую версию API.

*Альтернатива.* Пробовать REST1 и при ошибке откатываться на REST3. Отклонена: проект запрещает скрытый fallback, и после частичного чтения смена маршрута ломает счётчики.

### 3. `strict-rest3` сохраняет текущее поведение

При `strict-rest3` маршрут остаётся REST3 с локальными фильтрами, без `policy-denied`. Так существующие сценарии и скрипты не ломаются; пользователь, которому нужна скорость, использует политику по умолчанию.

*Альтернатива.* Возвращать `policy-denied`, как у временных окон. Отклонена: это регрессия для тех, кто сейчас получает корректный, хоть и медленный, ответ.

### 4. Бюджет и полнота

`--max-scan` считает строки, полученные с сервера, то есть уже отфильтрованные. `complete` и `reason` вычисляются как сейчас, плюс проверка согласованности REST1 `total`/`next` (`Page::complete=false` → `pagination-no-progress`). Лишний `tasks.task.get` для группы на маршруте REST1 не выполняется: `GROUP_ID` есть в каждой строке.

### 5. `meta.listRoute`

Новое поле `meta.listRoute = {apiVersion: "1.0"|"3.0", serverFilter: ["groupId","responsibleId","creatorId","id"], reason: string|null}` дополняет ответ. Существующие ключи `meta` не меняются, `schemaVersion` остаётся 1.

### 6. Подсказка `scan-budget-exhausted`

Подсказку формирует `TaskListScanner::scan()` (`meta.hint`); текст выбирается по режиму: при `$title !== null` (`task:find`) сохраняется предупреждение о дублях, иначе (`task:list`) — «неполный обход не доказывает отсутствие подходящих задач». Human-диагностика `TaskHumanRenderer` (ключ `scan-budget-exhausted`) получает такое же разделение по команде.

## Risks / Trade-offs

- [REST1 и REST3 по-разному видят доступные задачи] → В integration-тесте сравнить множества ID двух маршрутов на одной fixture-группе; в `meta.scope` указывать прежнее значение `tasks-visible-to-connection`.
- [REST1 отдаёт числа строками, `deadline` в другом формате] → Использовать существующую нормализацию `Rest1TaskListAdapter::normalize()` и unit-тест на совпадение строк REST1 и REST3 по формату. Integration-проверка 12.11.2026 на playground показала два оставшихся отличия. REST1 выдаёт `deadline` в часовом поясе пользователя (`19:00+06:00`), REST3 — в поясе сервера (`16:00+03:00`), момент времени одинаковый. Порядок ключей в JSON-строке другой. Столбцы TSV и human фиксированы, поэтому затронут только JSON. Отличия описаны в `docs/task-usage.md`, integration-тест сравнивает значения и момент `deadline`.
- [`--order` по полю, которого нет в REST1 `SORT_FIELDS` (например, `responsibleId`), молча переводит команду на медленный маршрут] → Причина в `meta.listRoute.reason` и строка в human-сводке.
- [Политика организации запрещает REST1] → Для неё есть `strict-rest3`, где сохраняется прежний маршрут.

## Migration Plan

Миграция данных не нужна. Откат — revert MR: маршрут REST3 остаётся рабочим кодом и покрыт текущими тестами.
