# bitrix24-cli

CLI для работы с задачами Битрикс24.

[Первый релиз 0.1.0: возможности](CHANGELOG.md#010--2026-10-12) · [область приёмки и ограничения](docs/rc-0.1.0.md) · [JTBD по ролям](docs/task-jtbd.md).

[![Allowed licenses](https://github.com/mesilov/bitrix24-cli/actions/workflows/license-check.yml/badge.svg?branch=dev&event=push)](https://github.com/mesilov/bitrix24-cli/actions/workflows/license-check.yml?query=branch%3Adev+event%3Apush)
[![PHP-CS-Fixer](https://github.com/mesilov/bitrix24-cli/actions/workflows/php-cs-fixer.yml/badge.svg?branch=dev&event=push)](https://github.com/mesilov/bitrix24-cli/actions/workflows/php-cs-fixer.yml?query=branch%3Adev+event%3Apush)
[![PHPStan](https://github.com/mesilov/bitrix24-cli/actions/workflows/phpstan.yml/badge.svg?branch=dev&event=push)](https://github.com/mesilov/bitrix24-cli/actions/workflows/phpstan.yml?query=branch%3Adev+event%3Apush)
[![Rector](https://github.com/mesilov/bitrix24-cli/actions/workflows/rector.yml/badge.svg?branch=dev&event=push)](https://github.com/mesilov/bitrix24-cli/actions/workflows/rector.yml?query=branch%3Adev+event%3Apush)

## Скачать

Готовые пакеты доступны в [последнем публичном релизе](https://github.com/mesilov/bitrix24-cli/releases/latest).
Прямые ссылки ниже ведут на **Latest Release** и обновляются при публикации следующей версии.
Версия релизного бинарника соответствует тегу релиза.

| Платформа | Архитектура | Скачать |
| --- | --- | --- |
| Linux | amd64 | [b24cli-linux-amd64.tar.gz](https://github.com/mesilov/bitrix24-cli/releases/latest/download/b24cli-linux-amd64.tar.gz) |
| macOS | arm64 | [b24cli-macos-arm64.tar.gz](https://github.com/mesilov/bitrix24-cli/releases/latest/download/b24cli-macos-arm64.tar.gz) |
| Windows | amd64 | [b24cli-windows-amd64.zip](https://github.com/mesilov/bitrix24-cli/releases/latest/download/b24cli-windows-amd64.zip) |

Скачайте [SHA256SUMS](https://github.com/mesilov/bitrix24-cli/releases/latest/download/SHA256SUMS)
для проверки архивов перед установкой. Для конкретной версии выберите её на
[странице релизов](https://github.com/mesilov/bitrix24-cli/releases).

Для проверки изменений из `dev` доступны
[ручные CI-сборки](https://github.com/mesilov/bitrix24-cli/actions/workflows/package-static.yml?query=branch%3Adev).
С правами записи откройте **Actions → Package standalone binaries → Run workflow**,
выберите ветку `dev` или нужную ветку изменений и запустите workflow.
Бинарники автоматически собираются только по релизному тегу; на MR и push в `dev`
выполняются тесты и линтеры. Команда ручного запуска:

```sh
gh workflow run package-static.yml --ref dev
```

Выберите завершённый run со статусом **Success** и artifact `b24cli-release-assets`:
он содержит три установочных архива и `SHA256SUMS`. Версия этих бинарников —
`dev-<полный source SHA>`. Для скачивания CI artifacts нужен вход в GitHub;
срок хранения — 90 дней.

## Установка

Для **macOS arm64 и Linux amd64** основной способ — пользовательский installer:

```sh
curl -fsSL https://raw.githubusercontent.com/mesilov/bitrix24-cli/main/install.sh | sh
. "$HOME/.local/share/b24cli/env"
b24cli --version
```

Installer скачивает архив и `SHA256SUMS` одного [публичного Release](https://github.com/mesilov/bitrix24-cli/releases),
проверяет SHA-256, notices и точную версию перед заменой executable.
По умолчанию выбирается latest stable; для конкретного опубликованного тега
(включая prerelease) передайте `sh -s -- --version 0.1.0` после pipe.
Нужны системные `sh`, `curl`, `tar`, `awk`, `sed`, `grep`, `mktemp`, файловые utilities
и `shasum` (macOS) либо `sha256sum` (Linux). `sudo`, PHP, Composer, Docker,
Homebrew, Python и jq для установки не нужны. Неподдерживаемые targets отклоняются.

Предварительно скачать и просмотреть скрипт:

```sh
curl -fsSL https://raw.githubusercontent.com/mesilov/bitrix24-cli/main/install.sh -o install.sh
less install.sh
sh install.sh --version 0.1.0
. "$HOME/.local/share/b24cli/env"
```

Binary устанавливается в `$HOME/.local/bin/b24cli`, notices/metadata — в
`$HOME/.local/share/b24cli/package/`, env-файл — в `$HOME/.local/share/b24cli/env`.
Текущему терминалу нужна команда подключения env-файла: `sh` через pipe не меняет
PATH родительского shell. Новые interactive zsh/bash и bash login sessions
получают PATH из startup profiles. Учитывается `${ZDOTDIR:-$HOME}/.zshrc`,
`.bashrc` и первый существующий `.bash_profile`/`.bash_login`, иначе `.profile`.
Повторная установка и подключение env-файла не добавляют дубликаты; настройки
подключений и пользовательские данные сохраняются. Для обновления повторите installer.

Для собственного абсолютного каталога (поддерживаются пробелы) и CI:

```sh
sh install.sh --install-dir "$HOME/tools/b24cli bin" --no-modify-path
. "$HOME/.local/share/b24cli/env"
b24cli --version
```

`--no-modify-path` сохраняет shell profiles; env-файл всё равно создаётся.
Installer выводит фактические пути и команду активации. Каталоги с `:` или переводом
строки отклоняются, поскольку они не подходят для PATH. Installer не отключает
Gatekeeper и не снимает quarantine: диагностика первого запуска —
[#23](https://github.com/mesilov/bitrix24-cli/issues/23), signing/notarization —
[#25](https://github.com/mesilov/bitrix24-cli/issues/25).

Ручная checksum/unpack/PATH установка ([#24](https://github.com/mesilov/bitrix24-cli/issues/24))
и Windows PowerShell описаны ниже. PHP, Composer и Docker пользователю не нужны. Выберите свою платформу ниже.
Проверенная матрица: Linux amd64 (Debian 12), macOS 15 arm64 (Apple silicon),
Windows Server 2022 amd64. Windows 10/11 и более старые macOS требуют отдельной
проверки; инструкции не расширяют заявленную поддержку. На Windows нужен доступный
для записи `%TEMP%`. Архивы для Linux arm64 и Mac Intel пока не предоставляются.

Скачайте архив своей платформы и `SHA256SUMS` **из одного Release или CI run**.
Для текущих CI-сборок удобнее скачать общий artifact `b24cli-release-assets` выбранного run
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
install -m 644 build-info.json runtime-sources.json THIRD-PARTY-NOTICES.md BITRIX24-CLI-LICENSE.txt YIIPRESS-LICENSE.txt "$HOME/.local/share/b24cli/"
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
При первом запуске macOS может заблокировать файл как непроверенный; в терминале
это также может выглядеть как `zsh: killed`. Если checksum и подпись прошли проверку,
после заблокированной попытки запуска откройте **Системные настройки → Конфиденциальность и безопасность →
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
install -m 644 build-info.json runtime-sources.json THIRD-PARTY-NOTICES.md BITRIX24-CLI-LICENSE.txt YIIPRESS-LICENSE.txt "$HOME/.local/share/b24cli/"
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
и повторите последние три команды; если PATH ещё не содержит каталог установки, перезапустите
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
$OutputEncoding = [Console]::InputEncoding = [System.Text.UTF8Encoding]::new($false)
Get-Content -Raw -Encoding UTF8 C:\secure\webhook.txt | .\b24cli.exe connection:add pet --webhook-stdin -n
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

`--profile NAME` и `config.profile` выбирают сохранённый именованный профиль.
Standalone не ищет dotenv в cwd или рядом с бинарником. `--config` задаёт runtime JSON
с `timeout`, `apiPolicy`, `profile`, `maxUploadBytes`, без credentials. Для группы используйте `--group-id`.

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
Обычные advanced fields проверяются локально по документированному REST3 контракту; dry-run не требует metadata API.

## Справка и сведения о приложении

```sh
b24cli --help
b24cli task:add --help
b24cli help task:add
b24cli --about
b24cli --version
```

Общий `--help` (или `-h`) показывает назначение b24cli, список публичных команд,
глобальные опции и примеры создания, просмотра и поиска задач. Первым идёт единый
блок «Общие» с `completion`, `help` и `list`, затем — пространства имён команд
в алфавитном порядке. Команды внутри каждого блока также упорядочены по имени.
Вся справка написана на русском, включая заголовки, описания аргументов и опций,
значения по умолчанию и пояснения встроенных команд. Имена команд, флагов,
API/JSON-полей и значений параметров сохраняют точное написание. Справка выбранной
команды содержит её аргументы, опции, ограничения и примеры; `list --help`
описывает команду списка. Без аргументов b24cli выводит список команд.

`b24cli help task:add --format=md` выводит справку в Markdown; доступны также
`txt`, `xml` и `json`. Человеческие описания во всех форматах — на русском.
`b24cli completion bash` (также `fish` или `zsh`) выводит скрипт автодополнения
для выбранной оболочки. Установку смотрите в `b24cli completion --help`.

`--about` показывает назначение приложения, фактическую версию сборки, автора,
лицензию и ссылки на репозитории приложения и скилла. Версия совпадает с
`--version`. Справка и сведения о приложении работают без подключения к порталу.

[Агентский скилл b24cli](https://github.com/mesilov/bitrix24-cli-skill) помогает
AI-агенту выполнять операции через публичные команды приложения.

## CLI-команды

Полный список с назначением, примерами и ссылками на интеграционные тесты:
[карта CLI-вызовов](docs/cli-command-map.md). Проверка установленной сборки —
`b24cli list`; список ниже служит кратким обзором.

[JTBD по ролям: сценарии, CLI-команды и интеграционные тесты](docs/task-jtbd.md).

### Проблемные сценарии агента

`doctor:problem-trajectory` регистрирует обезличенный сценарий нехватки публичной
CLI-возможности и алгоритм необходимой обвязки словами. Карточки сохраняются
локально рядом с user config; telemetry default true, лимит 100. Скилл проверяет
`analysisDue` при запуске и предлагает weekly пакетный разбор. Сам CLI не запускает
фоновый сбор и не читает истории чатов.

```sh
b24cli doctor:problem-trajectory --action status --json
b24cli doctor:problem-trajectory --report-file report.json --json
```

[Контракт и примеры](docs/problem-trajectories.md). Feedback в MVP пока мокается
только с явным `--mock-feedback` и подтверждением; результат mocked=true,
delivered=false не означает отправку обращения или создание issue.

| Команда | Назначение |
| --- | --- |
| `users:current` | Пользователь текущего подключения |
| `users:find` | Сотрудники по ФИО/email |
| `users:colleagues` | Коллеги текущего отдела |
| `users:manager` | Руководитель по оргструктуре или из профиля |
| `connection:add` | Сохранить именованное подключение и defaults создания задачи |
| `connection:list` | Показать сохранённые подключения |
| `connection:show` | Показать выбранное подключение без секретов |
| `connection:use` | Выбрать подключение по умолчанию |
| `connection:remove` | Удалить локальное подключение |
| `task:add` | Создать задачу с явными полями и тегами |
| `task:show` | Показать карточку задачи |
| `task:list` | Показать доступные задачи |
| `task:my` | Показать задачи выбранного исполнителя |
| `task:find` | Найти задачи по фрагменту заголовка |
| `task:start` | Начать работу |
| `task:pause` | Приостановить выполнение |
| `task:defer` | Отложить задачу |
| `task:renew` | Возобновить задачу |
| `task:complete` | Завершить или передать на контроль |
| `task:approve` | Принять результат при контроле |
| `task:disapprove` | Вернуть на доработку при контроле |
| `task:update` | Изменить явные поля, заменить или очистить теги |
| `task:delete` | Удалить задачу |
| `task:assign` | Сменить ответственного |
| `task:deadline:set` | Изменить срок задачи |
| `task:fields:list` | Показать поля задачи |
| `task:access:show` | Показать права текущего пользователя |
| `task:participants:set` | Заменить выбранные роли участников |
| `task:history:list` | Показать историю изменений задачи |
| `task:status:durations` | Рассчитать календарное время задачи в статусах |
| `task:file:attach` | Прикрепить файл Диска или загрузить локальный файл |
| `task:result:add` | Добавить результат задачи |
| `task:result:list` | Показать результаты задачи |
| `task:result:update` | Изменить текст результата задачи |
| `task:result:delete` | Удалить результат задачи |
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

### Календарное время в статусах

```sh
b24cli task:status:durations 123 --json
b24cli task:status:durations 123 --as-of 2026-10-01T12:00:00+03:00 --json
```

Каждый повторный вход в статус создаёт отдельный интервал. Учитываются ночи,
выходные и время в завершённом статусе. `asOf` фиксируется до чтения API;
`--as-of` требует ISO 8601 с часовым поясом, не в будущем.

Точные суммы требуют полной, непротиворечивой истории с доказанными началом
и последним состоянием. При неполноте или неоднозначности команда возвращает
`complete=false`, причины, exit 3 и `null` для сумм/времени в статусе
(«неизвестно» в human/plain). `elapsedBetweenEventsSeconds` — только разница
наблюдаемых меток, без гарантии непрерывного пребывания в статусе.
Два REST3 list чтения подтверждают `id/created` текущей задачи; строго более ранние
события сохраняются в `excludedHistoryEvidence` и исключаются из расчёта. Начало —
`created`, даже если NEW записан позже. Положительные STATUS 2/3/4/5 подтверждены
readback; мета-коды и неизвестные значения остаются partial. Недоказанная полнота
источника сохраняет null totals даже после отделения старых NEW/DELETE.

Команда читает REST1 историю и REST3 состояние; `strict-rest3` запрещает
маршрут до подключения. Трудозатраты смотрите через `task:time:show`.
На проверенном портале подтверждены повторные открытия и честный partial-result;
точный live-расчёт пока не подтверждён.

### Файл к задаче

```sh
b24cli task:file:attach 123 --file './Документы/Отчёт.pdf' --folder-id 456
b24cli task:file:attach 123 --file './report.pdf' --folder-id 456 --name 'Отчёт.pdf' --dry-run --json
b24cli task:file:attach 123 --file-id 789
```

`--file` загружает **один** читаемый обычный локальный файл в явно выбранную папку
Диска и прикрепляет его к задаче. Лимит CLI — **500 MB (500000000 байт)**;
явный `maxUploadBytes` в runtime JSON `--config` задаёт другой лимит в байтах и имеет приоритет, в том числе выше 500 MB.
stdin (`-`), URLs, каталоги и специальные файлы не принимаются. `--name` задаёт
имя, иначе используется basename пути: UTF-8, 1–255 символов, без разделителей
пути и управляющих символов. При совпадении имени создаётся файл с уникальным
именем; существующий файл не перезаписывается. Этот режим нельзя смешивать
с повторяемым `--file-id`, а `--folder-id` и `--name` используются только с `--file`.

Нужны разрешения webhook **disk/tasks** и доступ к папке/задаче. Загрузка
использует REST1 `disk.folder.uploadFile`, привязка — REST3 `tasks.task.file.attach`;
`strict-rest3` запрещает локальный режим до обращения к порталу. `--dry-run`
показывает план без credentials/API и содержимого файла. Существующий Disk ID
через `--file-id` требует только attach REST3.

Операция состоит из двух записей. Если загрузка подтверждена, а привязка
отклонена, CLI возвращает exit3 и Disk ID загруженного файла: после проверки
цели/прав используйте `--file-id`, не загружая файл снова. При `outcomeUnknown`
сначала проверьте папку Диска или привязку на портале. Автоматического повтора
и удаления загруженного файла нет; Ctrl-C возвращает 130 с известными шагами.
ACK привязки подтверждает метод, самостоятельный membership readback не заявляется.

Справка по параметрам и примерам: `b24cli help task:show`.

### Справка и ошибки ввода

`b24cli task:find --help` и `b24cli help task:find` показывают назначение,
обычные примеры, затем параметры, defaults и advanced input/API ограничения.
Help, список команд, version и completion работают без подключения.

Опечатка `task:find --titel договор` возвращает `usage-error` и код 2 с подсказкой
`--title` и корректным примером. Отсутствующее значение или обязательный аргумент
называются явно; конфликтующие параметры требуют выбрать один способ ввода.
Ошибочный ввод не выполняет API-запросов. Подсказки — только текст и не запускают
команды автоматически; значения аргументов, webhook/token и stack trace не отражаются.

### Карточки и форматы вывода

```sh
b24cli task:show 123
b24cli task:show 123 --select id --select title --select status --select responsible.id --select responsible.name --select group.id --select group.name --select deadline --select description
b24cli task:list --group-id 42 --limit 25
```

Карточка показывает название/ID, статус, ответственного, проект, срок и описание,
затем дополнительные выбранные поля. Имена показываются вместе с ID только из
полученных данных; при отсутствии имени выводится ID. Renderer не запрашивает
дополнительные данные. `--select` задаёт полный набор выбранных полей; для карточки
с именами явно выбирайте также ID relations. Длинный текст переносится по ширине
терминала (при неизвестной ширине — 80 колонок), управляющие символы отображаются
без исполнения. Узкий терминал использует вертикальный список, широкий — таблицу.

Пример карточки на условных данных:

```text
123 · Подготовить договор
Статус: В работе (in_progress)
Ответственный: Анна Иванова (#42)
Проект: #7
Срок: 2026-10-15T18:00:00+06:00

Описание
Проверить реквизиты.
```

`task:list` и `task:find` показывают область доступных задач, число показанных,
найденных и просмотренных задач, `complete`, `limitApplied` и фактический порядок.
Полный пустой результат означает отсутствие совпадений в этой доступной выборке.
Полный обход с ограниченным выводом возвращает 0; неполный обход — 3 с причиной и
следующим действием. `--all` снимает только лимит вывода; при scan cap увеличьте
`--max-scan`. Неполный поиск не доказывает отсутствие дублей или полный размер проекта.

`--json` выводит один объект `data/meta/error` с `meta.schemaVersion=1` и без ANSI.
`--plain` выводит TSV без заголовка: карточка — `field/value`, список —
`id/title/responsibleId/groupId/status/deadline`, поиск — `id/title`.
Tabs/newlines экранируются; сводка полноты plain отправляется в stderr.
`--quiet` подавляет обычный вывод и сводки, сохраняя существенные ошибки в stderr;
`--silent` подавляет весь вывод. Коды завершения сохраняются при обоих флагах.

Группа/проект задаётся положительным целым ID:

```sh
b24cli task:add --title "Договор" --creator 1 --responsible 2 --group-id 42
b24cli task:update 123 --group-id 42
b24cli task:list --group-id 42 --json
b24cli task:find --title "договор" --group-id 42 --json
```

JSON и REST 3.0 используют `groupId`; для advanced input используйте `--fields '{"groupId":42}'`,
для JSON-фильтра списка — `task:list --where '{"groupId":42}'`.
Флаг и JSON-поля нельзя смешивать. В `task:list` и `task:find` фильтр группы
без новых временных флагов выполняется локально при обходе доступных задач; `--max-scan` ограничивает обход,
`--limit` — число результатов. `task:find` совмещает `--group-id` с поиском
по заголовку без учёта регистра; без флага ищет во всех доступных группах.
При создании задачи выбранный профиль может задавать `default.task.groupId`;
явный `--group-id` или advanced JSON имеет приоритет над этим значением.

Свои задачи можно фильтровать по роли владельца выбранного вебхука:

```sh
b24cli task:list --role responsible --json  # делаю: исполнитель
b24cli task:list --role accomplice --json   # помогаю: соисполнитель
b24cli task:list --role creator --json      # поручил: постановщик
b24cli task:list --role auditor --json      # наблюдаю: наблюдатель
b24cli task:find --title "договор" --group-id 42 --role accomplice --json
b24cli task:list --role creator --where '{"responsibleId":9}' --json
```

Роль сочетается с остальными допустимыми критериями через AND. Сама по себе она не ограничивает
статус: «делаю» включает и завершённые задачи, «поручил» включает задачи, поставленные
себе. Группы могут пересекаться. ID берётся из выбранного подключения, а не из
`default.task`; JSON содержит `meta.roleFilter` с `role` и `userId`.
Без временных флагов `responsible` и `creator` используют REST 3.0. `accomplice` и `auditor` добавляют
REST 1.0 чтение ID участников каждой подходящей задачи и недоступны при
`--api-policy strict-rest3`. Для ограничения обхода используйте `--group-id`,
для списка также `--id`; `--all` не снимает `--max-scan`.

### Календарные окна дедлайна

Для выбора следующей работы используйте `task:my` с явным исполнителем:

```bash
b24cli task:my --user 42 --open --json
b24cli task:my --user 42 --open --due-before 2026-10-20T00:00:00+06:00 --max-scan 20000 --json
```

`--user` — обязательный положительный ID исполнителя (`responsibleId`). Он
не меняет пользователя и права подключения: доступны только видимые ему задачи.
Без `--open` возвращаются все состояния; с флагом — `pending`, `in_progress`,
`supposedly_completed`, `deferred`. `--due-before` требует ISO8601 с часовым поясом
и оставляет дедлайн строго раньше границы; задачи без дедлайна исключаются.
Все фильтры сочетаются через AND. Порядок `id DESC`, лимит вывода 50, бюджет
обхода по умолчанию 10000. Неполный обход даёт exit 3 и `meta.complete=false`;
`--max-scan` увеличивает бюджет. Human/JSON/plain соответствуют списку задач.
Команда использует чтение REST 3.0 и поддерживает `strict-rest3`. Очерёдность и
приоритет работы пользователь определяет сам; это отдельная операция EMP-01.

`task:list` и `task:find` принимают `--deadline-window` (один раз), `--overdue`
и `--timezone IANA`. Мнемоники: `today`, `tomorrow`, `yesterday`,
`day-before-yesterday`, `this-week`, `this-month`, `this-quarter`.

```sh
b24cli task:list --group-id 42 --deadline-window today --timezone Asia/Bishkek --json
b24cli task:list --group-id 42 --overdue --deadline-window this-week --timezone Asia/Bishkek --json
b24cli task:list --overdue --timezone Asia/Bishkek --json
b24cli task:find --title "отчёт" --group-id 42 --deadline-window this-month --overdue --timezone Asia/Bishkek --json
```

Окна календарные и полуоткрытые `[from, to)`: неделя начинается в понедельник,
месяц и квартал охватываются целиком. `this-week` отличается от следующих семи дней.
Границы учитывают DST и календарь. По умолчанию timezone берётся из `TZ`, затем из
PHP timezone окружения; CLI не угадывает пояс портала. Для запросов «сегодня» от
skill передавайте известный IANA timezone пользователя. Один `now` фиксируется
на всю команду с точностью до секунды, включая все страницы.

`--overdue` означает непустой дедлайн строго раньше `now`, исключает `completed`
и `declined`, включает `deferred` и `supposedly_completed`. Этот lifecycle фильтр
проверяется локально, а границы дат уходят в API. Без `--overdue` окно включает
все статусы. «Должны были закрыть вчера» — `--deadline-window yesterday --overdue`;
это не выборка по фактической дате закрытия. `deadline=null` не совпадает.
`tomorrow --overdue` возвращает полный пустой результат без сетевых запросов.

Временная выборка использует явный `tasks.task.list` REST 1.0: `>=DEADLINE`,
`<DEADLINE` и `GROUP_ID` для `--group-id`. Это выбор маршрута до вызовов;
`--api-policy strict-rest3` возвращает `policy-denied`/exit4 без fallback.
Остальные критерии — AND; `task:find --status` задаёт локальный lifecycle статус.
Не смешивайте новые флаги с `--due-before`, `--where`/`--where-file`;
неверные/повторные окна и неизвестный timezone дают usage-error/exit2 до API.

REST1 поддерживает общий набор `--select`: id, title, description, creatorId,
responsibleId, groupId, deadline, status, created, started, changed, closed,
activity, startPlan, endPlan, estimatedTime, priority, mark, allowsChangeDeadline,
allowsTimeTracking. Для `--order` доступны поля этого набора кроме description,
creatorId/responsibleId. Неподдерживаемые поля возвращают usage-error до API;
CLI не отправляет придуманные имена и не делает скрытые запросы другой версии.

JSON `meta.deadlineFilter` содержит window/overdue/timezone, now,
fromInclusive/toExclusive, route, deadlineApplied/overdueStatusApplied/groupApplied.
Форматы выдачи, `--limit`, `--all`, `--max-scan`, visible-task scope и partial/exit3
сохраняются. Metadata отличает полный обход от ограниченного вывода; права
подключения ограничивают видимость задач. Границы/маршрут доступны в JSON,
при обычном выводе использование REST1 явно сообщается в stderr.

Список и поиск обходят задачи от новых к старым (`id DESC`) и сохраняют этот
порядок в результатах. Обе команды принимают повторяемый `--order FIELD:asc|desc`;
без явно заданного `id` к пользовательской сортировке добавляется `id ASC`.

```sh
b24cli task:find --title "договор" --group-id 42 --max-scan 20000 --json
b24cli task:find --title "договор" --order id:asc --json
b24cli task:list --order title:asc --order id:desc --json
```

`--max-scan` по умолчанию равен 10000; `--limit` по умолчанию равен 50 и
ограничивает только вывод после обхода. `--all` снимает только лимит вывода.
REST 3.0 поддерживает серверную фильтрацию задач по `id`; группа, заголовок и
остальные критерии проверяются локально, поэтому даже поиск по группе может
просматривать много страниц. Подробнее: [контракт REST 3.0](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-list-rest-v3.html).

В JSON `meta.order` показывает фактический порядок, `meta.scanned` — число
просмотренных задач. `meta.complete=false`, `meta.reason=scan-budget-exhausted`
и код завершения 3 означают неполный результат; `meta.hint` предлагает увеличить
`--max-scan`. Пустой результат при неполном обходе **не доказывает отсутствие
дубля**. При ошибке страницы или недостающих данных причина также указывается
явно. Только полный пустой результат имеет `meta.complete=true` и код 0.

### Поиск с несколькими критериями

```sh
b24cli task:find --title "договор" --group-id 7 --responsible 42
b24cli task:find --title "договор" --status in_progress --order id:desc --limit 25
b24cli task:find --title "договор" --id 123 --id 124 --due-before 2026-10-15T18:00:00+06:00 --json
b24cli task:find --title "договор" --where '{"groupId":7,"responsibleId":42}' --all --json
b24cli task:find --title "договор" --where-file filters.json --role responsible --all --json
```

`--title` обязателен: буквальная подстрока заголовка без регистра Unicode,
без regex/wildcards и поиска по описанию. Все явно заданные критерии и роль
пересекаются через AND на каждой просмотренной странице. `--id` можно повторять;
он задаёт серверную выборку и scope `explicit-task-ids`. Остальные predicates
локальные; результат поиска содержит только `id/title`, включая 0/1/many совпадений.

`--where` и `--where-file` — отдельный режим равенств JSON object с ключами
`id`, `title`, `creatorId`, `responsibleId`, `groupId`, `status`, `deadline`.
Их нельзя смешивать друг с другом или с `--responsible`, `--group-id`, `--status`,
`--due-before`; можно сочетать с `--title`, `--id`, `--role`, `--order` и лимитами.
`--where-file=-` читает stdin явно; одновременный `--config=-` отклоняется.
`--project` не поддерживается; идентификатор группы задаётся через `--group-id`.

Роль проверяется относительно пользователя выбранного подключения. Без роли
определение пользователя и чтение участников не добавляются. `responsible/creator`
сохраняют REST3; `accomplice/auditor` используют явно выбранное REST1 чтение состава
роли после остальных критериев и запрещены при `strict-rest3` (код 4 до API).
При ошибке этого чтения сохраняются подтверждённые совпадения и partial/код 3.
Default порядок — `id DESC`, лимит вывода — 50, scan budget — 10000;
`--all` не отменяет scan budget и не обещает меньшую стоимость обхода.

### Теги задач

Повторяемая опция `--tag` задаёт полный набор названий. Имена сохраняют кириллицу,
пробелы и `#`; одинаковые названия передаются один раз. В `task:update` отсутствие
опции сохраняет существующие теги, а `--clear-tags` явно очищает весь набор.

```sh
b24cli task:add --title "Плановая работа" --creator 1 --responsible 2 --tag '#плановая' --tag 'Этап 1'
b24cli task:update 123 --tag '#ошибка'
b24cli task:update 123 --clear-tags
b24cli task:show 123 --select tags.id --select tags.name --json
b24cli task:add --title "Плановая работа" --creator 1 --responsible 2 --tag '#плановая' --dry-run --json
```

Обычные поля записываются через REST 3.0, теги — отдельным `tasks.task.update`
REST 1.0 с полем `TAGS`. Обновление только тегов выполняет один REST 1.0 запрос.
`--api-policy strict-rest3` отклоняет команду с тегами до создания или изменения
задачи. `--tag` и `--clear-tags` нельзя смешивать друг с другом или с
`--fields`/`--fields-file`. REST 3.0 `tags` остаётся полем чтения;
advanced-запись `tags`/`TAGS` не поддерживается.

Создание с тегами и смешанное обновление выполняются в два шага. Dry-run показывает
обе версии API и зависимость `taskIdFromStep` для ещё не созданной задачи.
Если первый шаг подтверждён, а запись тегов не завершилась, CLI возвращает код 3
с известным `TASK_ID`, подтверждёнными шагами и `complete=false`. `outcomeUnknown`
показывает потерю подтверждения отправленного запроса; прерывание возвращает 130.
Проверьте теги через `task:show` и повторите только `task:update TASK_ID --tag ...`
или `--clear-tags`. CLI не повторяет создание и не удаляет задачу автоматически;
составная операция не атомарна.

### Работа с результатами задачи

Сотрудник фиксирует итог работы, читает его и исправляет текст по ID результата:

```sh
b24cli task:result:add 123 --text "Договор готов: https://example.org/report"
b24cli task:result:add 123 --text-file result.txt --json
b24cli task:result:list 123 --all --json
b24cli task:result:update 123 --result 17 --text "Договор проверен"
b24cli task:result:delete 123 --result 17 --dry-run --json
b24cli task:result:delete 123 --result 17 --force
```

`--text-file -` читает текст из stdin. `task:result:list` возвращает отдельные
результаты с текстом, автором, датами, статусом `open`/`closed`, `fileIds`,
`rights` и `messageId`, если они доступны. `--limit` ограничивает вывод (50 по
умолчанию), `--all` снимает этот лимит, `--max-scan` ограничивает обход
(10000 по умолчанию). При неполном обходе JSON содержит `meta.complete=false`
и причину, команда возвращает код 3.

Изменение и удаление сначала проверяют принадлежность результата задаче.
`--dry-run` показывает план без записи; `--force` подтверждает удаление в
автоматизации. Все четыре команды используют REST 3.0 и доступны с
`--api-policy strict-rest3` в пределах прав подключения.

| Роль / JTBD | Когда и цель | Действие | Признак успеха |
| --- | --- | --- | --- |
| Руководитель проекта / PM-07 | Исполнитель предъявил работу; нужны данные для проверки | `task:result:list TASK_ID` | Видны текст, автор и ID каждого результата выбранной задачи |
| Аккаунт менеджер / AM-07 | Команда подготовила материалы для клиента | `task:result:list TASK_ID` | Получен сохранённый итог работы для подготовки передачи |
| Сотрудник / EMP-08 | Нужно зафиксировать итог или исправить ошибочный результат | `task:result:add/list/update/delete` | Операция подтверждена для результата выбранной задачи |

Результат хранится как отдельная сущность Bitrix24. Эти команды сами не
меняют статус задачи и не фиксируют решение о приёмке или передачу клиенту.

Разработка, подключение и сборка: [руководство](docs/development.md).

## Сотрудники и оргструктура

```sh
b24cli users:current --json
b24cli users:find --name "Иванов Иван" --json
b24cli users:find --email "ivan@example.test" --json
b24cli users:colleagues --department-id 7 --json
b24cli users:manager --department-id 7 --json
b24cli --profile work users:manager --source config --json
```

`users:current` определяет пользователя выбранного подключения. Коллеги — активные сотрудники того же текущего отдела, кроме самого пользователя; дочерние отделы не включаются. При нескольких текущих отделах для `colleagues` и org-руководителя обязателен `--department-id`.

`users:manager` по умолчанию получает руководителя по оргструктуре. Если текущий пользователь сам руководит отделом или отдел без руководителя, команда проходит вверх по `PARENT` до первого другого руководителя. `--source config` явно выбирает `default.users.managerId` сохранённого профиля.

Поиск по ФИО сопоставляет все токены без регистра Unicode с `NAME`, `SECOND_NAME`, `LAST_NAME`, независимо от порядка; поиск email использует точный серверный фильтр. Результат всегда список кандидатов с ID, без автоматического назначения в задачи. Поиск и коллеги поддерживают `--limit` (default 50), `--all`, `--max-scan` (default 1000). Лимит вывода не останавливает обход; `--all` сохраняет scan budget. Неполная выборка возвращает exit3 и `complete=false`; полнота относится к сотрудникам, видимым текущему подключению.

Команды используют REST1 и права `user`/`department` выбранного подключения. JSON сохраняет API имена `ID`, `NAME`, `SECOND_NAME`, `LAST_NAME`, `EMAIL`, `UF_DEPARTMENT`; plain TSV выводит эти шесть колонок без заголовка. Доступность email и оргструктуры зависит от user scope.

В профиле можно задать `default.users.observerId`, `managerId` и boolean `autoAddObserver`. Только при `autoAddObserver=true` создание задачи автоматически добавляет наблюдателя, сохраняя уже назначенных. При ошибке после создания CLI сохраняет ID задачи и сообщает partial. Настройка: `connection:add work --observer-id 5 --manager-id 9 --auto-add-observer true`; поля и ограничения описаны в [конфигурации](docs/configuration.md).

## Лицензия

[bitrix24-cli-license](LICENSE) основана на BSD-3-Clause.
Copyright © 2026 Maksim Mesilov <mesilov.maxim@gmail.com>.
Лицензии сторонних компонентов сохранены в пакетах отдельно.
Все упоминаемые товарные знаки принадлежат их владельцам.

### Жизненный цикл и списки по статусу

```sh
b24cli task:start 123
b24cli task:pause 123
b24cli task:defer 123
b24cli task:renew 123
b24cli task:complete 123 --dry-run --json
b24cli task:complete 123 --json
b24cli task:approve 123
b24cli task:disapprove 123

# 42 — ваш ID; 99 — другой исполнитель; 7 — проект
b24cli task:list --responsible 42 --status in_progress --all --json
b24cli task:list --group-id 7 --status supposedly_completed --all --json
b24cli task:list --responsible 99 --status deferred --all --json
b24cli task:list --group-id 7 --responsible 99 --status pending --all --json
```

Фильтр `--status` принимает одно точное логическое значение: `pending`,
`in_progress`, `supposedly_completed`, `completed`, `deferred`, `declined`.
Все условия исполнителя/проекта/статуса объединяются через AND. Свои задачи
выбираются вашим явным ID; автоматического `--me` нет. Числовые коды и опечатки
в `--status` или `--where.status` отклоняются. `--all` снимает лимит вывода,
но сохраняет `--max-scan` (по умолчанию 10000) и ограничения доступа подключения.

Lifecycle команды используют явный REST 1.0 маршрут; `strict-rest3` запрещает их
до запросов, включая dry-run. Проверяются текущее состояние, право действия и
обязательный native result. `--dry-run` показывает условия и шаги без записи,
включая возможность контроля и серверного завершения подзадач. Обычные переходы
не требуют дополнительного подтверждения. Заметка или файл не заменяют native
result при `requireResult=true`; CLI не создаёт его автоматически.

После действия выводится фактический REST3 `status`. `supposedly_completed`
означает ожидание контроля; `completed` — завершение. Постановщик может завершить
задачу сразу, поэтому статус не вычисляется только из `needsControl` или команды.
JSON содержит `data.operation` с `taskId/action/confirmed` и `data.task` с
`id/status/needsControl/requireResult`. Plain TSV: `taskId`, `action`, `confirmed`,
`status`, `needsControl`, `requireResult`. `meta.complete` означает полноту
выполнения и чтения, а не завершение задачи. При подтверждённой записи и ошибке
последующего чтения возвращается partial/exit3; при неизвестном исходе отправленной
записи — `outcomeUnknown=true`. Запись не повторяется; состояние можно сверить
через `task:show`. Изменение raw `status` через `task:update` запрещено.

Если список не возвращает groupId для фильтра проекта, CLI проверяет group.id
карточки подходящей по остальным условиям задачи. Эти REST3 чтения отражаются
в meta.apiCalls и ограничиваются числом просмотренных задач; непроверенная
принадлежность даёт partial вместо недостоверной пустой выборки.


### Пользовательские поля задач

`--user-fields JSON` или `--user-fields-file PATH|-` принимает непустой JSON-объект с точными custom `UF_*` кодами. Доступны строки, числа, логические значения и плоские списки поддержанных значений; `null`, вложенные объекты и системные `UF_CRM_TASK`, `UF_TASK_WEBDAV_FILES`, `UF_MAIL_MESSAGE` отклоняются. Пустая строка или список явно передаются для очистки; обязательные поля очищать может запретить портал. Пропущенные поля сохраняются.

```bash
b24cli task:add --title "План работ" --creator 1 --responsible 2 \
  --group-id 42 --user-fields '{"UF_QUERY":"5"}'
b24cli task:update 123 --user-fields '{"UF_QUERY":"6"}'
b24cli task:update 123 --user-fields-file fields.json --dry-run --json
b24cli task:show 123 --select userFields.key --select userFields.value --json
```

Поля должны существовать на выбранном портале; `UF_QUERY` — пример кода. CLI читает `tasks.task.getFields` через REST1, проверяет доступные имена и типы. Обязательность и множественность проверяет портал: их metadata может быть неполной.

Создание с custom значениями выполняет **один REST1 tasks.task.add**, включая ordinary и custom значения сразу. Basic-флаги и значения `default.task` собирают native REST1 payload: TITLE, DESCRIPTION, CREATED_BY, RESPONSIBLE_ID, GROUP_ID, DEADLINE, ALLOW_TIME_TRACKING. Поля профиля сохраняют свою описанную структуру; явные значения выше default, учёт времени без default выключен.

Advanced JSON при custom создании использует **точные REST1 имена**; он не смешивается с basic-флагами. CLI не переименовывает camelCase JSON:

```bash
b24cli task:add \
  --fields '{"TITLE":"План работ","CREATED_BY":1,"RESPONSIBLE_ID":2,"ALLOW_TIME_TRACKING":"N"}' \
  --user-fields '{"UF_QUERY":"5"}' --dry-run --json
```

Без custom input ordinary создание/обновление и `--fields` используют REST3 camelCase поля и JSON booleans. Их имена и типы проверяются локально по контракту add/update; `editable=false` из field.list не служит универсальным запретом записи. Ordinary advanced dry-run работает без API.

Custom-only update выполняет один REST1 update. Смешанный update сначала меняет ordinary поля через REST3, затем custom через REST1, затем явно заданные теги. При частичном результате CLI сохраняет TASK_ID и подтверждённые шаги; проверьте значения существующей задачи перед повторением `task:update`. Создание и записи автоматически не повторяются и не откатываются.

Custom dry-run читает схему и показывает версии API и native поля без записи. `strict-rest3` отклоняет весь custom план до credentials/API. Теги и default observer сохраняют свои отдельные объявленные шаги; ошибка прекращает последующие записи.
