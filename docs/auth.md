# Вход и профили b24cli

[Краткая настройка](../README.md#подключения-и-конфигурация) · [Настройки](config.md) · [Проверка разрешений](auth-scopes.md)

Команды `auth:*` отвечают за учётные данные и выбор портала, команды `config:*` — за
несекретные настройки. Вебхук вводится один раз при входе; значения по умолчанию
меняются отдельно и без повторного ввода секрета.

| Команда | Что делает | Сеть |
| --- | --- | --- |
| `auth:login NAME` | Проверяет вебхук и сохраняет его как профиль | да; с `--skip-check` — нет |
| `auth:status [NAME]` | Показывает владельца вебхука, его права и затронутые команды | да |
| `auth:list [NAME]` | Показывает сохранённые профили и фактическое подключение | нет |
| `auth:switch NAME` | Выбирает профиль по умолчанию | нет |
| `auth:logout NAME` | Удаляет профиль и его учётные данные | нет |

<a id="incoming-webhook"></a>
## Где взять входящий вебхук

b24cli подключается к порталу через входящий вебхук: это URL, по которому внешняя
программа вызывает REST API Битрикс24 от имени пользователя, создавшего вебхук.
Создать вебхук может любой сотрудник в пределах своих прав, если администратор
разрешил это на портале.

1. В левом меню Битрикс24 откройте раздел *Разработчикам*.
2. Перейдите на вкладку *Готовые сценарии → Другое → Входящий вебхук*. Откроется окно
   с уже сгенерированным кодом вебхука.
3. В блоке *Настройка прав* отметьте права для нужных команд. Рекомендуемый набор
   и что без каждого права не работает — в таблице
   [«Права вебхука»](../README.md#права-вебхука), точное соответствие команд и прав —
   в [матрице разрешений](auth-scopes.md#матрица-разрешений).
4. Скопируйте URL из поля *Вебхук для вызова rest api*.
5. Нажмите *Создать*. Вебхук появится в списке *Интеграции*.
6. Выполните `b24cli auth:login work` и вставьте скопированный URL в ответ на запрос.

Пошаговая инструкция со снимками экрана — в официальной статье
[«Как выполнить свой первый запрос к API»](https://apidocs.bitrix24.ru/first-steps/first-rest-api-call.html).
На порталах с английским интерфейсом названия пунктов другие, см.
[англоязычную документацию REST API](https://apidocs.bitrix24.com/).

Если пункта *Входящий вебхук* нет, право создавать вебхуки вам не выдано. Попросите
администратора портала
[открыть доступ к созданию вебхуков](https://apidocs.bitrix24.ru/first-steps/first-rest-api-call.html#webhook-app-access):
*Настройки → Настройки Битрикс24 → Безопасность → Интеграции Битрикс24 → Кому разрешить
создавать входящие вебхуки*. Если вместо окна вебхука открылось сообщение о тарифе,
REST API на портале недоступен — см.
[«Подключить доступ к REST API»](https://apidocs.bitrix24.ru/first-steps/access-to-rest-api.html).

### Из чего состоит URL

```text
https://your-company.bitrix24.ru/rest/1/<секретный код>/
```

- `your-company.bitrix24.ru` — адрес портала; он же становится `hostname` профиля;
- `1` — ID пользователя, создавшего вебхук: запросы выполняются с его правами;
- `<секретный код>` — ключ доступа вебхука.

Вставляйте URL целиком, как он показан в поле *Вебхук для вызова rest api*.
Имя метода в конце (`profile`, `user.current.json` и т. п.) добавлять не нужно.

### Почему ввод скрыт

Секретный код в пути URL работает как пароль: любой, у кого есть URL, вызывает
REST API с правами создателя вебхука в пределах отмеченных прав. Поэтому `auth:login`
не принимает вебхук аргументом и не показывает введённый текст, а сохраняет его в
отдельном защищённом файле (см. [«Хранение секрета»](#хранение-секрета)). Не передавайте
URL в аргументах команд, не оставляйте его в истории shell, логах и репозиториях.

В интерактивном терминале `auth:login` перед вводом напоминает, что вставить и где это
взять:

```text
Вставьте URL входящего вебхука Битрикс24. Ввод скрыт: в адресе есть секретный код.
Где взять: Разработчикам → Готовые сценарии → Другое → Входящий вебхук, поле «Вебхук для вызова rest api».
Пошаговая инструкция и права: https://github.com/mesilov/bitrix24-cli/blob/main/docs/auth.md#incoming-webhook
URL входящего вебхука:
```

Подсказка и запрос выводятся в stderr; с `--webhook-stdin` их нет.

### Если вебхук утёк или перестал работать

Если `auth:login` или `auth:status` сообщают об ошибке авторизации (`INVALID_CREDENTIALS`),
вебхук удалён, истёк или URL скопирован с ошибкой. Откройте вебхук в списке *Интеграции*
раздела *Разработчикам*. Если код мог попасть к посторонним или срок действия истёк,
нажмите *Перегенерировать* под полем *Вебхук для вызова rest api* и подтвердите действие.
Затем сохраните новый URL, не теряя настроек профиля:

```sh
b24cli auth:login work --replace
```

Права меняются в том же окне, в блоке *Настройка прав*; после сохранения выполните
`b24cli auth:status work`. Как различать отказы доступа — в разделе
[«Если портал отказывает в доступе»](auth-scopes.md#если-портал-отказывает-в-доступе).

## Первый запуск

Именованные профили `work`, `pet`, `clientA`, `clientB` описывают отдельные порталы.
Подготовьте [входящий вебхук портала](#incoming-webhook) с правами для нужных команд.
`auth:login` запросит его скрытым вводом в терминале. Не передавайте полный URL
в аргументах команды или строке shell history.

```sh
b24cli auth:login work
b24cli auth:status work
b24cli task:list --json
```

До сохранения `auth:login` проверяет вебхук на портале двумя запросами REST 1.0:
[`profile`](https://apidocs.bitrix24.ru/api-reference/common/users/profile.html) определяет
владельца вебхука и не требует отдельного разрешения,
[`scope`](https://apidocs.bitrix24.ru/api-reference/common/system/scope.html) читает выданные права.

| Результат проверки | Что происходит | Код |
| --- | --- | --- |
| Вебхук действителен, прав достаточно | Профиль сохранён, выведены hostname и владелец вебхука | 0 |
| Вебхук действителен, части прав нет | Профиль сохранён; недостающие права и затронутые команды — в stderr | 0 |
| Права прочитать не удалось | Профиль сохранён с предупреждением; повторите `auth:status` | 0 |
| Неверный вебхук, ошибка сети, неактивный пользователь | Ничего не сохранено | 1 |

Первый профиль становится профилем по умолчанию, поэтому следующие команды работают
без `--profile`. `auth:status` даёт точный ответ о правах: при нехватке разрешений
он завершается с кодом 4. Подробности — в [руководстве по проверке разрешений](auth-scopes.md).

Конфигурация и учётные данные хранятся отдельно от бинарника.
[Каталог, формат и права доступа](config.md#каталог-и-файлы) · [JSON Schema](config.schema.json).

## Linux / macOS (Bash / zsh)

```sh
b24cli auth:login work
# Вебхук вводится скрыто в терминале.
b24cli auth:status work
b24cli config:set default.task.groupId 42
b24cli config:set default.task.creatorId 1
b24cli config:set default.task.responsibleId 2
b24cli task:add --title "Подготовить договор" --dry-run --json
# Второй портал: профиль по умолчанию остаётся прежним.
b24cli auth:login pet
b24cli task:list --profile pet --json
b24cli task:list --env-file /secure/portal.env --json
```

## Windows (PowerShell)

```powershell
.\b24cli.exe auth:login work
.\b24cli.exe auth:status work
.\b24cli.exe config:set default.task.groupId 42
$OutputEncoding = [Console]::InputEncoding = [System.Text.UTF8Encoding]::new($false)
Get-Content -Raw -Encoding UTF8 C:\secure\webhook.txt | .\b24cli.exe auth:login pet --webhook-stdin -n
.\b24cli.exe task:list --profile work --json
.\b24cli.exe task:list --env-file 'C:\secure\portal.env' --json
```

## Windows (cmd.exe)

```bat
b24cli.exe auth:login work --webhook-stdin -n < C:\secure\webhook.txt
b24cli.exe auth:status work
b24cli.exe task:list --profile work --json
b24cli.exe task:list --env-file C:\secure\portal.env --json
```

## Автоматизация и CI

Секрет читается из перенаправленного stdin только с явным `--webhook-stdin`;
`-n` без этого флага завершается ошибкой до ожидания ввода.
`--skip-check` сохраняет профиль без запросов к порталу: для офлайна, сборки образов
и политики `strict-rest3`, которая запрещает проверку через REST 1.0.

```sh
b24cli auth:login ci --webhook-stdin --skip-check -n < /secure/webhook.txt
b24cli config:set default.task.groupId 42 --profile ci
b24cli auth:status ci --json
```

Без `--skip-check` тот же вызов проверяет вебхук и подходит для первого запуска агента:
неверный секрет виден сразу, а не на первой рабочей команде.

## Несколько порталов

```sh
b24cli auth:login clientA
b24cli auth:list
b24cli auth:switch clientA
```

Новый профиль не становится профилем по умолчанию, если тот уже выбран: добавление
второго портала не переключает команды, меняющие данные. Переключение выполняет только
`auth:switch`. `auth:list` работает локально: показывает `profile`, `name`, `hostname`
и отметку `isDefault`, а также фактическое подключение текущего вызова — источник
(сохранённый профиль, `--env-file`, `B24CLI_ENV_FILE` или `BITRIX24_WEBHOOK`), связанный
профиль, путь или переменную и hostname. Токен, ID пользователя вебхука и путь REST
не выводятся. `auth:list NAME` показывает один профиль.

## Замена вебхука

```sh
b24cli auth:login work --replace
```

`--replace` меняет только секрет существующего профиля: `name`, `default.task`,
`default.users` и профиль по умолчанию сохраняются. Новый вебхук проверяется так же,
как при первом входе; при ошибке проверки прежние учётные данные остаются на месте.
`connection.hostname` обновляется по новому вебхуку. Если hostname изменился, под тем же
именем теперь другой портал: вывод показывает прежний и новый hostname, а сохранённые ID
из `default.*` не очищаются — проверьте их через `b24cli config:list --profile work`.

`auth:login NAME` без `--replace` создаёт только новый профиль: существующий NAME
отклоняется до ввода вебхука. Значения по умолчанию задаются командами
[`config:set`](config.md#команды-config).

## Выход

```sh
b24cli auth:logout pet
```

Удаляются метаданные профиля вместе с его настройками и локальный файл учётных данных.
Если профиль был выбран по умолчанию, `defaultProfile` становится `null`: другой профиль
не выбирается автоматически. Вебхук на портале не отзывается — удалите его в настройках
портала отдельно.

## Хранение секрета

Вебхук хранится целиком в отдельном dotenv-файле открытого текста с ограничением доступа;
это хранение без шифрования. JSON содержит несекретные метаданные и настройки.
Поля, требования к host, права Unix/Windows и проверка схемы описаны
в [руководстве по настройкам](config.md).

## Выбор подключения

Приоритет подключения: явный `--profile` или `--env-file` → profile из runtime `--config` →
process `BITRIX24_WEBHOOK` → SDK alias `BITRIX24_PHP_SDK_PLAYGROUND_WEBHOOK` →
`B24CLI_ENV_FILE` → `B24CLI_PROFILE` → профиль по умолчанию → source-only project `.env.local`/`.env`.
CLI `--profile` перекрывает config.profile; env-file конфликтует с обоими profile selectors.
Без явного selector одновременные `B24CLI_ENV_FILE` и `B24CLI_PROFILE` — ошибка даже при process webhook.
Ошибка выбранного источника не переключает портал. Dotenv принимает literal значения;
variable/command substitution (`$...`) отклоняется. Секретные значения не передавайте в flags.

`--profile NAME` и `config.profile` выбирают сохранённый именованный профиль.
Standalone не ищет dotenv в cwd или рядом с бинарником. `--config` задаёт runtime JSON
с `timeout`, `apiPolicy`, `profile`, `maxUploadBytes`, без credentials. Для группы используйте `--group-id`.

`auth:status` следует этому же выбору и проверяет также подключения из окружения и dotenv-файла.
`auth:login`, `auth:switch` и `auth:logout` адресуют профиль аргументом NAME и не принимают
`--profile`/`--env-file`. Команды `config:*` работают только с сохранёнными профилями:
[как выбирается профиль](config.md#выбор-профиля).

## Docker / CI

Process webhook передаётся существующим `make cli` без сохранения в профили.
Для внешнего dotenv wrapper монтирует файл только для чтения:

```sh
B24CLI_ENV_FILE=/absolute/secure/portal.env make cli ARGS='task:list --env-file /run/b24cli.env --json'
B24CLI_CONFIG_DIR=/absolute/user/b24cli B24CLI_PROFILE=work make cli ARGS='task:list --json'
# Локальное управление требует явного разрешения записи mount:
mkdir -m 700 /absolute/user/b24cli
B24CLI_CONFIG_DIR=/absolute/user/b24cli B24CLI_CONFIG_WRITE=1 make cli ARGS='auth:switch work'
```

Docker работает от UID/GID пользователя; config mount по умолчанию read-only.
Используйте путь `/run/b24cli-config` для явного `--config-dir` внутри контейнера.
Headless task-команды не спрашивают webhook и не сохраняют CI credentials.
Help/version/list/completion и offline dry-run не читают credential files и не пишут конфиг;
Обычные advanced fields проверяются локально по документированному REST3 контракту; dry-run не требует metadata API.
