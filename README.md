# bitrix24-cli

CLI для работы с задачами Битрикс24.

[![Allowed licenses](https://github.com/mesilov/bitrix24-cli/actions/workflows/license-check.yml/badge.svg?branch=dev&event=push)](https://github.com/mesilov/bitrix24-cli/actions/workflows/license-check.yml?query=branch%3Adev+event%3Apush)
[![PHP-CS-Fixer](https://github.com/mesilov/bitrix24-cli/actions/workflows/php-cs-fixer.yml/badge.svg?branch=dev&event=push)](https://github.com/mesilov/bitrix24-cli/actions/workflows/php-cs-fixer.yml?query=branch%3Adev+event%3Apush)
[![PHPStan](https://github.com/mesilov/bitrix24-cli/actions/workflows/phpstan.yml/badge.svg?branch=dev&event=push)](https://github.com/mesilov/bitrix24-cli/actions/workflows/phpstan.yml?query=branch%3Adev+event%3Apush)
[![Rector](https://github.com/mesilov/bitrix24-cli/actions/workflows/rector.yml/badge.svg?branch=dev&event=push)](https://github.com/mesilov/bitrix24-cli/actions/workflows/rector.yml?query=branch%3Adev+event%3Apush)

## Скачать

Публичного релиза пока нет. Ниже — предварительные бинарники [CI-сборки `a7024b1` от 11.10.2026](https://github.com/mesilov/bitrix24-cli/actions/runs/38148884407).

| Платформа | Архитектура | Скачать архив CI |
| --- | --- | --- |
| Linux | amd64 | [Скачать](https://github.com/mesilov/bitrix24-cli/actions/runs/38148884407/artifacts/11706600824) |
| macOS | arm64 | [Скачать](https://github.com/mesilov/bitrix24-cli/actions/runs/38148884407/artifacts/11706535808) |
| Windows | amd64 | [Скачать](https://github.com/mesilov/bitrix24-cli/actions/runs/38148884407/artifacts/11706070836) |

[Установочные архивы `.tar.gz` / `.zip` и `SHA256SUMS`](https://github.com/mesilov/bitrix24-cli/actions/runs/38148884407/artifacts/11706590395).
Для скачивания CI artifacts нужен вход в GitHub; срок хранения — 90 дней (эти сборки доступны до 09.01.2027).
[Новые сборки](https://github.com/mesilov/bitrix24-cli/actions/workflows/package-static.yml?query=branch%3Adev).

## Установка

PHP, Composer и Docker пользователю не нужны. Выберите свою платформу ниже.
Проверенная матрица: Linux amd64 (Debian 12), macOS 15 arm64 (Apple silicon),
Windows Server 2022 amd64. Windows 10/11 и более старые macOS требуют отдельной
проверки; инструкции не расширяют заявленную поддержку. На Windows нужен доступный
для записи `%TEMP%`. Архивы для Linux arm64 и Mac Intel пока не предоставляются.

Скачайте архив своей платформы и `SHA256SUMS` **из одного Release или CI run**.
Для текущих CI-сборок удобнее скачать [общий artifact с установочными архивами](https://github.com/mesilov/bitrix24-cli/actions/runs/38148884407/artifacts/11706590395)
и распаковать его внешний ZIP: внутри находятся `.tar.gz`/`.zip` и `SHA256SUMS`.
Откройте терминал в каталоге с этими файлами. Продолжайте только при успешной
проверке checksum; при несовпадении скачайте архив заново из того же источника.

<details>
<summary><strong>Linux amd64 — установка и PATH (Bash)</strong></summary>

Проверьте архитектуру: `uname -m` должен вывести `x86_64`. Проверьте checksum
и распакуйте пакет; `&&` останавливает распаковку при ошибке проверки:

```sh
b24cli_checksum=$(grep '  b24cli-linux-amd64\.tar\.gz$' SHA256SUMS) &&
printf '%s\n' "$b24cli_checksum" | sha256sum --check - &&
tar -xzf b24cli-linux-amd64.tar.gz
```

После результата `b24cli-linux-amd64.tar.gz: OK` и успешной распаковки
проверьте запуск из текущего каталога:

```sh
chmod +x ./b24cli
./b24cli --version
./b24cli --help
```

После успешного первого запуска установите binary без `sudo`, сохранив metadata и license notices:

```sh
mkdir -p "$HOME/.local/bin" "$HOME/.local/share/b24cli"
install -m 755 ./b24cli "$HOME/.local/bin/b24cli"
install -m 644 build-info.json runtime-sources.json THIRD-PARTY-NOTICES.md YIIPRESS-LICENSE.txt "$HOME/.local/share/b24cli/"
cp -R runtime-licenses "$HOME/.local/share/b24cli/"
export PATH="$HOME/.local/bin:$PATH"
```

Для новых интерактивных Bash-терминалов сохраните PATH в `.bashrc`.
Повторный запуск не дублирует строку:

```sh
touch "$HOME/.bashrc"
grep -Fqx 'export PATH="$HOME/.local/bin:$PATH"' "$HOME/.bashrc" ||
  printf '\n%s\n' 'export PATH="$HOME/.local/bin:$PATH"' >> "$HOME/.bashrc"
```

Для zsh используйте сохранение PATH из блока macOS; для другой оболочки —
её файл настройки PATH. Проверьте установку из другого каталога и затем
повторите проверку в новом терминале:

```sh
cd /tmp
command -v b24cli
b24cli --version
b24cli help task:show
```

`command -v` должен показать `$HOME/.local/bin/b24cli`. Если появляется
`command not found`, проверьте PATH; если `Permission denied` — права исполнения
и разрешение исполнения на файловой системе. Корпоративные политики могут
ограничивать запуск отдельно от PATH. При ошибке HTTPS см. [настройку доверенных CA](build/README.md#установка).

Для обновления проверьте checksum нового архива и повторите установку вместе
с копированием notices/metadata, затем сверьте `b24cli --version`.
Публичный релиз должен показывать версию тега, CI-сборка — `dev-<полный SHA>`.

</details>

<details>
<summary><strong>macOS arm64 — установка, первый запуск и PATH (zsh)</strong></summary>

Проверьте архитектуру: `uname -m` должен вывести `arm64`. Для скачанного пакета
проверьте checksum и распакуйте его только при успешной проверке:

```sh
b24cli_checksum=$(grep '  b24cli-macos-arm64\.tar\.gz$' SHA256SUMS) &&
printf '%s\n' "$b24cli_checksum" | shasum -a 256 --check - &&
tar -xzf b24cli-macos-arm64.tar.gz
```

После результата `b24cli-macos-arm64.tar.gz: OK` и успешной распаковки:

```sh
chmod +x ./b24cli
./b24cli --version
./b24cli --help
```

macOS executable получает ad-hoc подпись после добавления PHAR payload.
Она защищает целостность готового файла; Developer ID и notarization пока не выполняются.
Если macOS сообщает о неизвестном разработчике и предлагает разрешение, после
попытки запуска откройте **Системные настройки → Конфиденциальность и безопасность →
Всё равно открыть**, подтвердите **Открыть** и повторите команду.
Это [штатное разрешение конкретного файла](https://support.apple.com/ru-ru/102445),
которое может быть недоступно на управляемом Mac. Оно не исправляет повреждённую подпись.

Целостность скачанного executable можно проверить отдельно:

```sh
codesign --verify --strict --verbose=2 ./b24cli
```

Если проверка подписи завершается ошибкой, файл удаляется или запуск остаётся
заблокированным, скачайте свежий artifact с проверенным checksum и сообщите
версию macOS, ссылку на сборку и вывод команды в [#23](https://github.com/mesilov/bitrix24-cli/issues/23).

После **успешного первого запуска** установите binary без `sudo` и сохраните notices:

```sh
mkdir -p "$HOME/.local/bin" "$HOME/.local/share/b24cli"
install -m 755 ./b24cli "$HOME/.local/bin/b24cli"
install -m 644 build-info.json runtime-sources.json THIRD-PARTY-NOTICES.md YIIPRESS-LICENSE.txt "$HOME/.local/share/b24cli/"
cp -R runtime-licenses "$HOME/.local/share/b24cli/"
export PATH="$HOME/.local/bin:$PATH"
rehash
```

Для новых zsh-терминалов сохраните настройку в `.zshrc`; команды учитывают `ZDOTDIR`
и при повторном запуске не добавляют строку ещё раз:

```sh
b24cli_zshrc="${ZDOTDIR:-$HOME}/.zshrc"
touch "$b24cli_zshrc"
grep -Fqx 'export PATH="$HOME/.local/bin:$PATH"' "$b24cli_zshrc" ||
  printf '\n%s\n' 'export PATH="$HOME/.local/bin:$PATH"' >> "$b24cli_zshrc"
```

Проверьте команду из другого каталога, затем откройте новый терминал и повторите:

```sh
cd /tmp
command -v b24cli
b24cli --version
b24cli help task:show
```

`command -v` должен показать `$HOME/.local/bin/b24cli`. `./b24cli` запускает файл
из текущего каталога; `b24cli` ищется в PATH. Ошибка zsh `command not found`
решается настройкой PATH, а отказ ОС при запуске файла рассматривается в #23.
Текущий каталог `.` добавлять в PATH не нужно. Для обновления проверьте checksum
нового архива, повторите установку binary/notices и сверьте версию. При ошибке HTTPS
см. [настройку доверенных CA](build/README.md#установка).

</details>

<details>
<summary><strong>Windows amd64 — установка и PATH (PowerShell)</strong></summary>

Используйте Windows x64 и PowerShell. Сначала проверьте единственную запись
для нужного архива в `SHA256SUMS`; при ошибке команды выбросят исключение:

```powershell
$b24cliChecksumLines = @(Get-Content -LiteralPath .\SHA256SUMS -ErrorAction Stop | Where-Object {
    $_ -match '^[a-fA-F0-9]{64}  b24cli-windows-amd64\.zip$'
})
if ($b24cliChecksumLines.Count -ne 1) { throw 'Missing or duplicate checksum' }
$b24cliExpected = ($b24cliChecksumLines[0] -split '  ', 2)[0]
$b24cliActual = (Get-FileHash -LiteralPath .\b24cli-windows-amd64.zip -Algorithm SHA256 -ErrorAction Stop).Hash
if ($b24cliActual -ne $b24cliExpected) { throw 'Checksum mismatch' }
Write-Output 'b24cli-windows-amd64.zip: OK'
```

Продолжайте только после `OK`. Распакуйте весь пакет в пользовательский каталог:
в нём останутся executable, metadata и license notices. Права администратора не нужны.

```powershell
$b24cliInstallDir = Join-Path $env:LOCALAPPDATA 'Programs\b24cli'
Expand-Archive -LiteralPath .\b24cli-windows-amd64.zip -DestinationPath $b24cliInstallDir -Force -ErrorAction Stop
```

Если Windows блокирует **доверенный файл с проверенным checksum** как скачанный,
можно снять метку происхождения только с него:

```powershell
Unblock-File -LiteralPath (Join-Path $b24cliInstallDir 'b24cli.exe')
```

При предупреждении SmartScreen доступно **Подробнее → Выполнить в любом случае**,
если это разрешено системой. [Unblock-File](https://learn.microsoft.com/en-us/powershell/module/microsoft.powershell.utility/unblock-file?view=powershell-5.1)
и это разрешение не создают доверенную подпись. В Windows 11 Smart App Control
или корпоративная политика могут продолжать блокировать запуск; инструкция
не гарантирует обход и не требует отключения защиты всей системы.
[Пояснение Microsoft](https://learn.microsoft.com/en-us/windows/apps/package-and-deploy/smartscreen-reputation).

Проверьте первый запуск до настройки PATH:

```powershell
& (Join-Path $b24cliInstallDir 'b24cli.exe') --version
& (Join-Path $b24cliInstallDir 'b24cli.exe') --help
```

После успешного запуска добавьте каталог в **пользовательский** PATH и текущую сессию.
Существующие entries сохраняются; повторный запуск не добавляет тот же путь ещё раз:

```powershell
$b24cliUserPath = [Environment]::GetEnvironmentVariable('Path', 'User')
if (($b24cliUserPath -split ';') -notcontains $b24cliInstallDir) {
    $b24cliNewPath = (@($b24cliUserPath, $b24cliInstallDir) | Where-Object { $_ }) -join ';'
    [Environment]::SetEnvironmentVariable('Path', $b24cliNewPath, 'User')
}
if (($env:Path -split ';') -notcontains $b24cliInstallDir) {
    $env:Path = "$b24cliInstallDir;$env:Path"
}
Set-Location $env:TEMP
(Get-Command b24cli -CommandType Application).Source
b24cli --version
b24cli help task:show
```

Путь должен вести к установленному `b24cli.exe`. Откройте новое окно PowerShell
и повторите последние три команды; если оно унаследовало прежний PATH, перезапустите
приложение терминала. При `Access denied` проверьте права и политику безопасности,
при ошибке временного файла — доступность `%TEMP%`. Для корпоративного HTTPS CA
см. [настройку доверия Windows](build/README.md#установка).

Для обновления закройте запущенные процессы b24cli, проверьте checksum нового
архива и повторите `Expand-Archive` в тот же каталог. При необходимости повторите
точечный `Unblock-File` и сверьте `b24cli --version`. PATH добавлять заново не нужно.

</details>

Для подключения к порталу после установки см. [руководство](docs/development.md).

## Подключения и конфигурация

Именованные окружения `work`, `pet`, `clientA`, `clientB` описывают отдельные порталы.
[Описание формата и принципов работы](docs/configuration.md), [JSON Schema](docs/config.schema.json).
Пользовательский каталог: `--config-dir` → `B24CLI_CONFIG_DIR` → на Linux/macOS
абсолютный `$XDG_CONFIG_HOME/b24cli` либо `$HOME/.config/b24cli`; на Windows
`%APPDATA%\b24cli`. Относительный XDG игнорируется. Без HOME/APPDATA нужен явный каталог.
Состояние хранится отдельно от бинарника.

### bash / zsh

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

### PowerShell

```powershell
.\b24cli.exe connection:add work --group-id 42 --creator-id 1 --responsible-id 2
.\b24cli.exe connection:use work
Get-Content -Raw C:\secure\webhook.txt | .\b24cli.exe connection:add pet --webhook-stdin -n
.\b24cli.exe task:list --profile work --json
.\b24cli.exe task:list --env-file 'C:\secure\portal.env' --json
```

### cmd.exe

```bat
b24cli.exe connection:add work --webhook-stdin -n < C:\secure\webhook.txt
b24cli.exe connection:use work
b24cli.exe task:list --profile work --json
b24cli.exe task:list --env-file C:\secure\portal.env --json
```

Webhook хранится целиком в отдельном plaintext dotenv; JSON содержит описательный `name`,
`connection.hostname`, относительный `connection.credentialFile` и optional `default.task`.
`name` не используется для HTTP/DNS. Host полного HTTPS webhook должен совпадать с
`connection.hostname`. Managed dirs/files имеют 0700/0600 на Unix; на Windows CLI
устанавливает и проверяет ACL текущего пользователя через встроенный Windows PowerShell.
Это защита доступа, без шифрования; администратор/системные полномочия могут её обходить.
Небезопасные права и symlinks приводят к ошибке, без автоматического исправления чужих файлов.

`connection:add` не выбирает default; `connection:use` делает это явно.
Удаление текущего default оставляет `defaultProfile: null`. Webhook на портале не отзывается.
`default.task` поддерживает только `groupId`, `creatorId`, `responsibleId` и boolean
`allowsTimeTracking`. Defaults применяются только к `task:add` выбранного профиля.
Явные поля выше defaults, включая явный `false`; учёт времени по умолчанию `false`.
Отсутствующие автор/исполнитель остаются обязательными; CLI не выводит их из webhook.

Приоритет подключения: явный `--profile` или `--env-file` → profile из runtime `--config` →
process `BITRIX24_WEBHOOK` → SDK alias `BITRIX24_PHP_SDK_PLAYGROUND_WEBHOOK` →
`B24CLI_ENV_FILE` → `B24CLI_PROFILE` → saved default → source-only project `.env.local`/`.env`.
CLI `--profile` перекрывает config.profile; env-file конфликтует с обоими profile selectors.
Без явного selector одновременные `B24CLI_ENV_FILE` и `B24CLI_PROFILE` — ошибка даже при process webhook.
Ошибка выбранного источника не переключает портал. Dotenv принимает literal значения;
variable/command substitution (`$...`) отклоняется. Секретные значения не передавайте в flags.

**Совместимость:** прежний `--profile default`/`config.profile=default` теперь выбирает
сохранённый профиль `default`. Для старого process env уберите selector либо создайте профиль.
Standalone не ищет dotenv в cwd или рядом с бинарником. `--config` остаётся runtime JSON
с `timeout`, `apiPolicy`, `profile`, без credentials. `--group-id` — единственное имя флага группы;
`--project` удалён в #27.

### Docker / CI

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
advanced fields сохраняют описанный preflight editable metadata.

## CLI-команды

| Команда | Назначение |
| --- | --- |
| `task:add` | Создать задачу |
| `task:show` | Показать карточку задачи |
| `task:list` | Показать доступные задачи |
| `task:find` | Найти задачи по фрагменту заголовка |
| `task:update` | Изменить явные поля задачи |
| `task:delete` | Удалить задачу |
| `task:assign` | Сменить ответственного |
| `task:deadline:set` | Изменить срок задачи |
| `task:fields:list` | Показать поля задачи |
| `task:access:show` | Показать права текущего пользователя |
| `task:participants:set` | Заменить выбранные роли участников |
| `task:history:list` | Показать историю изменений задачи |
| `task:file:attach` | Прикрепить существующий файл Диска |
| `task:chat:delete` | Удалить сообщение чата задачи |
| `task:chat:list` | Показать сообщения чата задачи |
| `task:chat:send` | Отправить сообщение в чат задачи |
| `task:chat:update` | Изменить сообщение чата задачи |
| `task:checklist:add` | Создать чек-лист |
| `task:checklist:item:add` | Добавить пункт чек-листа |
| `task:checklist:item:complete` | Выполнить пункт чек-листа |
| `task:checklist:item:delete` | Удалить пункт с дочерними пунктами |
| `task:checklist:item:list` | Показать пункты чек-листа |
| `task:checklist:item:renew` | Возобновить пункт чек-листа |
| `task:checklist:item:update` | Изменить заголовок пункта |
| `task:checklist:list` | Показать чек-листы |
| `task:time:add` | Добавить запись времени |
| `task:time:delete` | Удалить запись времени |
| `task:time:list` | Показать записи времени |
| `task:time:show` | Показать контекст учёта времени |
| `task:time:update` | Исправить запись времени |
| `list` | Показать список команд |
| `help` | Показать справку команды |
| `completion` | Сгенерировать автодополнение для shell |

Справка по параметрам и примерам: `b24cli help task:show`.

Группа/проект задаётся положительным целым ID:

```sh
b24cli task:add --title "Договор" --creator 1 --responsible 2 --group-id 42
b24cli task:update 123 --group-id 42
b24cli task:list --group-id 42 --json
b24cli task:find --title "договор" --group-id 42 --json
```

Миграция CLI для 0.1.0: замените `--project ID` на `--group-id ID` в командах
`task:add`, `task:update` и `task:list`. Старое имя удалено без alias: его передача,
в том числе вместе с новым флагом, возвращает exit 2 до API-запроса. JSON и REST 3.0
сохраняют `groupId`; для advanced input используйте `--fields '{"groupId":42}'`,
для JSON-фильтра списка — `task:list --where '{"groupId":42}'`.
Флаг и JSON-поля нельзя смешивать. В `task:list` и `task:find` фильтр группы
выполняется локально при обходе доступных задач; `--max-scan` ограничивает обход,
`--limit` — число результатов. `task:find` совмещает `--group-id` с поиском
по заголовку без учёта регистра; без флага ищет во всех доступных группах.
Значения по умолчанию из профилей разрабатываются отдельно в [#19](https://github.com/mesilov/bitrix24-cli/issues/19).
Предварительные бинарники выше собраны до этой миграции; новое имя доступно после пересборки CLI.

Разработка, подключение и сборка: [руководство](docs/development.md).
