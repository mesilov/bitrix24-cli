# Установка b24cli

[Краткая установка](../README.md#установка) · [Подключение портала](connection.md)

## Источники пакетов

Скачайте [SHA256SUMS](https://github.com/mesilov/bitrix24-cli/releases/latest/download/SHA256SUMS)
для проверки архивов перед установкой. Ссылки на пакеты Linux amd64, macOS arm64
и Windows amd64 находятся в [разделе «Скачать»](../README.md#скачать).
Для конкретной версии выберите её на
[странице релизов](https://github.com/mesilov/bitrix24-cli/releases).

Для проверки выбранных снимков `dev` и релизных кандидатов доступны
[ручные CI-сборки](https://github.com/mesilov/bitrix24-cli/actions/workflows/package-static.yml?query=branch%3Abuilds).
Мейнтейнер выбирает снимок через ветку `builds`; её push запускает упаковку.
Повторный ручной запуск той же ветки:

```sh
gh workflow run package-static.yml --ref builds
```

Выберите завершённый run со статусом **Success** и artifact `b24cli-release-assets`:
он содержит три установочных архива и `SHA256SUMS`. Версия снимка —
`dev-<полный source SHA>`; версия RC совпадает с тегом `MAJOR.MINOR.PATCH-rc-N`.
Для скачивания CI artifacts нужен вход в GitHub. Итоговые архивы снимков `builds`
и RC хранятся 30 дней, диагностика — 7 дней. Артефакты не публикуют Release
и не меняют latest. Выбор снимка, скачивание по run ID и проверка RC описаны
в [руководстве сборки](../build/README.md#промежуточные-снимки-из-dev).

## Установщик для Linux и macOS

Для **macOS arm64 и Linux amd64** основной способ — пользовательский installer:

```sh
(
  set -o pipefail
  curl -fsSL https://raw.githubusercontent.com/mesilov/bitrix24-cli/main/install.sh | sh
) &&
. "$HOME/.local/share/b24cli/env" &&
b24cli --version
```

Installer скачивает архив и `SHA256SUMS` одного [публичного Release](https://github.com/mesilov/bitrix24-cli/releases),
проверяет SHA-256, notices и точную версию перед заменой executable.
По умолчанию выбирается latest stable; для конкретного опубликованного тега
(включая prerelease) передайте `sh -s -- --version 0.1.0` после pipe.
При загрузке latest, архива и SHA256SUMS после HTTP 408, 429, 500, 502, 503, 504,
таймаута или отказа соединения curl делает
до трёх повторов с паузами 1, 2, 4 секунды и учитывает `Retry-After`.
Повторы прекращаются, когда их число исчерпано или с начала первой попытки прошло
360 секунд для latest, 900 секунд для asset. Уже начатая попытка имеет собственный
лимит: 120 секунд для latest,
300 секунд для asset; соединение ограничено 15 секундами. Постоянные HTTP-ошибки,
например 404, и ошибки проверки пакета не повторяются. Окончательный сбой оставляет
прежнюю установку и пользовательские данные на месте.

В приведённом Bash/zsh блоке `pipefail` проверяет обе стороны pipeline, а `&&`
запускает следующий шаг только после успеха предыдущего. При сбое скачивания или
установки env не подключается и `b24cli --version` не выполняется. Успешная активация
сохраняет PATH в текущем терминале. Явный тег обходит определение latest, но загрузка
самих assets также требует доступной сети.

Нужны системные `sh`, `curl`, `tar`, `awk`, `sed`, `grep`, `mktemp`, файловые utilities
и `shasum` (macOS) либо `sha256sum` (Linux). `sudo`, PHP, Composer, Docker,
Homebrew, Python и jq для установки не нужны. Неподдерживаемые targets отклоняются.

Предварительно скачать и просмотреть скрипт:

```sh
curl -fsSL --retry 3 --retry-connrefused --retry-max-time 120 https://raw.githubusercontent.com/mesilov/bitrix24-cli/main/install.sh -o install.sh &&
less install.sh &&
sh install.sh --version 0.1.0 &&
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
sh install.sh --install-dir "$HOME/tools/b24cli bin" --no-modify-path &&
. "$HOME/.local/share/b24cli/env" &&
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
ограничивать запуск отдельно от PATH. При ошибке HTTPS см. [настройку доверенных CA](../build/README.md#установка).

Для обновления проверьте checksum нового архива и повторите установку вместе
с копированием notices/metadata, затем сверьте `b24cli --version`.
Публичный релиз и RC должны показывать версию своего тега, снимок `builds` — `dev-<полный SHA>`.

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
см. [настройку доверенных CA](../build/README.md#установка).

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
см. [настройку доверия Windows](../build/README.md#установка).

Для обновления закройте запущенные процессы b24cli, проверьте checksum нового
архива и повторите `Expand-Archive` в тот же каталог. При необходимости повторите
точечный `Unblock-File` и сверьте `b24cli --version`. PATH добавлять заново не нужно.

</details>

После установки [подключите портал](connection.md).
