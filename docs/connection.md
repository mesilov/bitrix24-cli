# Подключения b24cli

[Краткая настройка](../README.md#подключения-и-конфигурация) · [Конфиг](config.md)

## Первое подключение

Именованные окружения `work`, `pet`, `clientA`, `clientB` описывают отдельные порталы.
Подготовьте входящий webhook портала с правами для нужных команд.
`connection:add` запросит его скрытым вводом в терминале. Не передавайте полный URL
в аргументах команды или строке shell history.

```sh
b24cli connection:add work
b24cli connection:use work
b24cli --profile work users:current --json
```

Последняя команда читает текущего пользователя на портале; для неё нужен scope
`user`. `connection:list` и `connection:show` проверяют локально сохранённые
параметры и не подтверждают доступ к API. Для другого портала повторите настройку
с новым именем профиля.

Конфигурация и credentials хранятся отдельно от бинарника.
[Каталог, формат и права доступа](config.md#каталог-и-файлы) · [JSON Schema](config.schema.json).

## Linux / macOS (Bash / zsh)

```sh
b24cli connection:add work --group-id 42 --creator-id 1 --responsible-id 2
# Webhook вводится скрыто в терминале.
b24cli connection:use work
b24cli connection:list
b24cli connection:show
b24cli task:add --profile work --title "Подготовить договор" --dry-run --json
# Для автоматизации входной secret-файл не передаётся содержимым в argv:
b24cli connection:add pet --webhook-stdin -n < /secure/webhook.txt
b24cli task:list --env-file /secure/portal.env --json
b24cli connection:remove pet
```

## Windows (PowerShell)

```powershell
.\b24cli.exe connection:add work --group-id 42 --creator-id 1 --responsible-id 2
.\b24cli.exe connection:use work
$OutputEncoding = [Console]::InputEncoding = [System.Text.UTF8Encoding]::new($false)
Get-Content -Raw -Encoding UTF8 C:\secure\webhook.txt | .\b24cli.exe connection:add pet --webhook-stdin -n
.\b24cli.exe task:list --profile work --json
.\b24cli.exe task:list --env-file 'C:\secure\portal.env' --json
```

## Windows (cmd.exe)

```bat
b24cli.exe connection:add work --webhook-stdin -n < C:\secure\webhook.txt
b24cli.exe connection:use work
b24cli.exe task:list --profile work --json
b24cli.exe task:list --env-file C:\secure\portal.env --json
```

## Изменение и удаление профиля

Webhook хранится целиком в отдельном plaintext dotenv с ограничением доступа;
это хранение без шифрования. JSON содержит несекретные метаданные и defaults.
Поля, требования к host, права Unix/Windows и проверка схемы описаны
в [руководстве по конфигу](config.md).

`connection:add` не выбирает default; `connection:use` делает это явно.
`connection:add NAME` создаёт только новый профиль. Существующий NAME отклоняется
до ввода webhook: переданные параметры не применяются. Для изменения профиля
сначала проверьте его defaults и обеспечьте доступ к webhook для повторного ввода:

```sh
b24cli connection:show work
b24cli connection:remove work
# Передайте все нужные defaults; webhook снова вводится скрыто в терминале.
b24cli connection:add work --group-id 187 --creator-id 3 --responsible-id 2 --allows-time-tracking true
# Если work должен быть подключением по умолчанию:
b24cli connection:use work
```

При удалении стирается локальный credential file. Defaults удалённого профиля
не переносятся: отсутствующие ID flags не сохраняют прежние значения,
`--allows-time-tracking` без флага снова `false`. При использовании описательного
домена задайте его заново через `--portal-domain`.
Удаление текущего default оставляет `defaultProfile: null`. Webhook на портале не отзывается.
`default.task` поддерживает `groupId`, `creatorId`, `responsibleId` и boolean
`allowsTimeTracking`. Defaults применяются только к `task:add` выбранного профиля.
Явные поля выше defaults, включая явный `false`; учёт времени по умолчанию `false`.
Отсутствующие автор/исполнитель остаются обязательными; CLI не выводит их из webhook.

## Выбор подключения

Приоритет подключения: явный `--profile` или `--env-file` → profile из runtime `--config` →
process `BITRIX24_WEBHOOK` → SDK alias `BITRIX24_PHP_SDK_PLAYGROUND_WEBHOOK` →
`B24CLI_ENV_FILE` → `B24CLI_PROFILE` → saved default → source-only project `.env.local`/`.env`.
CLI `--profile` перекрывает config.profile; env-file конфликтует с обоими profile selectors.
Без явного selector одновременные `B24CLI_ENV_FILE` и `B24CLI_PROFILE` — ошибка даже при process webhook.
Ошибка выбранного источника не переключает портал. Dotenv принимает literal значения;
variable/command substitution (`$...`) отклоняется. Секретные значения не передавайте в flags.

`--profile NAME` и `config.profile` выбирают сохранённый именованный профиль.
Standalone не ищет dotenv в cwd или рядом с бинарником. `--config` задаёт runtime JSON
с `timeout`, `apiPolicy`, `profile`, `maxUploadBytes`, без credentials. Для группы используйте `--group-id`.

## Docker / CI

Process webhook передаётся существующим `make cli` без сохранения в профили.
Для внешнего dotenv wrapper монтирует файл только для чтения:

```sh
B24CLI_ENV_FILE=/absolute/secure/portal.env make cli ARGS='task:list --env-file /run/b24cli.env --json'
B24CLI_CONFIG_DIR=/absolute/user/b24cli B24CLI_PROFILE=work make cli ARGS='task:list --json'
# Локальное управление требует явного разрешения записи mount:
mkdir -m 700 /absolute/user/b24cli
B24CLI_CONFIG_DIR=/absolute/user/b24cli B24CLI_CONFIG_WRITE=1 make cli ARGS='connection:use work'
```

Docker работает от UID/GID пользователя; config mount по умолчанию read-only.
Используйте путь `/run/b24cli-config` для явного `--config-dir` внутри контейнера.
Headless task-команды не спрашивают webhook и не сохраняют CI credentials.
Help/version/list/completion и offline dry-run не читают credential files и не пишут конфиг;
Обычные advanced fields проверяются локально по документированному REST3 контракту; dry-run не требует metadata API.
