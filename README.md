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

<details>
<summary>Windows amd64 (PowerShell)</summary>

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

</details>

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

ID, имена профилей и тексты в примерах замените своими. Команды изменения задач
приведены с `--dry-run`.

### Справка и автодополнение

| Команда | Пример | Назначение |
| --- | --- | --- |
| `completion` | `b24cli completion bash` | Вывести скрипт автодополнения для оболочки |
| `help` | `b24cli help task:add` | Показать справку команды |
| `list` | `b24cli list task` | Показать список команд |

### Подключения

| Команда | Пример | Назначение |
| --- | --- | --- |
| `connection:add` | `b24cli connection:add work --group-id 7 --creator-id 1 --responsible-id 42` | Сохранить локальное подключение |
| `connection:list` | `b24cli connection:list` | Показать сохранённые подключения |
| `connection:remove` | `b24cli connection:remove work` | Удалить локальный профиль и его учётные данные |
| `connection:show` | `b24cli connection:show work` | Показать безопасные параметры выбранного подключения |
| `connection:use` | `b24cli connection:use work` | Выбрать сохранённый профиль по умолчанию |

### Сотрудники

| Команда | Пример | Назначение |
| --- | --- | --- |
| `users:colleagues` | `b24cli users:colleagues --department-id 7 --json` | Коллеги текущего отдела по оргструктуре |
| `users:current` | `b24cli users:current --json` | Пользователь текущего подключения |
| `users:find` | `b24cli users:find --name "Иванов Иван" --json` | Найти активных сотрудников по ФИО или email |
| `users:manager` | `b24cli users:manager --department-id 7 --json` | Непосредственный или настроенный руководитель |

### Создание и редактирование задач

| Команда | Пример | Назначение |
| --- | --- | --- |
| `task:add` | `b24cli task:add --title "Подготовить договор" --creator 1 --responsible 42 --dry-run` | Создать задачу |
| `task:assign` | `b24cli task:assign 123 --responsible 3 --dry-run` | Сменить ответственного |
| `task:deadline:set` | `b24cli task:deadline:set 123 --at 2026-12-01T12:00:00+06:00 --dry-run` | Изменить срок задачи |
| `task:delete` | `b24cli task:delete 123 --dry-run` | Удалить задачу |
| `task:participants:set` | `b24cli task:participants:set 123 --auditor 4 --clear-accomplices --dry-run` | Заменить выбранные роли участников |
| `task:update` | `b24cli task:update 123 --title "Договор для согласования" --dry-run` | Изменить явные поля задачи |

### Просмотр и поиск задач

| Команда | Пример | Назначение |
| --- | --- | --- |
| `task:access:show` | `b24cli task:access:show 123` | Показать права текущего пользователя |
| `task:fields:list` | `b24cli task:fields:list --name title` | Показать поля задачи |
| `task:find` | `b24cli task:find --title "договор" --group-id 7 --responsible 42` | Найти задачи по буквальной подстроке title |
| `task:list` | `b24cli task:list --group-id 7 --responsible 42` | Показать доступные задачи |
| `task:my` | `b24cli task:my --user 42 --open --json` | Показать задачи выбранного исполнителя |
| `task:show` | `b24cli task:show 123` | Показать карточку задачи |

### Жизненный цикл

| Команда | Пример | Назначение |
| --- | --- | --- |
| `task:approve` | `b24cli task:approve 123 --dry-run` | Принять результат задачи |
| `task:complete` | `b24cli task:complete 123 --dry-run` | Завершить задачу или передать на контроль |
| `task:defer` | `b24cli task:defer 123 --dry-run` | Отложить задачу |
| `task:disapprove` | `b24cli task:disapprove 123 --dry-run` | Вернуть задачу на доработку |
| `task:pause` | `b24cli task:pause 123 --dry-run` | Приостановить выполнение задачи |
| `task:renew` | `b24cli task:renew 123 --dry-run` | Возобновить задачу |
| `task:start` | `b24cli task:start 123 --dry-run` | Начать работу над задачей |

### История и время в статусах

| Команда | Пример | Назначение |
| --- | --- | --- |
| `task:history:list` | `b24cli task:history:list 123 --json` | Показать историю изменений задачи |
| `task:status:durations` | `b24cli task:status:durations 123 --as-of 2026-10-01T12:00:00+03:00 --json` | Рассчитать календарное время задачи в статусах |

### Файлы

| Команда | Пример | Назначение |
| --- | --- | --- |
| `task:file:attach` | `b24cli task:file:attach 123 --file-id 50 --dry-run` | Прикрепить файл Диска или загрузить локальный файл |

### Результаты

| Команда | Пример | Назначение |
| --- | --- | --- |
| `task:result:add` | `b24cli task:result:add 123 --text "Договор готов" --dry-run` | Добавить результат задачи |
| `task:result:delete` | `b24cli task:result:delete 123 --result 17 --force --dry-run` | Удалить результат задачи |
| `task:result:list` | `b24cli task:result:list 123 --all` | Показать результаты задачи |
| `task:result:update` | `b24cli task:result:update 123 --result 17 --text "Договор проверен" --dry-run` | Изменить текст результата задачи |

### Чат задачи

| Команда | Пример | Назначение |
| --- | --- | --- |
| `task:chat:delete` | `b24cli task:chat:delete 123 --message 900 --force --dry-run` | Удалить сообщение чата задачи |
| `task:chat:list` | `b24cli task:chat:list 123 --limit 20` | Показать сообщения чата задачи |
| `task:chat:send` | `b24cli task:chat:send 123 --text "Договор готов" --dry-run` | Отправить сообщение в чат задачи |
| `task:chat:update` | `b24cli task:chat:update 123 --message 900 --text "Договор согласован" --dry-run` | Изменить сообщение чата задачи |

### Чек-листы

| Команда | Пример | Назначение |
| --- | --- | --- |
| `task:checklist:add` | `b24cli task:checklist:add 123 --title "Подготовка" --dry-run` | Создать чек-лист |
| `task:checklist:item:add` | `b24cli task:checklist:item:add 123 --checklist 10 --title "Проверить реквизиты" --dry-run` | Добавить пункт чек-листа |
| `task:checklist:item:complete` | `b24cli task:checklist:item:complete 123 --item 11 --dry-run` | Выполнить пункт чек-листа |
| `task:checklist:item:delete` | `b24cli task:checklist:item:delete 123 --item 11 --dry-run` | Удалить пункт с дочерними пунктами |
| `task:checklist:item:list` | `b24cli task:checklist:item:list 123 --checklist 10` | Показать пункты чек-листа |
| `task:checklist:item:renew` | `b24cli task:checklist:item:renew 123 --item 11 --dry-run` | Возобновить пункт чек-листа |
| `task:checklist:item:update` | `b24cli task:checklist:item:update 123 --item 11 --title "Уточнить реквизиты" --dry-run` | Изменить заголовок пункта |
| `task:checklist:list` | `b24cli task:checklist:list 123` | Показать чек-листы |

### Трудозатраты

| Команда | Пример | Назначение |
| --- | --- | --- |
| `task:time:add` | `b24cli task:time:add 123 --seconds 3600 --text "Подготовка договора" --dry-run` | Добавить запись времени |
| `task:time:delete` | `b24cli task:time:delete 123 --entry 30 --force --dry-run` | Удалить запись времени |
| `task:time:list` | `b24cli task:time:list 123` | Показать записи времени |
| `task:time:show` | `b24cli task:time:show 123` | Показать контекст учёта времени |
| `task:time:update` | `b24cli task:time:update 123 --entry 30 --seconds 1800 --dry-run` | Исправить запись времени |

### Диагностика агента

| Команда | Пример | Назначение |
| --- | --- | --- |
| `doctor:problem-trajectory` | `b24cli doctor:problem-trajectory --action status --json` | Сохранить и разобрать обезличенные карточки нехватки CLI |

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
