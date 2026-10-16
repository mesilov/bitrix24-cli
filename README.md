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
(
  set -o pipefail
  curl -fsSL https://raw.githubusercontent.com/mesilov/bitrix24-cli/main/install.sh | sh
) &&
. "$HOME/.local/share/b24cli/env" &&
b24cli --version
```

### macOS arm64 (zsh)

```sh
(
  set -o pipefail
  curl -fsSL https://raw.githubusercontent.com/mesilov/bitrix24-cli/main/install.sh | sh
) &&
. "$HOME/.local/share/b24cli/env" &&
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

CLI проверяет разрешения вебхука перед работой с порталом и кеширует результат на сутки
в отдельном `state.json`. При нехватке прав блокируется только затронутая команда.
После изменения разрешений выполните `b24cli connection:check work --json`.
Подробности — в [руководстве по проверке разрешений](docs/connection-scopes.md).

## CLI-команды

ID, имена профилей и тексты в примерах замените своими. Команды изменения задач
приведены с `--dry-run`.

### Справка и автодополнение

| Команда | Назначение | Пример |
| --- | --- | --- |
| `completion` | Вывести скрипт автодополнения для оболочки | `b24cli completion bash` |
| `help` | Показать справку команды | `b24cli help task:add` |
| `list` | Показать список команд | `b24cli list task` |

### Подключения

| Команда | Назначение | Пример |
| --- | --- | --- |
| `connection:add` | Сохранить локальное подключение | `b24cli connection:add work --group-id 7 --creator-id 1 --responsible-id 42` |
| `connection:check` | Проверить разрешения вебхука и требования CLI | `b24cli connection:check work --json` |
| `connection:list` | Показать сохранённые подключения | `b24cli connection:list` |
| `connection:remove` | Удалить локальный профиль и его учётные данные | `b24cli connection:remove work` |
| `connection:show` | Показать безопасные параметры выбранного подключения | `b24cli connection:show work` |
| `connection:use` | Выбрать сохранённый профиль по умолчанию | `b24cli connection:use work` |

### Сотрудники

| Команда | Назначение | Пример |
| --- | --- | --- |
| `users:colleagues` | Коллеги текущего отдела по оргструктуре | `b24cli users:colleagues --department-id 7 --json` |
| `users:current` | Пользователь текущего подключения | `b24cli users:current --json` |
| `users:find` | Найти активных сотрудников по ФИО или email | `b24cli users:find --name "Иванов Иван" --json` |
| `users:manager` | Непосредственный или настроенный руководитель | `b24cli users:manager --department-id 7 --json` |

### Создание и редактирование задач

| Команда | Назначение | Пример |
| --- | --- | --- |
| `task:add` | Создать задачу | `b24cli task:add --title "Подготовить договор" --creator 1 --responsible 42 --dry-run` |
| `task:assign` | Сменить ответственного | `b24cli task:assign 123 --responsible 3 --dry-run` |
| `task:deadline:set` | Изменить срок задачи | `b24cli task:deadline:set 123 --at 2026-12-01T12:00:00+06:00 --dry-run` |
| `task:delete` | Удалить задачу | `b24cli task:delete 123 --dry-run` |
| `task:participants:set` | Заменить выбранные роли участников | `b24cli task:participants:set 123 --auditor 4 --clear-accomplices --dry-run` |
| `task:update` | Изменить явные поля задачи | `b24cli task:update 123 --title "Договор для согласования" --dry-run` |

### Шаблоны задач

| Команда | Назначение | Пример |
| --- | --- | --- |
| `task:template:show` | Прочитать шаблон по ID | `b24cli task:template:show 139 --json` |
| `task:template:fields:list` | Получить описание полей | `b24cli task:template:fields:list --json` |
| `task:template:add` | Создать шаблон | `b24cli task:template:add --title "Типовая задача" --creator 1 --responsible 42 --dry-run` |
| `task:template:update` | Изменить явные поля шаблона | `b24cli task:template:update 139 --description "Проверить реквизиты" --dry-run` |
| `task:template:delete` | Запросить удаление шаблона | `b24cli task:template:delete 139 --dry-run` |

Все пять команд используют REST 1.0 и scope `task`; `strict-rest3` запрещает их.
Удаление возвращает частичное подтверждение (exit 3), поскольку API не подтверждает
результат. Поля, JSON/stdin и ограничения — в [руководстве](docs/task-usage.md#шаблоны-задач).

### Просмотр и поиск задач

| Команда | Назначение | Пример |
| --- | --- | --- |
| `task:access:show` | Показать права текущего пользователя | `b24cli task:access:show 123` |
| `task:fields:list` | Показать поля задачи | `b24cli task:fields:list --name title` |
| `task:find` | Найти задачи по буквальной подстроке title | `b24cli task:find --title "договор" --group-id 7 --responsible 42` |
| `task:list` | Показать доступные задачи | `b24cli task:list --group-id 7 --responsible 42` |
| `task:my` | Показать задачи выбранного исполнителя | `b24cli task:my --user 42 --open --json` |
| `task:show` | Показать карточку задачи | `b24cli task:show 123` |

### Жизненный цикл

| Команда | Назначение | Пример |
| --- | --- | --- |
| `task:approve` | Принять результат задачи | `b24cli task:approve 123 --dry-run` |
| `task:complete` | Завершить задачу или передать на контроль | `b24cli task:complete 123 --dry-run` |
| `task:defer` | Отложить задачу | `b24cli task:defer 123 --dry-run` |
| `task:disapprove` | Вернуть задачу на доработку | `b24cli task:disapprove 123 --dry-run` |
| `task:pause` | Приостановить выполнение задачи | `b24cli task:pause 123 --dry-run` |
| `task:renew` | Возобновить задачу | `b24cli task:renew 123 --dry-run` |
| `task:start` | Начать работу над задачей | `b24cli task:start 123 --dry-run` |

### История и время в статусах

| Команда | Назначение | Пример |
| --- | --- | --- |
| `task:history:list` | Показать историю изменений задачи | `b24cli task:history:list 123 --json` |
| `task:status:durations` | Рассчитать календарное время задачи в статусах | `b24cli task:status:durations 123 --as-of 2026-10-01T12:00:00+03:00 --json` |

### Файлы

| Команда | Назначение | Пример |
| --- | --- | --- |
| `task:file:attach` | Прикрепить файл Диска или загрузить локальный файл | `b24cli task:file:attach 123 --file-id 50 --dry-run` |

### Результаты

| Команда | Назначение | Пример |
| --- | --- | --- |
| `task:result:add` | Добавить результат задачи | `b24cli task:result:add 123 --text "Договор готов" --dry-run` |
| `task:result:delete` | Удалить результат задачи | `b24cli task:result:delete 123 --result 17 --force --dry-run` |
| `task:result:list` | Показать результаты задачи | `b24cli task:result:list 123 --all` |
| `task:result:update` | Изменить текст результата задачи | `b24cli task:result:update 123 --result 17 --text "Договор проверен" --dry-run` |

### Чат задачи

| Команда | Назначение | Пример |
| --- | --- | --- |
| `task:chat:delete` | Удалить сообщение чата задачи | `b24cli task:chat:delete 123 --message 900 --force --dry-run` |
| `task:chat:list` | Показать сообщения чата задачи | `b24cli task:chat:list 123 --limit 20` |
| `task:chat:send` | Отправить сообщение в чат задачи | `b24cli task:chat:send 123 --text "Договор готов" --dry-run` |
| `task:chat:update` | Изменить сообщение чата задачи | `b24cli task:chat:update 123 --message 900 --text "Договор согласован" --dry-run` |

### Чек-листы

| Команда | Назначение | Пример |
| --- | --- | --- |
| `task:checklist:add` | Создать чек-лист | `b24cli task:checklist:add 123 --title "Подготовка" --dry-run` |
| `task:checklist:item:add` | Добавить пункт чек-листа | `b24cli task:checklist:item:add 123 --checklist 10 --title "Проверить реквизиты" --dry-run` |
| `task:checklist:item:complete` | Выполнить пункт чек-листа | `b24cli task:checklist:item:complete 123 --item 11 --dry-run` |
| `task:checklist:item:delete` | Удалить пункт с дочерними пунктами | `b24cli task:checklist:item:delete 123 --item 11 --dry-run` |
| `task:checklist:item:list` | Показать пункты чек-листа | `b24cli task:checklist:item:list 123 --checklist 10` |
| `task:checklist:item:renew` | Возобновить пункт чек-листа | `b24cli task:checklist:item:renew 123 --item 11 --dry-run` |
| `task:checklist:item:update` | Изменить заголовок пункта | `b24cli task:checklist:item:update 123 --item 11 --title "Уточнить реквизиты" --dry-run` |
| `task:checklist:list` | Показать чек-листы | `b24cli task:checklist:list 123` |

### Трудозатраты

| Команда | Назначение | Пример |
| --- | --- | --- |
| `task:time:add` | Добавить запись времени | `b24cli task:time:add 123 --seconds 3600 --text "Подготовка договора" --dry-run` |
| `task:time:delete` | Удалить запись времени | `b24cli task:time:delete 123 --entry 30 --force --dry-run` |
| `task:time:list` | Показать записи времени | `b24cli task:time:list 123` |
| `task:time:show` | Показать контекст учёта времени | `b24cli task:time:show 123` |
| `task:time:update` | Исправить запись времени | `b24cli task:time:update 123 --entry 30 --seconds 1800 --dry-run` |

### Диагностика агента

| Команда | Назначение | Пример |
| --- | --- | --- |
| `doctor:problem-trajectory` | Сохранить и разобрать обезличенные карточки нехватки CLI | `b24cli doctor:problem-trajectory --action status --json` |

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
