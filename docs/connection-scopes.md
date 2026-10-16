# Проверка разрешений вебхука

CLI проверяет scope выбранного входящего вебхука перед обращением к порталу. Проверка относится к маршрутам выбранного режима команды: локальная загрузка файла требует `disk`, прикрепление готового file ID — только разрешение задач; чтение и изменение чата через `im.*` требует `im`.

Требования поставляются вместе с CLI в манифесте. Его ревизия меняется вместе с требованиями и каталогом маршрутов. Версия CLI записывается для диагностики; сама по себе смена версии без изменения манифеста не требует запроса.

## Состояние

`state.json` лежит рядом с `config.json` в выбранном каталоге конфигурации. Это служебный кеш, его можно удалить для повторной проверки. Формат config.json и credential files не меняется. State использует private permissions/Windows ACL, отдельный `.state.lock` и атомарную публикацию.

Корневые поля: `version: 1` и `connections: {}`. Ключ записи — SHA256 от credentials и выбранного source/profile/path. Вебхук, token и user ID не сохраняются. Разные источники и ротация вебхука не наследуют проверку другого подключения.

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

[`Справочник scope`](https://apidocs.bitrix24.ru/api-reference/scopes/permissions.html) рекомендует `task` и называет `tasks` устаревшим, а [страница REST3 tasks.task.get](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-get-rest-v3.html) указывает `tasks`. Поэтому task-группа блокируется только при отсутствии обоих. Это консервативное решение из-за расхождения документации; при настройке выбирайте `task`. Выданные коды не переименовываются.

[`user.get`](https://apidocs.bitrix24.ru/api-reference/user/user-get.html) допускает `user`, `user_basic`, `user_brief`; доступные поля зависят от варианта. [`department.get`](https://apidocs.bitrix24.ru/api-reference/departments/department-get.html) требует `department`, [`disk.folder.uploadFile`](https://apidocs.bitrix24.ru/api-reference/disk/folder/disk-folder-upload-file.html) — `disk`, [`im.dialog.messages.get`](https://apidocs.bitrix24.ru/api-reference/chats/messages/im-dialog-messages-get.html) и изменение/удаление сообщений — `im`.

Наличие scope не подтверждает права на конкретную задачу, чат, файл или доступность API на тарифе.

## Проверка и исправление

```bash
b24cli connection:check work --json
b24cli connection:check --env-file /absolute/path/portal.env
```

Команда всегда обновляет снимок. При полной совместимости код завершения — 0. При missing scope — 4: ответ содержит `error.code=scope-missing` и `error.details.scopeValidation` с выданными и недостающими разрешениями, временем, подключением и `potentiallyAffectedCommands`. Этот список включает условные зависимости опций. Обычная команда проверяет только фактический план. При неудачной проверке — код 1 и `scope-check-unavailable`.

При ошибке human/plain сохраняют полный отчёт в stderr: права, дату проверки, ревизию манифеста, подключение и потенциально затронутые команды. В plain поля отчёта разделены табуляцией; stdout остаётся пустым. JSON возвращает отчёт в `error.details.scopeValidation`.

Добавьте недостающие права в настройках входящего вебхука, затем повторите проверку с теми же параметрами выбора подключения. Если изменился сам URL/token, обновите credentials документированным способом. CLI не меняет разрешения на портале.

Автоматическая проверка выполняется при первом реальном REST-использовании, смене credentials/манифеста и по истечении суток. Внутри TTL сеть не добавляется. Подтверждённая нехватка блокирует только затронутую команду до её первого REST-вызова; отсутствие `im` не блокирует обычный список задач. Успешный JSON содержит `meta.scopeValidation`; при stale/unknown human/plain выводят предупреждение в stderr.

Help/version/list/completion, локальные `connection:add/list/show/use/remove` и dry-run не открывают state и не добавляют проверку. `connection:check --dry-run` показывает план без сети. Существующие remote preflight в других dry-run сохраняются.

`strict-rest3` использует только свежий кеш и не выполняет REST1 `scope`; при необходимости обновления статус unknown/stale сохраняет допустимый REST3 маршрут. Явная `connection:check` требует обычной политики `task-v3` и отклоняется strict-rest3 до подключения.

Точный ответ API `insufficient_scope` инвалидирует снимок и ограничивает последующую перепроверку интервалом backoff. Другие отказы доступа сохраняют свою причину. Ни один изменяющий запрос автоматически не повторяется.

Для сохранения state в подключённом Docker-каталоге нужен доступ на запись. Используйте существующий параметр `B24CLI_CONFIG_WRITE=1` вместе с `B24CLI_CONFIG_DIR` при запуске через Make. При read-only mount автоматическая проверка сообщает unknown и не перезаписывает конфигурацию.

Шаблоны: `task:template:show`, `task:template:fields:list`, `task:template:add/update/delete`
используют только `tasks.template.get/fields/add/update/delete` (REST 1.0),
документированный scope `task`; группа `task|tasks` сохранена по общему правилу
выше. Отдельных user/IM/disk разрешений эти команды не запрашивают.
