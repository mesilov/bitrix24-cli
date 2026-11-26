# Design

## Context

Требования к scope уже заданы кодом:

- `ScopeManifest` относит каждый метод к группе scope: 39 методов задач → `task|tasks`, 19 прочих методов с явными группами, `call.followup.get` (REST 3.0) — необязательный источник `call`;
- `CommandCatalog::ROUTES` перечисляет для каждой команды полное объединение маршрутов по всем режимам. Это объединение использует `auth:status` → `potentiallyAffectedCommands`;
- фактический план отдельного вызова строят `routes()` обработчиков, и он может быть уже объединения. Например, `ListTasksHandler` добавляет `im.counters.get` только при `--unread`.

[docs/auth-scopes.md](../../../docs/auth-scopes.md) описывает правила проверки абзацами со ссылками на источники. Похожий генератор уже есть: `scripts/cli-reference.php` строит `docs/cli-command-map.md` в режимах `--write`/`--check`, а `tests/Unit/HelpAndAboutTest.php` сверяет карту с каталогом команд. Отдельного CI-шага для `make cli-reference-check` нет: дрейф ловит unit-тест в `command-tests.yml`.

Параллельно в worktree `152-webhook-setup-guide` лежит незакоммиченный change `explain-webhook-setup`. Он планирует собственную таблицу scope по группам команд в руководстве по подключению. Его тексты написаны до перехода с `connection:*` на `auth:*`.

## Goals / Non-Goals

**Goals:**
- одна матрица «команда → scope → маршруты», которая не может разойтись с манифестом незаметно;
- короткий ответ в README для пользователя, который настраивает вебхук впервые;
- раздел, который по признакам ошибки направляет к нужному действию.

**Non-Goals:**
- изменение манифеста, маршрутов, кодов ошибок или их текстов;
- команда `doctor`;
- изменения в репозитории скилла и в change #152.

## Decisions

### 1. Матрица генерируется и вставляется между маркерами

Раздел «Матрица разрешений» в `docs/auth-scopes.md` ограничен маркерами `<!-- scope-matrix:start -->` и `<!-- scope-matrix:end -->`. Текст между ними целиком производит генератор, а остальной документ остаётся ручным.

Чистый класс `Bitrix24\CLI\Application\Connection\ScopeMatrix` строит markdown-блок без ввода-вывода по `ScopeManifest` и `CommandCatalog::ROUTES`. Скрипт `scripts/scope-matrix.php` только читает и пишет файл: `--write` заменяет блок, `--check` сравнивает и завершается с кодом 1 и подсказкой `make scope-matrix`. Цели Makefile — `scope-matrix` и `scope-matrix-check`, по образцу `cli-reference`.

Unit-тест вызывает тот же класс и сравнивает результат с блоком в документе. Поэтому дрейф ловит существующий CI-прогон тестов, отдельный шаг не нужен.

Альтернативы:
- ручная таблица плюс тест-парсер. Тест пришлось бы писать под формат таблицы, а при ошибке он не исправляет документ;
- столбец scope в `docs/cli-command-map.md`. Карта команд и так широкая, а маршруты и условия в неё не помещаются. Если понадобится, ссылку из карты на матрицу можно добавить позже.

### 2. Строка матрицы

Столбцы: «Команда», «Обязательные scope», «Только при опциях», «Необязательные», «REST-маршруты».

- Группы с альтернативами выводятся как `task` / `tasks` и `user` / `user_basic` / `user_brief`; рекомендуемый код идёт первым. Порядок групп фиксированный и одинаковый для всех строк: `task|tasks`, `user|…`, `department`, `im`, `disk`, `sonet_group`. Так генерация детерминирована.
- Маршруты выводятся как `метод (1.0)` / `метод (3.0)` без повторов, в порядке каталога.
- Команды группируются по пространствам имён: `auth`, `users`, `task`, `task:chat`, `chats` и т.д. Внутри группы — по имени, как в карте команд.
- Последней строкой идёт перечень команд без обращения к порталу: публичные команды приложения, которых нет в `CommandCatalog::ROUTES`, — `auth:list`, `auth:switch`, `auth:logout`, `config:*`, `help`, `list`, `completion`. Перечень вычисляется, а не пишется вручную.
- `auth:login` и `auth:status` используют маршруты без scope (`profile`, `scope`). Для них в матрице явно написано «не требуются».

### 3. Условия опций — ручная карта с проверкой

Из `CommandCatalog::ROUTES` нельзя понять, какой scope зависит от опции. Поэтому генератор держит небольшую ручную карту `команда → scope → условие`. Известные пары после разбора `routes()`:

| Команда | Scope | Условие |
| --- | --- | --- |
| `task:list` | `im` | только `--unread` (`im.counters.get`) |
| `task:show` | `user` / `user_basic` / `user_brief` | только `--select` с полями участников кроме `id` (`user.get`) |
| `task:file:attach` | `disk` | только локальный файл `--file`; `--file-id` требует лишь scope задач |
| `chats:send` | `sonet_group` | только поиск по названию `--name` |
| `chats:send` | `task` / `tasks` | только `--name` или TARGET `task:N` |
| `chats:resolve`, `chats:messages:list`, `chats:calls:list`, `chats:calls:show`, `users:presence` | `task` / `tasks` | только TARGET `task:N` (`tasks.task.get` для привязки чата задачи) |

Карта сверена со всеми `routes()`, где состав зависит от запроса: `ChatCalls`, `ChatCreate`, `ChatDestination`, `ChatMessages`, `ChatSend`, `AddTask`, `AttachTaskFiles`, `FindTasks`, `ListTasks`, `ShowTask`, `UpdateTask`, `TaskHandover`. Условия, которые меняют только маршруты внутри одной группы scope, в карту не попадают: на набор прав они не влияют. Это REST 1.0/3.0 у `task:add`/`task:update`/`task:find`, `im.search.chat.list` у `chats:add`, звонки `chats:messages:list` и состав lifecycle-переходов `task:handover`.

Тест проверяет, что каждая пара из карты есть среди обязательных групп этой команды по манифесту. Устаревшая пометка ломает тест.

### 4. README — короткая таблица по scope, без маршрутов

В README, в разделе о подключении рядом со ссылкой на `docs/auth-scopes.md`, появляется таблица «Код scope | Название в настройках вебхука | Что без него не работает» для `task`, `user` (с вариантами), `im`, `disk`, `department`, `sonet_group`, `call`. Под таблицей — строка с рекомендуемым набором. Названия взяты из официального справочника scope.

Таблица короткая и меняется редко, поэтому её не генерируем. Тест проверяет только, что каждый код из манифеста упомянут в README и что есть ссылка на матрицу.

### 5. Раздел «Если портал отказывает в доступе»

Раздел пишется вручную в `docs/auth-scopes.md` после матрицы. Он устроен как таблица «Признак → Причина → Что делать». Признаки берутся из фактических ошибок CLI:

- `scope-missing`, exit 4 — предпроверка по кешу: добавить scope, затем `auth:status`;
- `permission-denied` + `apiErrorCode=insufficient_scope` (`SdkApiTransport`) — права вебхука изменились после проверки: то же, что выше;
- `permission-denied` без этого кода — портал отказал пользователю-владельцу вебхука: проверить доступ к объекту, например `task:access:show`;
- `policy-denied`, exit 4 — `--api-policy strict-rest3`: убрать опцию или выбрать REST3-совместимый режим;
- строка `This operation used an explicitly admitted REST 1.0 route.` — сведения о политике `task-v3`, действий не требуется.

Где менять права: *Разработчикам → Готовые сценарии → Другое → Входящий вебхук* при создании, у существующего вебхука — в списке *Интеграции*, блок *Настройка прав*. Шаги создания вебхука здесь не повторяются: их описывает #152, поэтому даётся ссылка на официальную статью. Самодиагностика — один вызов `b24cli auth:status NAME`. Он показывает владельца, выданные и недостающие scope и `potentiallyAffectedCommands`.

### 6. Координация с #152

Матрицей и рекомендуемым набором прав владеет этот change. В отчёте пользователю и в issue #152 нужно отметить, что `explain-webhook-setup` должен ссылаться на README и матрицу, а не вести свою таблицу. Чужой незакоммиченный change не редактируется.

## Risks / Trade-offs

- [Ручная карта условий отстаёт от `routes()`] → тест ловит только устаревшие пометки, а новые условия без пометки не ловит. Смягчение: в задачах есть сверка со всеми `routes()`, где состав зависит от запроса, а описание генератора в `docs/development.md` напоминает проверять карту при добавлении опций.
- [Документация `task`/`tasks` противоречива] → матрица показывает обе альтернативы и ставит рекомендуемый `task` первым. Это уже принятое решение из [docs/auth-scopes.md](../../../docs/auth-scopes.md).
- [`sonet_group.get` по документации требует `sonet`] → матрица показывает фактический код `sonet_group`, подтверждённый live-приёмкой #170. Расхождение уже задокументировано.
- [`im.call.get` не задокументирован] → в матрице он показан с `im`, как в манифесте, со ссылкой на существующую оговорку.
- [Строка про REST 1.0 продолжит путать пользователей] → документация объясняет её. Менять текст диагностики — отдельное решение вне объёма.
- [Пересечение с #152] → если #152 будет влит раньше со своей таблицей, при реализации её нужно заменить ссылкой на матрицу.

## Сверка с документацией

Источник — MCP документации Битрикс24 (`https://mcp-dev.bitrix24.tech/mcp`), 23.11.2026: `bitrix-method-details` по точному имени каждого метода манифеста. Для имён, у которых есть страницы REST 1.0 и 3.0, MCP вернул одну страницу. В таблице она указана как есть; scope REST 1.0 и 3.0 обеих версий покрывается группой `task|tasks`.

Справочник scope и статья о первом запросе получены через `bitrix-article-details`. Граница проверки: только документация, вызовы к порталу не выполнялись.

| Метод | Scope в документации | Страница |
| --- | --- | --- |
| `tasks.template.get` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/template/tasks-template-get.html) |
| `tasks.template.fields` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/template/tasks-template-fields.html) |
| `tasks.template.add` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/template/tasks-template-add.html) |
| `tasks.template.update` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/template/tasks-template-update.html) |
| `tasks.template.delete` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/template/tasks-template-delete.html) |
| `tasks.task.add` | tasks | [ссылка](https://apidocs.bitrix24.ru/api-reference/rest-v3/tasks/tasks-task-add.html) |
| `tasks.task.get` | tasks | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-get-rest-v3.html) |
| `tasks.task.list` | tasks | [ссылка](https://apidocs.bitrix24.ru/api-reference/rest-v3/tasks/tasks-task-list.html) |
| `tasks.task.update` | tasks | [ссылка](https://apidocs.bitrix24.ru/api-reference/rest-v3/tasks/tasks-task-update.html) |
| `tasks.task.delete` | tasks | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/rest-v3/tasks-task-delete.html) |
| `tasks.task.getFields` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-get-fields.html) |
| `tasks.task.field.list` | tasks | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/rest-v3/tasks-task-field-list.html) |
| `tasks.task.field.get` | tasks | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-field-get.html) |
| `tasks.task.access.get` | tasks | [ссылка](https://apidocs.bitrix24.ru/api-reference/rest-v3/tasks/tasks-task-access-get.html) |
| `tasks.task.history.list` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-history-list.html) |
| `tasks.task.file.attach` | tasks | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/rest-v3/tasks-task-file-attach.html) |
| `tasks.task.chat.message.send` | tasks | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-chat-message-send.html) |
| `tasks.task.start` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/status/tasks-task-start.html) |
| `tasks.task.pause` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-pause.html) |
| `tasks.task.defer` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-defer.html) |
| `tasks.task.renew` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/status/tasks-task-renew.html) |
| `tasks.task.complete` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/status/tasks-task-complete.html) |
| `tasks.task.approve` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-approve.html) |
| `tasks.task.disapprove` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-disapprove.html) |
| `tasks.task.result.add` | tasks | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/rest-v3/result/tasks-task-result-add.html) |
| `tasks.task.result.list` | tasks | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/rest-v3/result/tasks-task-result-list.html) |
| `tasks.task.result.update` | tasks | [ссылка](https://apidocs.bitrix24.ru/api-reference/rest-v3/tasks/result/tasks-task-result-update.html) |
| `tasks.task.result.delete` | tasks | [ссылка](https://apidocs.bitrix24.ru/api-reference/rest-v3/tasks/result/tasks-task-result-delete.html) |
| `task.elapseditem.add` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/elapsed-item/task-elapsed-item-add.html) |
| `task.elapseditem.getlist` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/elapsed-item/task-elapsed-item-get-list.html) |
| `task.elapseditem.update` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/elapsed-item/task-elapsed-item-update.html) |
| `task.elapseditem.delete` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/elapsed-item/task-elapsed-item-delete.html) |
| `task.checklistitem.add` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/checklist-item/task-checklist-item-add.html) |
| `task.checklistitem.getlist` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/checklist-item/task-checklist-item-get-list.html) |
| `task.checklistitem.update` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/checklist-item/task-checklist-item-update.html) |
| `task.checklistitem.complete` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/checklist-item/task-checklist-item-complete.html) |
| `task.checklistitem.renew` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/checklist-item/task-checklist-item-renew.html) |
| `task.checklistitem.delete` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/checklist-item/task-checklist-item-delete.html) |
| `task.commentitem.getlist` | task | [ссылка](https://apidocs.bitrix24.ru/api-reference/tasks/comment-item/task-comment-item-get-list.html) |
| `sonet_group.user.get` | sonet_group | [ссылка](https://apidocs.bitrix24.ru/api-reference/sonet-group/members/sonet-group-user-get.html) |
| `sonet_group.get` | sonet | [ссылка](https://apidocs.bitrix24.ru/api-reference/sonet-group/sonet-group-get.html) |
| `scope` | базовый | [ссылка](https://apidocs.bitrix24.ru/api-reference/common/system/scope.html) |
| `profile` | базовый | [ссылка](https://apidocs.bitrix24.ru/api-reference/common/users/profile.html) |
| `user.current` | user, user_brief, user_basic | [ссылка](https://apidocs.bitrix24.ru/api-reference/user/user-current.html) |
| `user.get` | user, user_brief, user_basic | [ссылка](https://apidocs.bitrix24.ru/api-reference/user/user-get.html) |
| `department.get` | department | [ссылка](https://apidocs.bitrix24.ru/api-reference/departments/department-get.html) |
| `im.dialog.get` | im | [ссылка](https://apidocs.bitrix24.ru/api-reference/chats/im-dialog-get.html) |
| `im.counters.get` | im | [ссылка](https://apidocs.bitrix24.ru/api-reference/chats/im-counters-get.html) |
| `im.dialog.messages.get` | im | [ссылка](https://apidocs.bitrix24.ru/api-reference/chats/messages/im-dialog-messages-get.html) |
| `im.search.chat.list` | im | [ссылка](https://apidocs.bitrix24.ru/api-reference/chats/search/im-search-chat-list.html) |
| `im.message.add` | im | [ссылка](https://apidocs.bitrix24.ru/api-reference/chats/messages/im-message-add.html) |
| `im.chat.add` | im | [ссылка](https://apidocs.bitrix24.ru/api-reference/chats/im-chat-add.html) |
| `im.message.update` | im | [ссылка](https://apidocs.bitrix24.ru/api-reference/chats/messages/im-message-update.html) |
| `im.message.delete` | im | [ссылка](https://apidocs.bitrix24.ru/api-reference/chats/messages/im-message-delete.html) |
| `im.call.get` | — | не найдена |
| `im.user.list.get` | im | [ссылка](https://apidocs.bitrix24.ru/api-reference/chats/users/im-user-list-get.html) |
| `im.dialog.users.list` | im | [ссылка](https://apidocs.bitrix24.ru/api-reference/chats/chat-users/im-dialog-users-list.html) |
| `disk.folder.uploadFile` | disk | [ссылка](https://apidocs.bitrix24.ru/api-reference/disk/folder/disk-folder-upload-file.html) |
| `call.followup.get` | call | [ссылка](https://apidocs.bitrix24.ru/api-reference/telephony/follow-up/call-followup-get.html) |
