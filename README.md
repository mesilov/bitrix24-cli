# b24cli — command line client for Bitrix24

Автоматизируйте работу с задачами из терминала и AI-агентов: управляйте задачами,
находите сотрудников и получайте результаты в JSON. Для работы агента доступен
[готовый скилл](https://github.com/mesilov/bitrix24-cli-skill).

[![Allowed licenses](https://github.com/mesilov/bitrix24-cli/actions/workflows/license-check.yml/badge.svg?branch=dev&event=push)](https://github.com/mesilov/bitrix24-cli/actions/workflows/license-check.yml?query=branch%3Adev+event%3Apush)
[![PHP-CS-Fixer](https://github.com/mesilov/bitrix24-cli/actions/workflows/php-cs-fixer.yml/badge.svg?branch=dev&event=push)](https://github.com/mesilov/bitrix24-cli/actions/workflows/php-cs-fixer.yml?query=branch%3Adev+event%3Apush)
[![PHPStan](https://github.com/mesilov/bitrix24-cli/actions/workflows/phpstan.yml/badge.svg?branch=dev&event=push)](https://github.com/mesilov/bitrix24-cli/actions/workflows/phpstan.yml?query=branch%3Adev+event%3Apush)
[![Rector](https://github.com/mesilov/bitrix24-cli/actions/workflows/rector.yml/badge.svg?branch=dev&event=push)](https://github.com/mesilov/bitrix24-cli/actions/workflows/rector.yml?query=branch%3Adev+event%3Apush)

## Скачать

Готовые пакеты — в [последнем публичном релизе](https://github.com/mesilov/bitrix24-cli/releases/latest).
Прямые ссылки всегда ведут на Latest Release.

| Платформа | Архитектура | Скачать |
| --- | --- | --- |
| Linux | amd64 | [b24cli-linux-amd64.tar.gz](https://github.com/mesilov/bitrix24-cli/releases/latest/download/b24cli-linux-amd64.tar.gz) |
| macOS | arm64 | [b24cli-macos-arm64.tar.gz](https://github.com/mesilov/bitrix24-cli/releases/latest/download/b24cli-macos-arm64.tar.gz) |
| Windows | amd64 | [b24cli-windows-amd64.zip](https://github.com/mesilov/bitrix24-cli/releases/latest/download/b24cli-windows-amd64.zip) |

## Установка

PHP, Composer и Docker устанавливать не нужно. Выберите свою платформу.

### Linux amd64 (Bash)

```sh
curl -fsSL https://raw.githubusercontent.com/mesilov/bitrix24-cli/main/install.sh | sh
. "$HOME/.local/share/b24cli/env"
b24cli --version
```

### macOS arm64 (zsh)

```sh
curl -fsSL https://raw.githubusercontent.com/mesilov/bitrix24-cli/main/install.sh | sh
. "$HOME/.local/share/b24cli/env"
b24cli --version
```

### Windows amd64 (PowerShell)

Вставьте блок в PowerShell: он скачает пакет из одного релиза, проверит его
и добавит b24cli в пользовательский PATH.

```powershell
$ErrorActionPreference = 'Stop'
$b24cliRelease = Invoke-RestMethod 'https://api.github.com/repos/mesilov/bitrix24-cli/releases/latest'
$b24cliTemp = Join-Path $env:TEMP ([guid]::NewGuid().ToString())
New-Item -ItemType Directory -Path $b24cliTemp | Out-Null
foreach ($b24cliName in @('b24cli-windows-amd64.zip', 'SHA256SUMS')) {
    $b24cliAsset = @($b24cliRelease.assets | Where-Object { $_.name -eq $b24cliName })
    if ($b24cliAsset.Count -ne 1) { throw "Missing asset: $b24cliName" }
    Invoke-WebRequest -UseBasicParsing $b24cliAsset[0].browser_download_url -OutFile (Join-Path $b24cliTemp $b24cliName)
}
$b24cliZip = Join-Path $b24cliTemp 'b24cli-windows-amd64.zip'
$b24cliChecksum = @(Get-Content (Join-Path $b24cliTemp 'SHA256SUMS') | Where-Object {
    $_ -match '^[a-fA-F0-9]{64}  b24cli-windows-amd64\.zip$'
})
if ($b24cliChecksum.Count -ne 1) { throw 'Missing or duplicate checksum' }
if ((Get-FileHash $b24cliZip -Algorithm SHA256).Hash -ne ($b24cliChecksum[0] -split '  ')[0]) { throw 'Checksum mismatch' }
$b24cliDir = Join-Path $env:LOCALAPPDATA 'Programs\b24cli'
Expand-Archive -LiteralPath $b24cliZip -DestinationPath $b24cliDir -Force
& (Join-Path $b24cliDir 'b24cli.exe') --version
if ($LASTEXITCODE -ne 0) { throw 'b24cli launch failed' }
$b24cliPath = [Environment]::GetEnvironmentVariable('Path', 'User')
if (($b24cliPath -split ';') -notcontains $b24cliDir) {
    [Environment]::SetEnvironmentVariable('Path', (@($b24cliPath, $b24cliDir) | Where-Object { $_ }) -join ';', 'User')
}
if (($env:Path -split ';') -notcontains $b24cliDir) { $env:Path = "$b24cliDir;$env:Path" }
b24cli --version
```

Обновление, ручная установка, PATH, предварительный просмотр скрипта и диагностика
первого запуска — в [руководстве по установке](docs/install.md).
Там же указаны проверенные версии ОС и ограничения платформ.

## Подключения и конфигурация

Подготовьте входящий webhook Битрикс24 с правами для нужных операций. Сохраните
его как именованное подключение, выберите профиль и проверьте доступ к порталу.
`connection:add` запросит webhook скрытым вводом — вставьте его в ответ на запрос.

<details>
<summary>Linux — Bash</summary>

```sh
b24cli connection:add work
b24cli connection:use work
b24cli --profile work users:current --json
```

</details>

<details>
<summary>macOS — zsh</summary>

```sh
b24cli connection:add work
b24cli connection:use work
b24cli --profile work users:current --json
```

</details>

<details>
<summary>Windows — PowerShell</summary>

```powershell
b24cli.exe connection:add work
b24cli.exe connection:use work
b24cli.exe --profile work users:current --json
```

</details>

Для каждого портала можно сохранить отдельный профиль. Управление подключениями,
ввод webhook в CI, переменные окружения и выбор источника credentials —
в [руководстве по подключениям](docs/connection.md).

## CLI-команды

| Группа | Команды |
| --- | --- |
| Справка и автодополнение | `completion`, `help`, `list` |
| Подключения | `connection:add`, `connection:list`, `connection:remove`, `connection:show`, `connection:use` |
| Сотрудники | `users:colleagues`, `users:current`, `users:find`, `users:manager` |
| Создание и редактирование задач | `task:add`, `task:assign`, `task:deadline:set`, `task:delete`, `task:participants:set`, `task:update` |
| Просмотр и поиск задач | `task:access:show`, `task:fields:list`, `task:find`, `task:list`, `task:my`, `task:show` |
| Жизненный цикл | `task:approve`, `task:complete`, `task:defer`, `task:disapprove`, `task:pause`, `task:renew`, `task:start` |
| История и время в статусах | `task:history:list`, `task:status:durations` |
| Файлы | `task:file:attach` |
| Результаты | `task:result:add`, `task:result:delete`, `task:result:list`, `task:result:update` |
| Чат задачи | `task:chat:delete`, `task:chat:list`, `task:chat:send`, `task:chat:update` |
| Чек-листы | `task:checklist:add`, `task:checklist:item:add`, `task:checklist:item:complete`, `task:checklist:item:delete`, `task:checklist:item:list`, `task:checklist:item:renew`, `task:checklist:item:update`, `task:checklist:list` |
| Трудозатраты | `task:time:add`, `task:time:delete`, `task:time:list`, `task:time:show`, `task:time:update` |
| Диагностика агента | `doctor:problem-trajectory` |

Полный список с примерами и назначением — в [карте CLI-вызовов](docs/cli-command-map.md).
Проверить свою сборку и открыть справку:

```sh
b24cli list
b24cli help task:add
b24cli --about
```

Рабочие сценарии: [задачи, поиск и форматы вывода](docs/task-usage.md),
[сотрудники и оргструктура](docs/users.md), [JTBD по ролям](docs/task-jtbd.md),
[проблемные сценарии агента](docs/problem-trajectories.md).

## Конфиг

`config.json` хранит профили порталов, выбранное подключение и настройки
по умолчанию для задач и рабочих ролей. Webhook хранится в отдельном credential file.

| Платформа | Файл по умолчанию |
| --- | --- |
| Linux | `$XDG_CONFIG_HOME/b24cli/config.json`, иначе `$HOME/.config/b24cli/config.json` |
| macOS | `$XDG_CONFIG_HOME/b24cli/config.json`, иначе `$HOME/.config/b24cli/config.json` |
| Windows | `%APPDATA%\b24cli\config.json` |

`XDG_CONFIG_HOME` учитывается только как абсолютный путь. Свой каталог задаётся
через `--config-dir PATH` или `B24CLI_CONFIG_DIR`. Хранение независимо от бинарника.
Глобальный `--config PATH` выбирает отдельный runtime JSON с параметрами выполнения.

Формат, приоритеты, defaults и права доступа — в [руководстве по конфигу](docs/config.md),
машиночитаемый контракт — [JSON Schema](docs/config.schema.json).

---

[bitrix24-cli-license](LICENSE) основана на BSD-3-Clause.
Copyright © 2026 Maksim Mesilov <mesilov.maxim@gmail.com>.
Лицензии сторонних компонентов сохранены в пакетах отдельно.
Все упоминаемые товарные знаки принадлежат их владельцам.
