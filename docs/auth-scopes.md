# Проверка разрешений вебхука

[Вход и профили](auth.md) · [Настройки](config.md)

CLI проверяет scope выбранного входящего вебхука перед обращением к порталу. Проверка относится к маршрутам выбранного режима команды: локальная загрузка файла требует `disk`, прикрепление готового file ID — только разрешение задач; чтение и изменение чата через `im.*` требует `im`.

Требования поставляются вместе с CLI в манифесте. Его ревизия меняется вместе с требованиями и каталогом маршрутов. Версия CLI записывается для диагностики; сама по себе смена версии без изменения манифеста не требует запроса.

## Матрица разрешений

Матрица показывает, какие scope входящего вебхука нужны каждой команде этой сборки. Она строится из того же манифеста, по которому CLI проверяет права, поэтому совпадает с отчётом `auth:status`.

- **Обязательные scope** нужны при любом вызове команды. Запись через `/` означает альтернативы, первым указан рекомендуемый код: `task` / `tasks`, `user` / `user_basic` / `user_brief`. Подробнее об альтернативах — в разделе [«Источники требований»](#источники-требований).
- **Только при опциях** — scope, который проверяется лишь в указанном режиме. Например, `task:list` без `--unread` работает без `im`.
- **Необязательные** источники не блокируют команду: без scope часть данных помечается недоступной с причиной.
- **REST-маршруты** — все методы, которые команда может вызвать, с версией API.

Scope открывает группу методов, но не даёт прав на конкретную задачу, чат или файл: запросы выполняются с правами пользователя, создавшего вебхук.

<!-- scope-matrix:start -->

Ревизия манифеста: `f5605fc63a3b213871eb9120d7323e7e7c101ecc06ea97bc72222d903724d509`. То же значение `auth:status` показывает в `scopeManifestRevision`. Блок обновляется командой `make scope-matrix`.

### `auth:*`

| Команда | Обязательные scope | Только при опциях | Необязательные | REST-маршруты |
| --- | --- | --- | --- | --- |
| `auth:login` | не требуются | — | — | `profile` (1.0), `scope` (1.0) |
| `auth:status` | не требуются | — | — | `profile` (1.0), `scope` (1.0) |

### `chats:*`

| Команда | Обязательные scope | Только при опциях | Необязательные | REST-маршруты |
| --- | --- | --- | --- | --- |
| `chats:add` | `im` | — | — | `im.search.chat.list` (1.0), `im.chat.add` (1.0), `im.dialog.get` (1.0), `im.dialog.users.list` (1.0) |
| `chats:calls:list` | `im` | `task` / `tasks` — TARGET `task:N` | `call` — `call.followup.get` | `tasks.task.get` (3.0), `im.dialog.get` (1.0), `im.dialog.messages.get` (1.0), `im.call.get` (1.0), `im.user.list.get` (1.0), `call.followup.get` (3.0) |
| `chats:calls:show` | `im` | `task` / `tasks` — TARGET `task:N` | `call` — `call.followup.get` | `tasks.task.get` (3.0), `im.dialog.get` (1.0), `im.dialog.messages.get` (1.0), `im.call.get` (1.0), `im.user.list.get` (1.0), `call.followup.get` (3.0) |
| `chats:find` | `task` / `tasks`, `im`, `sonet_group` | — | — | `im.search.chat.list` (1.0), `sonet_group.get` (1.0), `tasks.task.list` (3.0), `tasks.task.get` (3.0), `im.dialog.get` (1.0) |
| `chats:messages:list` | `im` | `task` / `tasks` — TARGET `task:N` | `call` — `call.followup.get` | `tasks.task.get` (3.0), `im.dialog.get` (1.0), `im.dialog.messages.get` (1.0), `im.call.get` (1.0), `im.user.list.get` (1.0), `call.followup.get` (3.0) |
| `chats:resolve` | `im` | `task` / `tasks` — TARGET `task:N` | — | `tasks.task.get` (3.0), `im.dialog.get` (1.0) |
| `chats:send` | `im` | `task` / `tasks` — `--name` или TARGET `task:N`; `sonet_group` — `--name` | — | `im.search.chat.list` (1.0), `sonet_group.get` (1.0), `tasks.task.list` (3.0), `tasks.task.get` (3.0), `im.dialog.get` (1.0), `im.message.add` (1.0), `im.dialog.messages.get` (1.0) |

### `kb:*`

| Команда | Обязательные scope | Только при опциях | Необязательные | REST-маршруты |
| --- | --- | --- | --- | --- |
| `kb:article:add` | `note` | — | — | `note.document.tree.list` (3.0), `note.document.add` (3.0) |
| `kb:article:list` | `note` | — | — | `note.document.tree.list` (3.0) |
| `kb:article:search` | `note` | — | — | `note.document.search.list` (3.0) |
| `kb:article:show` | `note` | — | — | `note.document.get` (3.0) |
| `kb:article:update` | `note` | — | — | `note.document.update` (3.0) |
| `kb:list` | `note` | — | — | `note.collection.list` (3.0) |

### `task:*`

| Команда | Обязательные scope | Только при опциях | Необязательные | REST-маршруты |
| --- | --- | --- | --- | --- |
| `task:access:show` | `task` / `tasks` | — | — | `tasks.task.access.get` (3.0) |
| `task:add` | `task` / `tasks` | — | — | `tasks.task.add` (1.0), `tasks.task.getFields` (1.0), `tasks.task.add` (3.0), `tasks.task.get` (1.0), `tasks.task.update` (1.0) |
| `task:approve` | `task` / `tasks` | — | — | `tasks.task.get` (3.0), `tasks.task.access.get` (3.0), `tasks.task.approve` (1.0) |
| `task:assign` | `task` / `tasks` | — | — | `tasks.task.update` (3.0) |
| `task:chat:delete` | `task` / `tasks`, `im` | — | — | `tasks.task.get` (3.0), `im.dialog.get` (1.0), `im.message.delete` (1.0), `im.dialog.messages.get` (1.0) |
| `task:chat:list` | `task` / `tasks`, `im` | — | `call` — `call.followup.get` | `tasks.task.get` (3.0), `im.dialog.get` (1.0), `im.dialog.messages.get` (1.0), `im.call.get` (1.0), `im.user.list.get` (1.0), `call.followup.get` (3.0) |
| `task:chat:send` | `task` / `tasks`, `im` | — | — | `tasks.task.get` (3.0), `im.dialog.get` (1.0), `tasks.task.chat.message.send` (3.0), `im.dialog.messages.get` (1.0) |
| `task:chat:update` | `task` / `tasks`, `im` | — | — | `tasks.task.get` (3.0), `im.dialog.get` (1.0), `im.message.update` (1.0), `im.dialog.messages.get` (1.0) |
| `task:checklist:add` | `task` / `tasks` | — | — | `task.checklistitem.add` (1.0) |
| `task:checklist:item:add` | `task` / `tasks` | — | — | `task.checklistitem.getlist` (1.0), `task.checklistitem.add` (1.0) |
| `task:checklist:item:complete` | `task` / `tasks` | — | — | `task.checklistitem.getlist` (1.0), `task.checklistitem.complete` (1.0) |
| `task:checklist:item:delete` | `task` / `tasks` | — | — | `task.checklistitem.getlist` (1.0), `task.checklistitem.delete` (1.0) |
| `task:checklist:item:list` | `task` / `tasks` | — | — | `task.checklistitem.getlist` (1.0) |
| `task:checklist:item:renew` | `task` / `tasks` | — | — | `task.checklistitem.getlist` (1.0), `task.checklistitem.renew` (1.0) |
| `task:checklist:item:update` | `task` / `tasks` | — | — | `task.checklistitem.getlist` (1.0), `task.checklistitem.update` (1.0) |
| `task:checklist:list` | `task` / `tasks` | — | — | `task.checklistitem.getlist` (1.0) |
| `task:complete` | `task` / `tasks` | — | — | `tasks.task.get` (3.0), `tasks.task.access.get` (3.0), `tasks.task.complete` (1.0), `tasks.task.result.list` (3.0) |
| `task:deadline:set` | `task` / `tasks` | — | — | `tasks.task.update` (3.0) |
| `task:defer` | `task` / `tasks` | — | — | `tasks.task.get` (3.0), `tasks.task.access.get` (3.0), `tasks.task.defer` (1.0) |
| `task:delete` | `task` / `tasks` | — | — | `tasks.task.delete` (3.0), `tasks.task.get` (3.0) |
| `task:disapprove` | `task` / `tasks` | — | — | `tasks.task.get` (3.0), `tasks.task.access.get` (3.0), `tasks.task.disapprove` (1.0) |
| `task:fields:list` | `task` / `tasks` | — | — | `tasks.task.field.list` (3.0), `tasks.task.field.get` (3.0) |
| `task:file:attach` | `task` / `tasks` | `disk` — локальный файл `--file` | — | `tasks.task.file.attach` (3.0), `disk.folder.uploadFile` (1.0) |
| `task:find` | `task` / `tasks` | — | — | `tasks.task.list` (3.0), `tasks.task.list` (1.0), `tasks.task.get` (1.0), `tasks.task.get` (3.0) |
| `task:handover` | `task` / `tasks`, `sonet_group` | — | — | `sonet_group.user.get` (1.0), `tasks.task.list` (3.0), `tasks.task.get` (3.0), `tasks.task.update` (3.0), `tasks.task.access.get` (3.0), `tasks.task.result.list` (3.0), `tasks.task.start` (1.0), `tasks.task.pause` (1.0), `tasks.task.defer` (1.0), `tasks.task.complete` (1.0), `tasks.task.approve` (1.0), `tasks.task.disapprove` (1.0) |
| `task:history:list` | `task` / `tasks` | — | — | `tasks.task.history.list` (1.0) |
| `task:legacy:comment:list` | `task` / `tasks` | — | — | `task.commentitem.getlist` (1.0) |
| `task:list` | `task` / `tasks` | `im` — `--unread` | — | `tasks.task.list` (3.0), `tasks.task.list` (1.0), `tasks.task.get` (1.0), `tasks.task.get` (3.0), `im.counters.get` (1.0) |
| `task:my` | `task` / `tasks` | — | — | `tasks.task.list` (3.0) |
| `task:participants:set` | `task` / `tasks` | — | — | `tasks.task.update` (1.0), `tasks.task.get` (3.0) |
| `task:pause` | `task` / `tasks` | — | — | `tasks.task.get` (3.0), `tasks.task.access.get` (3.0), `tasks.task.pause` (1.0) |
| `task:renew` | `task` / `tasks` | — | — | `tasks.task.get` (3.0), `tasks.task.access.get` (3.0), `tasks.task.renew` (1.0) |
| `task:report` | `task` / `tasks` | — | — | `tasks.task.list` (1.0) |
| `task:result:add` | `task` / `tasks` | — | — | `tasks.task.result.add` (3.0) |
| `task:result:delete` | `task` / `tasks` | — | — | `tasks.task.result.list` (3.0), `tasks.task.result.delete` (3.0) |
| `task:result:list` | `task` / `tasks` | — | — | `tasks.task.result.list` (3.0) |
| `task:result:update` | `task` / `tasks` | — | — | `tasks.task.result.list` (3.0), `tasks.task.result.update` (3.0) |
| `task:show` | `task` / `tasks` | `user` / `user_basic` / `user_brief` — `--select` с полями участников кроме `id` | — | `tasks.task.get` (3.0), `tasks.task.get` (1.0), `user.get` (1.0) |
| `task:start` | `task` / `tasks` | — | — | `tasks.task.get` (3.0), `tasks.task.access.get` (3.0), `tasks.task.start` (1.0) |
| `task:status:durations` | `task` / `tasks` | — | — | `tasks.task.history.list` (1.0), `tasks.task.get` (3.0), `tasks.task.list` (3.0) |
| `task:template:add` | `task` / `tasks` | — | — | `tasks.template.add` (1.0) |
| `task:template:delete` | `task` / `tasks` | — | — | `tasks.template.get` (1.0), `tasks.template.delete` (1.0) |
| `task:template:fields:list` | `task` / `tasks` | — | — | `tasks.template.fields` (1.0) |
| `task:template:show` | `task` / `tasks` | — | — | `tasks.template.get` (1.0) |
| `task:template:update` | `task` / `tasks` | — | — | `tasks.template.update` (1.0) |
| `task:time:add` | `task` / `tasks` | — | — | `task.elapseditem.add` (1.0) |
| `task:time:delete` | `task` / `tasks` | — | — | `task.elapseditem.delete` (1.0), `task.elapseditem.getlist` (1.0) |
| `task:time:list` | `task` / `tasks` | — | — | `task.elapseditem.getlist` (1.0) |
| `task:time:show` | `task` / `tasks` | — | — | `tasks.task.get` (3.0) |
| `task:time:update` | `task` / `tasks` | — | — | `task.elapseditem.update` (1.0), `task.elapseditem.getlist` (1.0) |
| `task:update` | `task` / `tasks` | — | — | `tasks.task.getFields` (1.0), `tasks.task.update` (3.0), `tasks.task.update` (1.0) |

### `users:*`

| Команда | Обязательные scope | Только при опциях | Необязательные | REST-маршруты |
| --- | --- | --- | --- | --- |
| `users:colleagues` | `user` / `user_basic` / `user_brief` | — | — | `user.current` (1.0), `user.get` (1.0) |
| `users:current` | `user` / `user_basic` / `user_brief` | — | — | `user.current` (1.0) |
| `users:find` | `user` / `user_basic` / `user_brief` | — | — | `user.get` (1.0) |
| `users:manager` | `user` / `user_basic` / `user_brief`, `department` | — | — | `user.current` (1.0), `department.get` (1.0), `user.get` (1.0) |
| `users:presence` | `im` | `task` / `tasks` — TARGET `task:N` | — | `tasks.task.get` (3.0), `im.dialog.get` (1.0), `im.dialog.users.list` (1.0), `im.user.list.get` (1.0) |

### Без обращения к порталу

Эти команды не требуют scope: `auth:list`, `auth:logout`, `auth:switch`, `completion`, `config:get`, `config:list`, `config:set`, `config:unset`, `doctor:problem-trajectory`, `help`, `list`.

<!-- scope-matrix:end -->

## Если портал отказывает в доступе

Права вебхука выбирают в блоке *Настройка прав*: при создании (*Разработчикам → Готовые сценарии → Другое → Входящий вебхук*) или позже, открыв вебхук в списке *Интеграции*. Порядок описан в [официальной инструкции](https://apidocs.bitrix24.ru/first-steps/first-rest-api-call.html), коды и названия scope — в [справочнике](https://apidocs.bitrix24.ru/api-reference/scopes/permissions.html).

Сначала выполните самодиагностику с тем же выбором подключения, что и у отказавшей команды:

```bash
b24cli auth:status work
```

Команда показывает владельца вебхука, выданные и недостающие scope и `potentiallyAffectedCommands` — команды, которым прав может не хватить. Перебирать команды по одной не нужно.

| Признак | Причина | Что делать |
| --- | --- | --- |
| `scope-missing`, exit 4, сообщение `is missing webhook scopes` | CLI по сохранённой проверке знает, что scope не выдан, и не обращается к порталу | Отметить перечисленные scope в настройках вебхука, затем `auth:status` |
| `permission-denied`, `apiErrorCode=insufficient_scope`, сообщение `Bitrix24 denied the requested scope` | Портал отказал по scope: права вебхука изменились после проверки | То же: добавить scope и выполнить `auth:status`, который обновит снимок |
| `permission-denied` без `insufficient_scope`, сообщение `Bitrix24 denied this operation` | Портал отказал пользователю, создавшему вебхук, в действии с объектом | Проверить, что этот пользователь видит задачу, чат или файл и может выполнить действие; для задачи — `task:access:show TASK_ID`. Галочки scope здесь не помогут |
| `policy-denied`, exit 4 | Выбрана политика `strict-rest3` (`--api-policy`, `apiPolicy` в runtime JSON `--config` или `B24CLI_API_POLICY`), которая запрещает маршруты REST 1.0 и `im.*` | Запустить команду с политикой по умолчанию `task-v3`; права вебхука менять не нужно |
| Строка `This operation used an explicitly admitted REST 1.0 route.` в stderr | Сведения: команда использовала маршрут REST 1.0, разрешённый политикой `task-v3` | Ничего. Это не ограничение портала и не white-list маршрутов |

Если URL вебхука изменился, замените его в профиле: `b24cli auth:login work --replace`.

## Состояние

`state.json` лежит рядом с `config.json` в выбранном каталоге конфигурации. Это служебный кеш, его можно удалить для повторной проверки. Формат config.json и credential files не меняется. State использует private permissions/Windows ACL, отдельный `.state.lock` и атомарную публикацию.

Корневые поля: `version: 1` и `connections: {}`. Ключ записи — SHA256 от credentials и выбранного source/profile/path. Вебхук, token и user ID не сохраняются. Разные источники и ротация вебхука не наследуют проверку другого подключения: после `auth:login NAME --replace` снимок прежнего вебхука не используется.

Каждая запись содержит:

- `grantedScopes`: список полученных строковых кодов;
- `lastSuccessfulCheckAt`: UTC дата успешной проверки либо null;
- `checkedWithAppVersion`: версия CLI, выполнившая успешную проверку (первую попытку, если успеха ещё нет);
- `scopeManifestRevision`: hash манифеста успешной проверки;
- `lastAttemptAt`, `nextCheckAt`: UTC даты последней попытки и следующей автоматической проверки;
- `status`: fresh, stale или unknown.

Снимок действует 86400 секунд. Ошибка обновления сохраняет успешный снимок и назначает повтор через 300 секунд. Stale/unknown не создаёт новый запрет команды. Если state повреждён, имеет неизвестную версию, небезопасный путь или недоступен для записи, автоматическая проверка показывает unknown; ручная диагностика сообщает ошибку. Проблемный файл не перезаписывается.

## Источники требований

Права получаются через [`scope`](https://apidocs.bitrix24.ru/api-reference/common/system/scope.html) без `full`. Нельзя использовать `full=true`: это набор возможностей портала.

Владельца вебхука определяет [`profile`](https://apidocs.bitrix24.ru/api-reference/common/users/profile.html): метод относится к базовым и не требует отдельного разрешения, поэтому владелец виден и у вебхука без `user`. В манифесте `profile` и `scope` описаны как маршруты без scope. Для неактивного пользователя `profile` возвращает пустой список вместо объекта — CLI считает это ошибкой подключения.

[`Справочник scope`](https://apidocs.bitrix24.ru/api-reference/scopes/permissions.html) рекомендует `task` и называет `tasks` устаревшим, а [страница REST3 tasks.task.get](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-get-rest-v3.html) указывает `tasks`. Поэтому task-группа блокируется только при отсутствии обоих. Это консервативное решение из-за расхождения документации; при настройке выбирайте `task`. Выданные коды не переименовываются.

[`user.get`](https://apidocs.bitrix24.ru/api-reference/user/user-get.html) допускает `user`, `user_basic`, `user_brief`; доступные поля зависят от варианта. [`department.get`](https://apidocs.bitrix24.ru/api-reference/departments/department-get.html) требует `department`, [`disk.folder.uploadFile`](https://apidocs.bitrix24.ru/api-reference/disk/folder/disk-folder-upload-file.html) — `disk`, [`im.dialog.messages.get`](https://apidocs.bitrix24.ru/api-reference/chats/messages/im-dialog-messages-get.html), [`im.dialog.get`](https://apidocs.bitrix24.ru/api-reference/chats/im-dialog-get.html) (`chats:resolve`, стартовая позиция чтения чата задачи и поиска отправленного `task:chat:send` сообщения, прочтения участников для сведений о звонке), [`im.counters.get`](https://apidocs.bitrix24.ru/api-reference/chats/im-counters-get.html) (только `task:list --unread`), [`im.search.chat.list`](https://apidocs.bitrix24.ru/api-reference/chats/search/im-search-chat-list.html) (`chats:find`), [`im.message.add`](https://apidocs.bitrix24.ru/api-reference/chats/messages/im-message-add.html) (`chats:send`), [`im.chat.add`](https://apidocs.bitrix24.ru/api-reference/chats/im-chat-add.html) (`chats:add`), [`im.user.list.get`](https://apidocs.bitrix24.ru/api-reference/chats/users/im-user-list-get.html) (`users:presence`, последняя активность участников звонка), недокументированный `im.call.get` (данные звонка в чате) и изменение/удаление сообщений — `im`. `chats:find` и `chats:send --name` также требуют `sonet_group` для [`sonet_group.get`](https://apidocs.bitrix24.ru/api-reference/sonet-group/sonet-group-get.html) и `task|tasks` для поиска задач по заголовку; MCP-описание `sonet_group.get` называет scope `sonet`, CLI использует фактический код `sonet_group`, как и для `task:handover`.

Команды Базы знаний 2.0 `kb:*` используют только REST 3.0 и требуют `note`: [`note.collection.list`](https://apidocs.bitrix24.ru/api-reference/rest-v3/note/collection/note-collection-list.html) (`kb:list`), [`note.document.tree.list`](https://apidocs.bitrix24.ru/api-reference/rest-v3/note/document/note-document-tree-list.html) (`kb:article:list` и проверка дубля `kb:article:add`), [`note.document.search.list`](https://apidocs.bitrix24.ru/api-reference/note/document/note-document-search-list.html) (`kb:article:search`), [`note.document.get`](https://apidocs.bitrix24.ru/api-reference/rest-v3/note/document/note-document-get.html) (`kb:article:show`), [`note.document.add`](https://apidocs.bitrix24.ru/api-reference/note/document/note-document-add.html) (`kb:article:add`), [`note.document.update`](https://apidocs.bitrix24.ru/api-reference/note/document/note-document-update.html) (`kb:article:update`). Нехватка `note` блокирует только `kb:*`. Методы доступны на коммерческих тарифах: на остальных портал отвечает `ACCESS_DENIED`, CLI возвращает `permission-denied`.

[`call.followup.get`](https://apidocs.bitrix24.ru/api-reference/telephony/follow-up/call-followup-get.html) (REST 3.0, AI Follow-up звонка) требует `call`, но это необязательный источник: нехватка `call` не блокирует команды чтения истории чата и не попадает в список затронутых команд `auth:status`. Без неё подтверждённые участники звонка помечаются недоступными с причиной `followup-scope-missing`.

Наличие scope не подтверждает права на конкретную задачу, чат, файл или доступность API на тарифе.

## Проверка и исправление

```bash
b24cli auth:status work --json
b24cli auth:status --env-file /absolute/path/portal.env
```

Команда сначала определяет владельца вебхука, затем всегда обновляет снимок прав. Отчёт содержит `owner` с полями REST 1.0 `ID`, `NAME`, `LAST_NAME`, `ADMIN`. При полной совместимости код завершения — 0. При missing scope — 4: ответ содержит `error.code=scope-missing` и `error.details.scopeValidation` с владельцем, выданными и недостающими разрешениями, временем, подключением и `potentiallyAffectedCommands`. Этот список включает условные зависимости опций. Обычная команда проверяет только фактический план. При неудачной проверке прав — код 1 и `scope-check-unavailable`.

Если `profile` не ответил или пользователь вебхука неактивен, команда завершается с кодом 1 до чтения прав: снимок не обновляется. Для неактивного пользователя код ошибки — `connection-unavailable`.

При ошибке human/plain сохраняют полный отчёт в stderr: владельца, права, дату проверки, ревизию манифеста, подключение и потенциально затронутые команды. В plain поля отчёта разделены табуляцией; stdout остаётся пустым. JSON возвращает отчёт в `error.details.scopeValidation`.

Добавьте недостающие права в настройках входящего вебхука, затем повторите проверку с теми же параметрами выбора подключения. Если изменился сам URL/token, замените его: `b24cli auth:login NAME --replace`. CLI не меняет разрешения на портале.

`auth:login` без `--skip-check` сохраняет первый снимок сразу при входе, поэтому следующая REST-команда не запрашивает права повторно. Нехватка прав вход не отменяет: профиль сохраняется, а отчёт выводится в stderr с кодом 0. Если права при входе прочитать не удалось, снимок помечается unknown.

Автоматическая проверка выполняется при первом реальном REST-использовании, смене credentials/манифеста и по истечении суток. Внутри TTL сеть не добавляется. Подтверждённая нехватка блокирует только затронутую команду до её первого REST-вызова; отсутствие `im` не блокирует обычный список задач, но блокирует `task:list --unread`; поэтому `task:list` попадает в `potentiallyAffectedCommands` с `im`. Успешный JSON содержит `meta.scopeValidation`; при stale/unknown human/plain выводят предупреждение в stderr.

Help/version/list/completion, локальные `auth:list`, `auth:switch`, `auth:logout`, все `config:*`, `auth:login --skip-check` и dry-run не открывают state и не добавляют проверку. `auth:status --dry-run` показывает план без сети. Существующие remote preflight в других dry-run сохраняются.

`strict-rest3` использует только свежий кеш и не выполняет REST1 `scope`; при необходимости обновления статус unknown/stale сохраняет допустимый REST3 маршрут. Явная `auth:status` и сетевая проверка `auth:login` требуют обычной политики `task-v3` и отклоняются strict-rest3 до ввода секрета и подключения; `auth:login --skip-check` сохраняет профиль без REST-вызовов.

Точный ответ API `insufficient_scope` инвалидирует снимок и ограничивает последующую перепроверку интервалом backoff. Другие отказы доступа сохраняют свою причину. Ни один изменяющий запрос автоматически не повторяется.

Для сохранения state в подключённом Docker-каталоге нужен доступ на запись. Используйте существующий параметр `B24CLI_CONFIG_WRITE=1` вместе с `B24CLI_CONFIG_DIR` при запуске через Make. При read-only mount автоматическая проверка сообщает unknown и не перезаписывает конфигурацию.

Шаблоны: `task:template:show`, `task:template:fields:list`, `task:template:add/update/delete`
используют только `tasks.template.get/fields/add/update/delete` (REST 1.0),
документированный scope `task`; группа `task|tasks` сохранена по общему правилу
выше. Отдельных user/IM/disk разрешений эти команды не запрашивают.

## Передача задач проекта

`task:handover` требует группу `task|tasks` и `sonet_group`: [sonet_group.user.get](https://apidocs.bitrix24.ru/api-reference/sonet-group/members/sonet-group-user-get.html) проверяет активное членство обоих сотрудников. Отсутствие `sonet_group` блокирует только эту зависимость и не добавляет требование к `task:assign` или `task:list`. `strict-rest3` отклоняет handover до API, включая dry-run. Членство само по себе не предоставляет право переназначить задачу.

Live-приёмка EMP-09 (#170) подтвердила код `sonet_group` в реальном ответе scope и успешный sonet_group.user.get с этим подключением. MCP-описание метода называет sonet; CLI использует фактический grant code и сохраняет это документационное расхождение явно.
