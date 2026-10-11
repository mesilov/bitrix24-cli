# Проект поверхности команд задач

Статус: предложение для ревью; прикладные команды ещё не реализованы. Проверено 2026-10-08 для [issue #10](https://github.com/mesilov/bitrix24-cli/issues/10), milestone `0.1.0`. Основания: [исследование](research.md), [машиночитаемая матрица](task-surface.json), официальная документация на commit `2b00d6bd3a845f4169ccb1ff6f0755f156903f4b` и b24phpsdk `3.7.0`.

Каталог объясняет 98 записей официального индекса задач, включая отдельные версии одного метода, и добавляет 6 составных или смежных операций. Предлагается 79 различных имён команд. P0/P1/P2 — проект приоритетов, не утверждённый MVP. P0 содержит 22 команды; P1 расширяет рабочие сценарии; P2 содержит диагностику, персональные настройки и совместимость. Deferred означает отдельную будущую работу.

## Что заимствуем из исследования

| Источник | Вывод для интерфейса |
| --- | --- |
| poydevor/b24-cli | Проверить полный цикл задачи: создание, изменение, исполнение, делегирование, чек-листы, обсуждение, время, канбан. TUI может быть дополнением к обычным командам. |
| bitrix24/templates-mcp | Разделять карточку, переходы состояния, результат и чат. Для new tasks использовать современные методы. |
| kostikpenzin/mcp_b24 | Широкий raw API каталог полезен для поиска пробелов, но один универсальный инструмент не заменяет проверяемые команды сценариев. |
| Официальная документация + закреплённый SDK | Версии, поля и фактические Core calls проверять отдельно; наличие SDK wrapper не доказывает успешный запрос. |

## Предлагаемый первый этап

- `tasks:create`
- `tasks:update`
- `tasks:get`
- `tasks:list`
- `tasks:fields`
- `tasks:access`
- `tasks:chat:send`
- `tasks:approve`
- `tasks:disapprove`
- `tasks:start`
- `tasks:pause`
- `tasks:defer`
- `tasks:complete`
- `tasks:renew`
- `tasks:result:add`
- `tasks:result:list`
- `tasks:checklist:add`
- `tasks:checklist:list`
- `tasks:checklist:complete`
- `tasks:time:add`
- `tasks:time:list`
- `tasks:chat:list`

Для сценариев нужны также отдельные базовые команды подключения и поиска пользователя: `connection:check`, `user:current`, `user:find`. Это зависимости будущей реализации, не часть 79 имён задач. При нескольких совпадениях имя пользователя не должно автоматически превращаться в произвольный ID.

## Версии и маршрутизация

| Область | Основной маршрут предложения |
| --- | --- |
| Создание, обновление, карточка, удаление, схема полей и права | REST 3.0, scope `tasks` |
| Список с фильтром по ответственному, статусу, проекту | REST 1.0, scope `task` |
| Альтернативный список `--api-version 3` | REST 3.0; документированный filter только `id` |
| Чат: отправка и создание результата | REST 3.0 |
| Lifecycle, чек-листы, ручное время, канбан | REST 1.0 |
| Изменение accomplices/auditors | REST 1.0 через `tasks.task.update` |
| Чтение чата | `tasks.task.get` 3.0 → `im.dialog.messages.get` 1.0, scope `tasks + im` |
| Редактирование/удаление сообщений | IM 1.0, scope `im` |

REST 1.0 использует `/rest`, REST 3.0 — `/rest/api`. Выбор версии должен задаваться маршрутом команды; для имеющихся альтернатив — явно через `--api-version 1|3`. После ошибки нет молчаливого fallback. Метаданные scope в TaskServiceBuilder SDK недостаточны: они ещё содержат `task`, хотя часть Core calls уже v3. Auth/transport следующего change должен учитывать фактический маршрут.

`tasks:create` v3 требует `title`, `creatorId`, `responsibleId`, поэтому предлагаются обязательные `--title`, `--creator`, `--responsible`. V3 accomplices/auditors — связанные объекты для чтения, а не принимаемые поля add/update; для них выделена `tasks:participants:set`. Создание и назначение участников — отдельные записи без обещания атомарности.

V3 list по умолчанию выбирает только `id`; human-представление должно явно запрашивать нужные поля, например `id,title,status`. Legacy фильтры и поля в UPPERCASE не переносятся в v3. Priority v3: `high|average|low`; status v3: `pending|in_progress|supposedly_completed|completed|deferred|declined`; mark: `positive|negative|none`.

Переход статуса, стадия канбана и результат — разные операции. `complete` может отправить задачу на контроль; вывести фактическое состояние, а не объявлять завершение заранее. `parentId` задаёт иерархию подзадач; dependence задаёт связь Ганта. Link type legacy: 0 start-start, 1 start-finish, 2 finish-start, 3 finish-finish; направление FROM → TO. `tasks.task.stopwatch` отключает наблюдение, не останавливает таймер.

## Предлагаемый контракт CLI

Команды оформляются в Symfony namespace `tasks:`. Human и JSON — режимы вывода одной операции. `--help` работает без подключения. `--profile` выбирает настроенное подключение; токены не передаются в прикладных аргументах и не попадают в вывод.

`--json` даёт один JSON envelope в stdout; прогресс и диагностика идут в stderr. Успех: `{"ok":true,"data":...,"meta":...}`; ошибка: `{"ok":false,"error":...,"meta":...}`. В meta указываются REST method и apiVersion; для составного запроса — сведения о шагах. Data сохраняет структуру выбранной API-версии. Унифицированный DTO между legacy и v3 пока не задан.

`--fields @file.json` и `--params @file.json` загружают JSON, `-` обозначает stdin. Filter/order/select — JSON выбранной версии. Конфликт значения поля между флагом и JSON считается ошибкой, а не скрытым переопределением. Даты — ISO 8601 с offset; без offset требуется явно заданная timezone портала. ID должны быть положительными целыми: task, checklist item, message, result и time entry различаются. `--seconds` — положительное целое.

Предлагаемые exit codes: 0 успех, 2 локальный ввод, 3 auth/permissions, 4 REST error, 5 transport error, 6 неполный результат. Поле error содержит стабильный локальный код и безопасные данные серверной ошибки. Полная классификация серверных ошибок нужна в implementation change.

`--limit` ограничивает число возвращаемых строк, default 50. `--all` явно собирает страницы, с ограничителем `--max-rows` (предложение default 10000). При ограничителе или сбое после части страниц вернуть признак неполноты и exit 6. Legacy start/next и v3 pagination.page/offset не смешиваются; v3 page size default 50, max 1000. Messenger, checklist и elapsed list имеют свои контракты пагинации: не применять к ним без проверки пагинатор task.list.

Название команды записи выражает намерение пользователя. Для удаления — `--yes` в non-TTY либо подтверждение с preview в TTY. `--dry-run` выполняет локальную проверку и показывает план; он не доказывает права сервера и не пишет в API. Сетевой preflight — только как явно выбранное чтение. Первый этап работает с отдельными задачами; bulk-запись вынесена за него.

Нельзя автоматически повторять запись после transport timeout, когда неизвестно, применил ли её сервер. Идемпотентность REST не предполагается. Составные сценарии сообщают успех/ошибку каждого шага; транзакции и rollback не обещаются.

## Чат новой карточки и результаты

`tasks:chat:send` использует `fields={taskId,text}`. Для чтения получить `chat.id` через get v3 и использовать `DIALOG_ID=chat{ID}` в IM. Message ID и legacy comment ID не взаимозаменяемы. Перед IM update/delete проверить принадлежность сообщения чату задачи.

Обзор документации говорит о неприменимости старых комментариев в новой карточке, а таблица миграции допускает legacy add. Update/delete/getlist там не работают. Поэтому новые сценарии строятся на chat, legacy-команды имеют явное имя и P2. Result add v3 использует `fields={taskId,text}`; result from message — `fields={messageId}`. Старые addFromComment/deleteFromComment не заменяют эти действия.

`tasks:file:attach` принимает уже загруженные Disk file IDs; upload локального файла требует отдельного Disk workflow. `tasks:time:add` — ручная запись `TASKID, ARFIELDS.SECONDS`; это не работа секундомера и не право списывать время за другого пользователя. Stage move принимает before либо after: таблица параметров запрещает их одновременное использование, хотя один пример источника противоречив.

## Примеры будущего интерфейса

Эти примеры описывают проект, сейчас в bin/console их нет.

```sh
make cli ARGS='tasks:list --filter "{\"RESPONSIBLE_ID\":42,\"!STATUS\":5}" --limit 50 --json'
make cli ARGS='tasks:create --title "Подготовить отчёт" --creator 7 --responsible 42'
make cli ARGS='tasks:chat:send 123 --text "Начал работу"'
make cli ARGS='tasks:time:add 123 --seconds 1800 --text "Сбор данных"'
make cli ARGS='tasks:result:add 123 --text "Отчёт подготовлен"'
make cli ARGS='tasks:complete 123'
```

## Матрица операций

В каждой строке синтаксис — проект CLI-параметров, не полный REST payload. `--params` и `--fields` обозначают необходимость отдельной проверки схемы при реализации. SDK wrapper означает статическое наличие метода; legacy-batch — только batch-обёртку; core-gap — отсутствие прямой обёртки в проверенном Task inventory. Supplemental IM находится вне этого inventory.

### Карточка и диагностика

| Команда и параметры | REST / версия | SDK | Приоритет / эффект | Ограничения |
| --- | --- | --- | --- | --- |
| `tasks:create --title TEXT --creator ID --responsible ID [--fields @file.json] --api-version 1` | [tasks.task.add](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-add.md) / 1.0 | [legacy-batch](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Service/Batch.php) | P2 / write / compatibility | Карточка; v3 требует creatorId; legacy TITLE/RESPONSIBLE_ID. |
| `tasks:create --title TEXT --creator ID --responsible ID [--fields @file.json]` | [tasks.task.add](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-add-rest-v3.md) / 3.0 | [wrapper: add()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Service/Task.php) | P0 / write | Карточка; v3 требует creatorId; legacy TITLE/RESPONSIBLE_ID. |
| `tasks:update ID --fields @file.json --api-version 1` | [tasks.task.update](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-update.md) / 1.0 | [legacy-batch](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Service/Batch.php) | P2 / write / compatibility | Частичное обновление. Поля только выбранной версии API; статус менять специальной командой. |
| `tasks:update ID --fields @file.json` | [tasks.task.update](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-update-rest-v3.md) / 3.0 | [wrapper: update()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Service/Task.php) | P0 / write | Частичное обновление. Поля только выбранной версии API; статус менять специальной командой. |
| `tasks:get ID [--select JSON] --api-version 1` | [tasks.task.get](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-get.md) / 1.0 | core-gap | P2 / read / compatibility | В v3 связанные объекты выбираются через select, например chat.id. |
| `tasks:get ID [--select JSON]` | [tasks.task.get](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-get-rest-v3.md) / 3.0 | [wrapper: get()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Service/Task.php) | P0 / read | В v3 связанные объекты выбираются через select, например chat.id. |
| `tasks:list [--filter JSON] [--order JSON] [--select JSON] [--limit N] [--all]` | [tasks.task.list](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-list.md) / 1.0 | [legacy-batch](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Service/Batch.php) | P0 / read | Основная версия для фильтров исполнителя/проекта/статуса — legacy; v3 filter документирует только id. |
| `tasks:list [--filter JSON] [--order JSON] [--select JSON] [--limit N] [--all] --api-version 3` | [tasks.task.list](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-list-rest-v3.md) / 3.0 | core-gap | P1 / read | Основная версия для фильтров исполнителя/проекта/статуса — legacy; v3 filter документирует только id. |
| `tasks:delete ID --yes --api-version 1` | [tasks.task.delete](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-delete.md) / 1.0 | [legacy-batch](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Service/Batch.php) | P2 / delete / compatibility | Удаление задачи, без массового удаления в MVP. |
| `tasks:delete ID --yes` | [tasks.task.delete](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-delete-rest-v3.md) / 3.0 | [wrapper: delete()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Service/Task.php) | P1 / delete | Удаление задачи, без массового удаления в MVP. |
| `tasks:fields --api-version 1` | [tasks.task.getFields](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-get-fields.md) / 1.0 | core-gap | P1 / read | Legacy схема полей; не заменяет схему v3. |
| `tasks:fields [--select JSON]` | [tasks.task.field.list](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-field-list.md) / 3.0 | [wrapper: list()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/TaskField/Service/TaskField.php) | P0 / read | Современная схема. |
| `tasks:field:get NAME` | [tasks.task.field.get](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-field-get.md) / 3.0 | [wrapper: get()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/TaskField/Service/TaskField.php) | P1 / read | Описание конкретного поля. |
| `tasks:access ID --api-version 1` | [tasks.task.getaccess](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-get-access.md) / 1.0 | core-gap | P1 / read | Legacy права. |
| `tasks:access ID` | [tasks.task.access.get](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-access-get.md) / 3.0 | [wrapper: get()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Service/TaskAccess.php) | P0 / read | Права задачи v3; серверная проверка всё равно обязательна. |
| `tasks:access:fields` | [tasks.task.access.field.list](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-access-field-list.md) / 3.0 | [wrapper: list()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/AccessField/Service/AccessField.php) | P2 / read | Схема объекта прав. |
| `tasks:access:field:get NAME` | [tasks.task.access.field.get](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-access-field-get.md) / 3.0 | [wrapper: get()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/AccessField/Service/AccessField.php) | P2 / read | Одно поле схемы прав. |
| `tasks:delegate ID --responsible USER_ID` | [tasks.task.delegate](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-delegate.md) / 1.0 | core-gap | P1 / write | taskId,userId; не подмена делегирования прямым UPDATE. |
| `tasks:counters [--params @file.json]` | [tasks.task.counters.get](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-counters-get.md) / 1.0 | core-gap | P1 / read | Счётчики пользователя; точную схему сверить отдельной страницей. |
| `tasks:history ID [--filter JSON --order JSON]` | [tasks.task.history.list](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-history-list.md) / 1.0 | core-gap | P1 / read | История; taskId, filter, order. |
| `tasks:planner:list [--params @file.json]` | [task.planner.getList](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/planner/task-planner-get-list.md) / 1.0 | [wrapper: getList()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Planner/Service/Planner.php) | P2 / read | Смежная область: План на день. Индекс пишет getList, actual SDK call — getlist. |

### Зависимости

| Команда и параметры | REST / версия | SDK | Приоритет / эффект | Ограничения |
| --- | --- | --- | --- | --- |
| `tasks:dependency:list ID [--limit N --offset N]` | [tasks.task.gantt.link.list](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-gantt-link-list.md) / 3.0 | core-gap | P1 / read | REST 3.0; filter=[taskId,ID]. Не список подзадач. |
| `tasks:dependency:add FROM_ID TO_ID --type INTEGER` | [task.dependence.add](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/task-dependence-add.md) / 1.0 | core-gap | P1 / write | 0/1/2/3; направление FROM → TO; отдельная связь от parentId. |
| `tasks:dependency:delete FROM_ID TO_ID --yes` | [task.dependence.delete](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/task-dependence-delete.md) / 1.0 | core-gap | P1 / delete | Удаление связи, а не связанных задач. |

### Файлы

| Команда и параметры | REST / версия | SDK | Приоритет / эффект | Ограничения |
| --- | --- | --- | --- | --- |
| `tasks:file:attach ID --file-ids JSON --api-version 1` | [tasks.task.files.attach](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-files-attach.md) / 1.0 | core-gap | P2 / write | Legacy путь; IDs уже загруженных файлов. |
| `tasks:file:attach ID --file-ids JSON` | [tasks.task.file.attach](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-file-attach.md) / 3.0 | [wrapper: attachExists()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Service/TaskFile.php) | P1 / write | taskId + fileIds; загрузка локального файла — отдельный Disk workflow. |
| `tasks:file:fields` | [tasks.task.file.field.list](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-file-field-list.md) / 3.0 | [wrapper: list()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/FileField/Service/FileField.php) | P2 / read | Схема файлов. |
| `tasks:file:field:get NAME` | [tasks.task.file.field.get](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-file-field-get.md) / 3.0 | [wrapper: get()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/FileField/Service/FileField.php) | P2 / read | Одно поле файлов. |

### Чат задачи

| Команда и параметры | REST / версия | SDK | Приоритет / эффект | Ограничения |
| --- | --- | --- | --- | --- |
| `tasks:chat:send ID --text TEXT или --text-file PATH` | [tasks.task.chat.message.send](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-chat-message-send.md) / 3.0 | [wrapper: sendMessage()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Service/TaskChat.php) | P0 / write | fields={taskId,text}; новая карточка. |
| `tasks:chat:fields` | [tasks.task.chat.message.field.list](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-chat-message-field-list.md) / 3.0 | [wrapper: list()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/ChatMessageField/Service/ChatMessageField.php) | P2 / read | Схема сообщения task chat. |
| `tasks:chat:field:get NAME` | [tasks.task.chat.message.field.get](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-chat-message-field-get.md) / 3.0 | [wrapper: get()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/ChatMessageField/Service/ChatMessageField.php) | P2 / read | Одно поле сообщения. |
| `tasks:result:from-message MESSAGE_ID` | [tasks.task.result.addfromchatmessage](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/result/tasks-task-result-addfromchatmessage.md) / 3.0 | core-gap | P1 / write | fields={messageId}; сообщение именно task chat. |
| `tasks:chat:list ID [--params @file.json]` | [tasks.task.get + im.dialog.messages.get](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-new.md) / mixed | composite | P0 / read | Получить chat.id; DIALOG_ID=chat{chat.id}. Пагинация messenger, не tasks.list. |
| `tasks:chat:update ID MESSAGE_ID --text TEXT` | [im.message.update](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/chats/messages/im-message-update.md) / 1.0 | outside-task | P1 / write | Проверить принадлежность сообщения чату задачи; MESSAGE_ID ≠ COMMENT_ID. |
| `tasks:chat:delete ID MESSAGE_ID --yes` | [im.message.delete](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/chats/messages/im-message-delete.md) / 1.0 | outside-task | P1 / delete | Проверить принадлежность сообщения task chat и права автора. |

### Жизненный цикл

| Команда и параметры | REST / версия | SDK | Приоритет / эффект | Ограничения |
| --- | --- | --- | --- | --- |
| `tasks:approve ID` | [tasks.task.approve](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-approve.md) / 1.0 | core-gap | P0 / write | Контроль постановщика; не синоним complete. |
| `tasks:disapprove ID` | [tasks.task.disapprove](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/tasks-task-disapprove.md) / 1.0 | core-gap | P0 / write | Отклонение результата постановщиком. |
| `tasks:start ID` | [tasks.task.start](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/status/tasks-task-start.md) / 1.0 | core-gap | P0 / write | Начать выполнение. |
| `tasks:pause ID` | [tasks.task.pause](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/status/tasks-task-pause.md) / 1.0 | core-gap | P0 / write | Приостановить выполнение. |
| `tasks:defer ID` | [tasks.task.defer](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/status/tasks-task-defer.md) / 1.0 | core-gap | P0 / write | Отложить. |
| `tasks:complete ID` | [tasks.task.complete](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/status/tasks-task-complete.md) / 1.0 | core-gap | P0 / write | Может отправить на контроль; вернуть фактическое состояние. |
| `tasks:renew ID` | [tasks.task.renew](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/status/tasks-task-renew.md) / 1.0 | core-gap | P0 / write | Возобновить; не равнозначно повторной попытке start. |

### Личные действия

| Команда и параметры | REST / версия | SDK | Приоритет / эффект | Ограничения |
| --- | --- | --- | --- | --- |
| `tasks:watch ID` | [tasks.task.startwatch](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/user-actions/tasks-task-start-watch.md) / 1.0 | core-gap | P1 / write | Наблюдение текущего пользователя; не запуск таймера. |
| `tasks:unwatch ID` | [tasks.task.stopwatch](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/user-actions/tasks-task-stop-watch.md) / 1.0 | core-gap | P1 / write | Отключить наблюдение; stopwatch не секундомер. |
| `tasks:favorite:add ID` | [tasks.task.favorite.add](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/user-actions/tasks-task-favorite-add.md) / 1.0 | core-gap | P2 / write | Избранное текущего пользователя. |
| `tasks:favorite:remove ID` | [tasks.task.favorite.remove](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/user-actions/tasks-task-favorite-remove.md) / 1.0 | core-gap | P2 / write | Убрать из избранного. |
| `tasks:pin ID` | [tasks.task.pin](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/user-actions/tasks-task-pin.md) / 1.0 | core-gap | P2 / write | Закрепление списка. |
| `tasks:unpin ID` | [tasks.task.unpin](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/user-actions/tasks-task-unpin.md) / 1.0 | core-gap | P2 / write | Снять закрепление. |
| `tasks:mute ID` | [tasks.task.mute](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/user-actions/tasks-task-mute.md) / 1.0 | core-gap | P2 / write | Без звука. |
| `tasks:unmute ID` | [tasks.task.unmute](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/user-actions/tasks-task-unmute.md) / 1.0 | core-gap | P2 / write | Вернуть уведомления. |
| `tasks:rate ID --mark positive\|negative\|none` | [tasks.task.update](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/fields-rest-v3.md) / 3.0 | [wrapper: update()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Service/Task.php) | P2 / write | fields.mark; не lifecycle/status. |

### Результат

| Команда и параметры | REST / версия | SDK | Приоритет / эффект | Ограничения |
| --- | --- | --- | --- | --- |
| `tasks:legacy-result:from-comment COMMENT_ID` | [tasks.task.result.addFromComment](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/result/tasks-task-result-add-from-comment.md) / 1.0 | [wrapper: addFromComment()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/TaskResult/Service/Result.php) | P2 / write | Только старая карточка; в новой не работает. |
| `tasks:result:list ID [--params @file.json] --api-version 1` | [tasks.task.result.list](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/result/tasks-task-result-list.md) / 1.0 | [wrapper: list()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/TaskResult/Service/Result.php) | P2 / read / compatibility | Предпочтительна v3; SDK result()->list() — legacy. |
| `tasks:legacy-result:unlink-comment COMMENT_ID --yes` | [tasks.task.result.deleteFromComment](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/result/tasks-task-result-delete-from-comment.md) / 1.0 | [wrapper: deleteFromComment()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/TaskResult/Service/Result.php) | P2 / delete | Не работает для результата новой карточки. |
| `tasks:result:add ID --text TEXT или --text-file PATH` | [tasks.task.result.add](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/result/tasks-task-result-add.md) / 3.0 | core-gap | P0 / write | fields={taskId,text}; не комментарий и не complete. |
| `tasks:result:update RESULT_ID --text TEXT` | [tasks.task.result.update](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/result/tasks-task-result-update.md) / 3.0 | core-gap | P1 / write | Сервер проверяет автора/права. |
| `tasks:result:list ID [--params @file.json]` | [tasks.task.result.list](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/result/tasks-task-result-list-rest-v3.md) / 3.0 | core-gap | P0 / read | Предпочтительна v3; SDK result()->list() — legacy. |
| `tasks:result:delete RESULT_ID --yes` | [tasks.task.result.delete](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/result/tasks-task-result-delete.md) / 3.0 | core-gap | P1 / delete | Удаляет результат, а не задачу. |

### Чек-листы

| Команда и параметры | REST / версия | SDK | Приоритет / эффект | Ограничения |
| --- | --- | --- | --- | --- |
| `tasks:checklist:add ID --fields @file.json` | [task.checklistitem.add](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/checklist-item/task-checklist-item-add.md) / 1.0 | [wrapper: add()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Checklistitem/Service/Checklistitem.php) | P0 / write | Дерево checklist; ID задачи отдельно от item ID. |
| `tasks:checklist:update ID ITEM_ID --fields @file.json` | [task.checklistitem.update](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/checklist-item/task-checklist-item-update.md) / 1.0 | [wrapper: update()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Checklistitem/Service/Checklistitem.php) | P1 / write | Редактирование пункта. |
| `tasks:checklist:get ID ITEM_ID` | [task.checklistitem.get](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/checklist-item/task-checklist-item-get.md) / 1.0 | [wrapper: get()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Checklistitem/Service/Checklistitem.php) | P1 / read | Один пункт. |
| `tasks:checklist:list ID [--params @file.json]` | [task.checklistitem.getlist](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/checklist-item/task-checklist-item-get-list.md) / 1.0 | [wrapper: getList()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Checklistitem/Service/Checklistitem.php) | P0 / read | Пункты с иерархией; не терять parent ID. |
| `tasks:checklist:move ID ITEM_ID AFTER_ITEM_ID` | [task.checklistitem.moveafteritem](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/checklist-item/task-checklist-item-move-after-item.md) / 1.0 | [wrapper: moveAfterItem()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Checklistitem/Service/Checklistitem.php) | P1 / write | Изменение порядка. |
| `tasks:checklist:complete ID ITEM_ID` | [task.checklistitem.complete](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/checklist-item/task-checklist-item-complete.md) / 1.0 | [wrapper: complete()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Checklistitem/Service/Checklistitem.php) | P0 / write | Отметить пункт выполненным. |
| `tasks:checklist:renew ID ITEM_ID` | [task.checklistitem.renew](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/checklist-item/task-checklist-item-renew.md) / 1.0 | [wrapper: renew()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Checklistitem/Service/Checklistitem.php) | P1 / write | Снять выполнение пункта. |
| `tasks:checklist:delete ID ITEM_ID --yes` | [task.checklistitem.delete](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/checklist-item/task-checklist-item-delete.md) / 1.0 | [wrapper: delete()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Checklistitem/Service/Checklistitem.php) | P1 / delete | Удаление заголовка может удалить дерево; предусмотреть preview. |
| `tasks:checklist:access ID ITEM_ID --action ACTION` | [task.checklistitem.isactionallowed](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/checklist-item/task-checklist-item-is-action-allowed.md) / 1.0 | [wrapper: isActionAllowed()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Checklistitem/Service/Checklistitem.php) | P2 / read | Права конкретного действия. |
| `tasks:checklist:manifest` | [task.checklistitem.getmanifest](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/checklist-item/task-checklist-item-get-manifest.md) / 1.0 | [wrapper: getManifest()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Checklistitem/Service/Checklistitem.php) | P2 / read | Диагностика схемы методов. |

### Legacy-комментарии

| Команда и параметры | REST / версия | SDK | Приоритет / эффект | Ограничения |
| --- | --- | --- | --- | --- |
| `tasks:legacy-comment:add ID --text TEXT` | [task.commentitem.add](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/comment-item/task-comment-item-add.md) / 1.0 | [wrapper: add()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Commentitem/Service/Commentitem.php) | P2 / write | Старая модель; миграционная таблица допускает add в новой, но новый CLI использует chat. |
| `tasks:legacy-comment:update ID COMMENT_ID --fields @file.json` | [task.commentitem.update](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/comment-item/task-comment-item-update.md) / 1.0 | [wrapper: update()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Commentitem/Service/Commentitem.php) | P2 / write | Только старая карточка; не работает в новой. |
| `tasks:legacy-comment:get ID COMMENT_ID` | [task.commentitem.get](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/comment-item/task-comment-item-get.md) / 1.0 | [wrapper: get()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Commentitem/Service/Commentitem.php) | P2 / read | Не обещать поддержку новой карточки. |
| `tasks:legacy-comment:list ID [--params @file.json]` | [task.commentitem.getlist](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/comment-item/task-comment-item-get-list.md) / 1.0 | [wrapper: getList()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Commentitem/Service/Commentitem.php) | P2 / read | В новой использовать chat:list. |
| `tasks:legacy-comment:delete ID COMMENT_ID --yes` | [task.commentitem.delete](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/comment-item/task-comment-item-delete.md) / 1.0 | [wrapper: delete()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Commentitem/Service/Commentitem.php) | P2 / delete | Только старая карточка. |

### Затраченное время

| Команда и параметры | REST / версия | SDK | Приоритет / эффект | Ограничения |
| --- | --- | --- | --- | --- |
| `tasks:time:add ID --seconds N [--text TEXT]` | [task.elapseditem.add](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/elapsed-item/task-elapsed-item-add.md) / 1.0 | [wrapper: add()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Elapseditem/Service/Elapseditem.php) | P0 / write | TASKID,ARFIELDS.SECONDS; ручной учёт, не таймер; USER_ID другого пользователя не разрешён. |
| `tasks:time:update ID ENTRY_ID --fields @file.json` | [task.elapseditem.update](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/elapsed-item/task-elapsed-item-update.md) / 1.0 | [wrapper: update()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Elapseditem/Service/Elapseditem.php) | P1 / write | Исправление существующей записи. |
| `tasks:time:get ID ENTRY_ID` | [task.elapseditem.get](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/elapsed-item/task-elapsed-item-get.md) / 1.0 | [wrapper: get()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Elapseditem/Service/Elapseditem.php) | P1 / read | Одна запись. |
| `tasks:time:list ID [--params @file.json]` | [task.elapseditem.getlist](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/elapsed-item/task-elapsed-item-get-list.md) / 1.0 | [wrapper: getList()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Elapseditem/Service/Elapseditem.php) | P0 / read | Не путать со сводным elapsedTime карточки. |
| `tasks:time:delete ID ENTRY_ID --yes` | [task.elapseditem.delete](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/elapsed-item/task-elapsed-item-delete.md) / 1.0 | [wrapper: delete()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Elapseditem/Service/Elapseditem.php) | P1 / delete | Удалить запись времени. |
| `tasks:time:access ID ENTRY_ID --action ACTION` | [task.elapseditem.isactionallowed](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/elapsed-item/task-elapsed-item-is-action-allowed.md) / 1.0 | [wrapper: isActionAllowed()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Elapseditem/Service/Elapseditem.php) | P2 / read | Права конкретного действия. |
| `tasks:time:manifest` | [task.elapseditem.getmanifest](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/elapsed-item/task-elapsed-item-get-manifest.md) / 1.0 | [wrapper: getManifest()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Elapseditem/Service/Elapseditem.php) | P2 / read | Диагностика схемы методов. |

### Канбан

| Команда и параметры | REST / версия | SDK | Приоритет / эффект | Ограничения |
| --- | --- | --- | --- | --- |
| `tasks:stage:add --fields @file.json` | [task.stages.add](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/stages/task-stages-add.md) / 1.0 | [wrapper: add()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Stage/Service/Stage.php) | P2 / write | Создание стадии канбана/Моего плана. |
| `tasks:stage:update STAGE_ID --fields @file.json` | [task.stages.update](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/stages/task-stages-update.md) / 1.0 | [wrapper: update()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Stage/Service/Stage.php) | P2 / write | Изменение стадии. |
| `tasks:stage:list --params @file.json` | [task.stages.get](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/stages/task-stages-get.md) / 1.0 | [wrapper: get()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Stage/Service/Stage.php) | P1 / read | Указать объект: проект или Мой план; не угадывать контекст. |
| `tasks:stage:access --params @file.json` | [task.stages.canmovetask](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/stages/task-stages-can-move-task.md) / 1.0 | [wrapper: canMoveTask()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Stage/Service/Stage.php) | P1 / read | Проверка прав перемещения в объекте. |
| `tasks:stage:move ID STAGE_ID [--before ID или --after ID]` | [task.stages.movetask](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/stages/task-stages-move-task.md) / 1.0 | [wrapper: moveTask()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Stage/Service/Stage.php) | P1 / write | before/after взаимоисключающие; stage ≠ status; Scrum — отдельный API. |
| `tasks:stage:delete STAGE_ID --yes` | [task.stages.delete](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/stages/task-stages-delete.md) / 1.0 | [wrapper: delete()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Stage/Service/Stage.php) | P2 / delete | Удаление стадии. |

### Участники

| Команда и параметры | REST / версия | SDK | Приоритет / эффект | Ограничения |
| --- | --- | --- | --- | --- |
| `tasks:participants:set ID [--accomplices JSON] [--auditors JSON]` | [tasks.task.update](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/fields-rest-v3.md) / 1.0 | core-gap | P1 / write | V3 не принимает accomplices/auditors при add/update; legacy ACCOMPLICES/AUDITORS. Отдельная операция может частично завершиться после create. |
| `tasks:parent:set ID --parent ID` | [tasks.task.update](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/fields-rest-v3.md) / 3.0 | [wrapper: update()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Service/Task.php) | P1 / write | fields.parentId; иерархия подзадач отдельно от связей Ганта. |

## Отложенные методы индекса

| Метод | SDK | Почему отложен |
| --- | --- | --- |
| [OnTaskAdd](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/events-tasks/on-task-add.md) | core-gap | События: подписки и входящий обработчик вне синхронного CLI |
| [OnTaskUpdate](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/events-tasks/on-task-update.md) | core-gap | События: подписки и входящий обработчик вне синхронного CLI |
| [OnTaskDelete](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/events-tasks/on-task-delete.md) | core-gap | События: подписки и входящий обработчик вне синхронного CLI |
| [task.item.userfield.add](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/user-field/task-item-user-field-add.md) | [wrapper: add()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Userfield/Service/Userfield.php) | Пользовательские поля: управление схемой, отдельный будущий change |
| [task.item.userfield.update](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/user-field/task-item-user-field-update.md) | [wrapper: update()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Userfield/Service/Userfield.php) | Пользовательские поля: управление схемой, отдельный будущий change |
| [task.item.userfield.get](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/user-field/task-item-user-field-get.md) | [wrapper: get()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Userfield/Service/Userfield.php) | Пользовательские поля: управление схемой, отдельный будущий change |
| [task.item.userfield.getlist](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/user-field/task-item-user-field-get-list.md) | [wrapper: getList()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Userfield/Service/Userfield.php) | Пользовательские поля: управление схемой, отдельный будущий change |
| [task.item.userfield.delete](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/user-field/task-item-user-field-delete.md) | [wrapper: delete()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Userfield/Service/Userfield.php) | Пользовательские поля: управление схемой, отдельный будущий change |
| [task.item.userfield.gettypes](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/user-field/task-item-user-field-get-types.md) | [wrapper: getTypes()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Userfield/Service/Userfield.php) | Пользовательские поля: управление схемой, отдельный будущий change |
| [task.item.userfield.getfields](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/user-field/task-item-user-field-get-fields.md) | [wrapper: getFields()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Userfield/Service/Userfield.php) | Пользовательские поля: управление схемой, отдельный будущий change |
| [tasks.flow.Flow.create](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/flow/tasks-flow-flow-create.md) | [wrapper: create()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Flow/Service/Flow.php) | Потоки: распределение задач, отдельный будущий change |
| [tasks.flow.Flow.get](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/flow/tasks-flow-flow-get.md) | [wrapper: get()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Flow/Service/Flow.php) | Потоки: распределение задач, отдельный будущий change |
| [tasks.flow.Flow.update](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/flow/tasks-flow-flow-update.md) | [wrapper: update()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Flow/Service/Flow.php) | Потоки: распределение задач, отдельный будущий change |
| [tasks.flow.Flow.delete](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/flow/tasks-flow-flow-delete.md) | [wrapper: delete()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Flow/Service/Flow.php) | Потоки: распределение задач, отдельный будущий change |
| [tasks.flow.Flow.isExists](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/flow/tasks-flow-flow-is-exists.md) | [wrapper: isExists()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Flow/Service/Flow.php) | Потоки: распределение задач, отдельный будущий change |
| [tasks.flow.Flow.activate](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/tasks/flow/tasks-flow-flow-activate.md) | [wrapper: activate()](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/src/Services/Task/Flow/Service/Flow.php) | Потоки: распределение задач, отдельный будущий change |

Дополнительно за пределами этого каталога: Scrum и спринты, Шаблоны и повторяющиеся задачи, Потоки, Управление пользовательскими полями, События и webhooks, Загрузка локальных файлов через Disk, Массовые операции и таймеры, Собственный MCP server. Scrum использует [отдельный API](https://github.com/bitrix24/b24restdocs/blob/2b00d6bd3a845f4169ccb1ff6f0755f156903f4b/api-reference/sonet-group/scrum/index.md). Область шаблонов надо отдельно сверить с актуальной официальной документацией. SDK содержит `tasks.flow.Flow.pin`, которого нет в проверенном индексе задач: команда не добавлена до отдельной проверки источника.

## Проверки для будущей реализации

Следующий implementation change должен проверить на тестовом портале: список моих открытых задач; создание с creator/responsible; чтение и запись чата; result перед complete при обязательном результате; complete/approve при включённом контроле; отказ в правах; ручное время; дерево чек-листа; частичную пагинацию; транспортную неопределённость записи; удаления с --yes; стабильный JSON и exit codes. Это план acceptance, не выполненные тесты.

## Границы исследования и дальнейшие решения

Индекс прочитан целиком. Отдельные страницы детально сверены для v3 list/add/update/get/fields, chat.send, delegate, history, result.add/fromchatmessage, file.attach, stage.move, elapsed.add, gantt.link.list и dependence.add. Параметры остальных строк — эскиз; их надо сверить с отдельными страницами до реализации.

SDK inventory построен по literal Core calls Task services закреплённой версии; IM service не проверялся. Наличие wrapper не гарантирует runtime-совместимость. Сторонние CLI/MCP не устанавливались, tools/list официального сервера и запросы к порталу не выполнялись.

Перед реализацией принять состав MVP, стратегию смешанных API, auth/profile и JSON-контракт; затем создать отдельный OpenSpec change с требованиями и сценариями. Это исследование архивируется как завершённая документационная работа с `skip_specs: true`, без добавления действующих runtime requirements.
