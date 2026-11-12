# Tasks

## 1. REST1 server filter gateway

- [x] 1.1 Читать серверную выборку через существующий порт `DeadlineTaskGateway`/`Rest1TaskListAdapter` с filter `ID`/`GROUP_ID`/`RESPONSIBLE_ID`/`CREATED_BY`, общей нормализацией и проверкой `total`/`next`; списки полей REST1 в `TaskListRoute` сверять с адаптером. Проверка: `TaskServerListFilterTest` — отправленный filter, `start`-пагинация, нормализация `status`/`groupId`/`responsibleId` в формате REST3, несогласованный `total` → `pagination-no-progress`, `testRest1FieldListsMatchTheAdapter`.

## 2. Route selection and planning

- [x] 2.1 Ввести в `TaskQuery`/`TaskListScanner` выбор маршрута до запросов по design, решение 2 (нет временных флагов, `task-v3`, есть серверное условие, все `select`/`order`/`where` поддерживаются REST1), с `reason` для REST3. Проверка: unit-тесты на каждую ветку выбора, включая `--order responsibleId:asc` → REST3 с `unsupported-field:responsibleId` и `strict-rest3` → REST3.
- [x] 2.2 Объявлять фактический маршрут в `ListTasksHandler::routes()` и `FindTasksHandler::routes()`: REST1 `tasks.task.list` без `tasks.task.get` для группы; для `accomplice`/`auditor` — плюс REST1 `tasks.task.get`. Проверка: `CommandContractsTest` (REST1 payload `task:list --responsible 2`, REST3 для `--status`), `CommandBehaviorTest`, `TaskServerListFilterTest::testLocalRouteIsChosenBeforeRequests`; `strict-rest3` не возвращает `policy-denied` для `--group-id`/`--responsible`/`--role responsible|creator`.

## 3. Scanner behaviour on the server route

- [x] 3.1 Строить серверный filter из `--group-id`, `--responsible`, `where.groupId|responsibleId|creatorId` и ID владельца вебхука для `--role responsible|creator`; при конфликте явного значения и роли отправлять явное значение, а роль проверять локально. Остальные предикаты, title и участники роли — локально, через AND. Проверка: unit-тесты сканера — совпадение на второй странице, `--limit 1` (`matched`≠`returned`), конфликт ролей → полный пустой результат с `complete=true`, нет `tasks.task.get` для группы, `--role auditor --group-id` читает участников только у кандидатов группы.
- [x] 3.2 Добавить `meta.listRoute` (`apiVersion`, `serverFilter`, `reason`) для обоих маршрутов, не меняя остальные ключи `meta`, `data.items` и TSV-колонки. Проверка: `TaskServerListFilterTest` — точная форма `meta.listRoute` и строк `data.items` для REST1 и REST3; прежние тесты локального пути (`CombinedTaskSearchTest`, `RecentTaskScanTest`, `GroupIdOptionTest`, `TaskRoleFilterTest`, `TaskLifecycleTest`) проходят при `strict-rest3`.
- [x] 3.3 Разделить подсказку `scan-budget-exhausted` для `task:list` и `task:find` в `TaskListScanner` (`meta.hint`) и `TaskHumanRenderer`. Проверка: unit-тест — `task:list` не упоминает дубли, `task:find` сохраняет текущий текст.

## 4. Help and documentation

- [x] 4.1 Обновить справку `ListTaskCommand`/`FindTaskCommand`: какие условия идут на сервер при `task-v3`, когда остаётся REST3, что считает `--max-scan`. Проверка: `bin/b24cli task:list --help` без credentials показывает новый текст; тест офлайн-справки проходит.
- [x] 4.2 Обновить `docs/task-usage.md` (разделы про `--group-id`, `--role` и полноту) и добавить запись в `CHANGELOG.md` → `Unreleased` → «Исправлено» со ссылкой на #183. Проверка: `git diff origin/dev...HEAD -- CHANGELOG.md docs/task-usage.md` содержит обе правки.

## 5. Integration and acceptance

- [x] 5.1 Добавить portal cases в `ListTaskCommandTest` и `FindTaskCommandTest` на собственной fixture-группе: задача группы с текущим ответственным включается; задача той же группы с другим ответственным и задача другой группы с тем же ответственным исключаются; `meta.listRoute.apiVersion=1.0`; множество ID совпадает с REST3-маршрутом при `strict-rest3`. Проверка: `make integration` на playground-портале, cleanup fixtures подтверждён.
- [x] 5.2 Выполнить `make unit` и линтеры проекта. Проверка: все проходят на текущем SHA ветки.
- [x] 5.3 Повторить воспроизведение #183 на `indigo.rarus.ru` кодом ветки (запуск из исходников; standalone-бинарник проверяется при приёмке сборки): `task:list --role responsible --group-id 1096 --all --json` возвращает `complete=true`, exit 0, `data.items=[]` для пользователя 4084, а `task:list --group-id 1096 --all --json` — задачи 95290, 95289, 95286. Проверка: JSON и время выполнения записаны в `verification.md` change.

## Workflow follow-up

- Попросить автора #183 повторить команду под своим профилем и сверить ID задач с интерфейсом.
- После merge в `dev` и закрытия #183 архивировать change и обновить путь в разделе `## OpenSpec` issue.
