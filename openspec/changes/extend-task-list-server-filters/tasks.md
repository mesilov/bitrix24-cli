## 1. Серверный статус

- [x] 1.1 Добавить `status` в серверные поля `TaskListRoute` с маппингом в `REAL_STATUS` (`pending`→2 … `deferred`→6); `declined` не делать кандидатом; `meta.listRoute.serverFilter` содержит `status`.
- [x] 1.2 Сохранить локальную проверку статуса на REST1 маршруте; строка с другим или неизвестным статусом не совпадает.
- [x] 1.3 Unit-тесты в `TaskServerListFilterTest`: `--status` и `where.status` → REST1 с `REAL_STATUS`; `declined` один → REST3 `no-server-predicate`; `declined` с группой → REST1 без `REAL_STATUS`; `strict-rest3` → REST3; план `routes()` для этих случаев.

## 2. Единый план временного маршрута

- [x] 2.1 `TaskListRoute::plan()` для временных флагов возвращает REST1 с `serverFilter` из `id`, серверных кандидатов и полей окон; `filter()` строит общий filter обоих маршрутов.
- [x] 2.2 Удалить ручную сборку filter временного маршрута из `TaskListScanner`; на временном маршруте отправлять `RESPONSIBLE_ID`, `CREATED_BY` (в том числе из роли) и `REAL_STATUS`.
- [x] 2.3 Добавить `meta.listRoute` на временном маршруте; сохранить ключи `meta.deadlineFilter`, включая `groupApplied`.
- [x] 2.4 Тесты временного маршрута: filter с исполнителем, ролью и статусом (`TaskDateWindowFilterTest::testWindowsAndServerPredicatesCombineInOneRequest`); существующие ключи `meta.deadlineFilter` подтверждает неизменённый `TaskDeadlineFilterTest`.

## 3. Окна `--created-window` и `--changed-window`

- [x] 3.1 Выделить расчёт границ мнемоник из `DeadlineWindowResolver`; добавить `DateWindow` (`created`/`changed` → `CREATED_DATE`/`CHANGED_DATE`) с `filter()`, `matches()`, `metadata()`.
- [x] 3.2 Резолвер разрешает все окна и `--overdue` за один вызов с одним `now` и одним `--timezone`; `TaskQuery` получает `dateWindows`.
- [x] 3.3 `TaskInputMapper`: флаги в `task:list`/`task:find`, отказ при повторе, неизвестном значении, смешении с `--where`/`--where-file`/`--due-before`; ошибка `--timezone` без временного флага перечисляет все временные флаги.
- [x] 3.4 `TaskListScanner`: границы окон в filter, добавление `created`/`changed` в select, локальная проверка момента времени, `missing-predicate-field` для пустой/неверной даты, скрытие невыбранных полей из результата; `meta.createdFilter`/`meta.changedFilter`.
- [x] 3.5 `strict-rest3` отклоняет новые окна до запросов (`routes()` объявляет REST1).
- [x] 3.6 Unit-тесты: границы окон на 2026-11-12 (`this-week`, `this-quarter`), один `now` для нескольких окон, filter с `>=CREATED_DATE`/`<CHANGED_DATE` и offset, комбинация трёх окон с ролью и статусом, ошибки usage, policy-denied, скрытые поля, `missing-predicate-field`.

## 4. Предупреждение и прогресс локального обхода

- [x] 4.1 `RuntimeState::$notice` и установка обработчика в `CommandRunner` (stderr, `VERBOSITY_NORMAL`, сброс после команды).
- [x] 4.2 `TaskListScanner`: предупреждение до первого запроса на REST3 маршруте без `--id` (причина, `--max-scan`, совет) и строка прогресса каждые 1000 просмотренных строк.
- [x] 4.3 Тесты: текст в stderr и чистый stdout в human/`--plain`/`--json`; нет сообщений на REST1 маршруте и с `--id`; подавление `--quiet`/`--silent`; прогресс после 1000 строк на fake transport.

## 5. Документация

- [x] 5.1 Справка `ListTaskCommand`/`FindTaskCommand`: новые флаги, серверный `--status`, серверные условия временного маршрута, сообщения stderr.
- [x] 5.2 `docs/task-usage.md`: окна по дате создания/изменения, пример «задачи группы за квартал», серверный статус и `declined`, предупреждение в stderr.
- [x] 5.3 `CHANGELOG.md` (`[Unreleased]`) со ссылкой на #186.

## 6. Проверка

- [x] 6.1 `make lint-all` и `make unit` без ошибок.
- [x] 6.2 Read-only приёмка на портале ручными запросами CLI (интеграционный набор пишет данные и не запускался): `--status` на REST1 совпадает с полным обходом REST3; окна created/changed совпадают с локальным расчётом и сужают выборку на сервере; предупреждение в stderr для локального обхода. Граница окна на портале не проверена (нет задач у границы), её покрывают unit-тесты. Результаты — в `verification.md`.
- [x] 6.3 `openspec validate extend-task-list-server-filters --strict`; сверить имена API-полей (`REAL_STATUS`, `CREATED_DATE`, `CHANGED_DATE`, `GROUP_ID`, `RESPONSIBLE_ID`, `CREATED_BY`) в спецификациях, коде и тестах.
- [ ] 6.4 После выпуска флагов передать в отдельный репозиторий skill задач перевод «созданные/изменённые за период» → `--created-window`/`--changed-window` (требование «Public skill translation»); до этого отметить в `verification.md`, что skill не обновлён.
