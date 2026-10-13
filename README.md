# bitrix24-cli

CLI для работы с задачами Битрикс24.

[Первый релиз 0.1.0: возможности](CHANGELOG.md#010--2026-10-12) · [область приёмки и ограничения](docs/rc-0.1.0.md).

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
[CI-сборки](https://github.com/mesilov/bitrix24-cli/actions/workflows/package-static.yml?query=branch%3Adev).
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

> URL `main/install.sh` станет доступен после доставки installer в `main`.
> До этого используйте ручную установку ниже; наличие скрипта в feature/dev не
> подтверждает доступность этой команды. Публичные archives уже доступны в Releases.

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
с `timeout`, `apiPolicy`, `profile`, без credentials. Для группы используйте `--group-id`.

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
| `task:find` | Найти задачи по фрагменту заголовка |
| `task:update` | Изменить явные поля, заменить или очистить теги |
| `task:delete` | Удалить задачу |
| `task:assign` | Сменить ответственного |
| `task:deadline:set` | Изменить срок задачи |
| `task:fields:list` | Показать поля задачи |
| `task:access:show` | Показать права текущего пользователя |
| `task:participants:set` | Заменить выбранные роли участников |
| `task:history:list` | Показать историю изменений задачи |
| `task:file:attach` | Прикрепить существующий файл Диска |
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

Справка по параметрам и примерам: `b24cli help task:show`.

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
выполняется локально при обходе доступных задач; `--max-scan` ограничивает обход,
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

Роль сочетается с остальными допустимыми критериями через AND. Она не ограничивает
статус: «делаю» включает и завершённые задачи, «поручил» включает задачи, поставленные
себе. Группы могут пересекаться. ID берётся из выбранного подключения, а не из
`default.task`; JSON содержит `meta.roleFilter` с `role` и `userId`.
`responsible` и `creator` используют REST 3.0. `accomplice` и `auditor` добавляют
REST 1.0 чтение ID участников каждой подходящей задачи и недоступны при
`--api-policy strict-rest3`. Для ограничения обхода используйте `--group-id`,
для списка также `--id`; `--all` не снимает `--max-scan`.

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
