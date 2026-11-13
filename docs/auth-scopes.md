# Проверка разрешений вебхука

[Вход и профили](auth.md) · [Настройки](config.md)

CLI проверяет scope выбранного входящего вебхука перед обращением к порталу. Проверка относится к маршрутам выбранного режима команды: локальная загрузка файла требует `disk`, прикрепление готового file ID — только разрешение задач; чтение и изменение чата через `im.*` требует `im`.

Требования поставляются вместе с CLI в манифесте. Его ревизия меняется вместе с требованиями и каталогом маршрутов. Версия CLI записывается для диагностики; сама по себе смена версии без изменения манифеста не требует запроса.

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

[`user.get`](https://apidocs.bitrix24.ru/api-reference/user/user-get.html) допускает `user`, `user_basic`, `user_brief`; доступные поля зависят от варианта. [`department.get`](https://apidocs.bitrix24.ru/api-reference/departments/department-get.html) требует `department`, [`disk.folder.uploadFile`](https://apidocs.bitrix24.ru/api-reference/disk/folder/disk-folder-upload-file.html) — `disk`, [`im.dialog.messages.get`](https://apidocs.bitrix24.ru/api-reference/chats/messages/im-dialog-messages-get.html), [`im.dialog.get`](https://apidocs.bitrix24.ru/api-reference/chats/im-dialog-get.html) (`chats:resolve`, стартовая позиция чтения чата задачи, прочтения участников для сведений о звонке), [`im.counters.get`](https://apidocs.bitrix24.ru/api-reference/chats/im-counters-get.html) (только `task:list --unread`), [`im.search.chat.list`](https://apidocs.bitrix24.ru/api-reference/chats/search/im-search-chat-list.html) (`chats:find`), [`im.message.add`](https://apidocs.bitrix24.ru/api-reference/chats/messages/im-message-add.html) (`chats:send`), [`im.chat.add`](https://apidocs.bitrix24.ru/api-reference/chats/im-chat-add.html) (`chats:add`), [`im.user.list.get`](https://apidocs.bitrix24.ru/api-reference/chats/users/im-user-list-get.html) (`users:presence`, последняя активность участников звонка), недокументированный `im.call.get` (данные звонка в чате) и изменение/удаление сообщений — `im`. `chats:find` и `chats:send --name` также требуют `sonet_group` для [`sonet_group.get`](https://apidocs.bitrix24.ru/api-reference/sonet-group/sonet-group-get.html) и `task|tasks` для поиска задач по заголовку; MCP-описание `sonet_group.get` называет scope `sonet`, CLI использует фактический код `sonet_group`, как и для `task:handover`.

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
