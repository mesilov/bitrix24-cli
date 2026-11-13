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

Подготовьте входящий webhook Битрикс24 с правами для нужных операций и войдите
на портал. `auth:login` запросит webhook скрытым вводом — вставьте его в ответ на запрос.
Перед сохранением CLI проверит webhook на портале и покажет его владельца; неверный
webhook не сохраняется. Первый профиль сразу становится профилем по умолчанию.

<details>
<summary>Linux — Bash</summary>

```sh
b24cli auth:login work
b24cli auth:status work
b24cli task:list --json
```

</details>

<details>
<summary>macOS — zsh</summary>

```sh
b24cli auth:login work
b24cli auth:status work
b24cli task:list --json
```

</details>

<details>
<summary>Windows — PowerShell</summary>

```powershell
b24cli.exe auth:login work
b24cli.exe auth:status work
b24cli.exe task:list --json
```

</details>

`auth:status` показывает владельца вебхука, выданные права и команды, которым прав
не хватает. Для каждого портала можно сохранить отдельный профиль: `auth:login clientA`,
затем `auth:switch clientA` или `--profile clientA`. Замена вебхука с сохранением настроек —
`auth:login work --replace`, выход — `auth:logout work`. Вход в CI, переменные окружения
и выбор источника credentials — в [руководстве по входу и профилям](docs/auth.md).

Значения по умолчанию и остальные несекретные настройки меняются без повторного ввода
вебхука:

```sh
b24cli config:set default.task.groupId 187
b24cli config:set default.users.observerId 5 --profile work
b24cli config:set logging.enabled true
b24cli config:list
```

Все ключи, типы значений и выбор профиля — в [руководстве по настройкам](docs/config.md#команды-config).

CLI проверяет разрешения вебхука перед работой с порталом и кеширует результат на сутки
в отдельном `state.json`. При нехватке прав блокируется только затронутая команда.
После изменения разрешений выполните `b24cli auth:status work --json`.
Подробности — в [руководстве по проверке разрешений](docs/auth-scopes.md).

## CLI-команды

ID, имена профилей и тексты в примерах замените своими. Команды изменения задач
приведены с `--dry-run`.

### Справка и автодополнение

| Команда | Назначение | Пример |
| --- | --- | --- |
| `completion` | Вывести скрипт автодополнения для оболочки | `b24cli completion bash` |
| `help` | Показать справку команды | `b24cli help task:add` |
| `list` | Показать список команд | `b24cli list task` |

### Вход и профили

| Команда | Назначение | Пример |
| --- | --- | --- |
| `auth:list` | Показать сохранённые профили и фактическое подключение | `b24cli auth:list` |
| `auth:login` | Сохранить вебхук портала как профиль и проверить его | `b24cli auth:login work` |
| `auth:logout` | Удалить локальный профиль и его учётные данные | `b24cli auth:logout work` |
| `auth:status` | Проверить вебхук: владелец, разрешения и требования CLI | `b24cli auth:status work --json` |
| `auth:switch` | Выбрать сохранённый профиль по умолчанию | `b24cli auth:switch work` |

### Настройки

| Команда | Назначение | Пример |
| --- | --- | --- |
| `config:get` | Показать действующее значение одной настройки | `b24cli config:get default.task.groupId` |
| `config:list` | Показать действующие настройки профиля и каталога конфигурации | `b24cli config:list` |
| `config:set` | Изменить одну настройку без повторного ввода вебхука | `b24cli config:set default.task.groupId 187` |
| `config:unset` | Удалить настройку и вернуть значение по умолчанию | `b24cli config:unset default.task.groupId` |

### Сотрудники

| Команда | Назначение | Пример |
| --- | --- | --- |
| `users:colleagues` | Коллеги текущего отдела по оргструктуре | `b24cli users:colleagues --department-id 7 --json` |
| `users:current` | Пользователь текущего подключения | `b24cli users:current --json` |
| `users:find` | Найти активных сотрудников по ФИО или email | `b24cli users:find --name "Иванов Иван" --json` |
| `users:manager` | Непосредственный или настроенный руководитель | `b24cli users:manager --department-id 7 --json` |
| `users:presence` | Последняя активность и отсутствие сотрудников | `b24cli users:presence --user-id 11 --user-id 22 --json` |

### Чаты

Подробнее — в [адресатах чатов](docs/chats.md).

| Команда | Назначение | Пример |
| --- | --- | --- |
| `chats:add` | Создать групповой чат с участниками для рассылки | `b24cli chats:add --title 'ИИ-новости' --user-id 12,34 --dry-run --json` |
| `chats:calls:list` | Звонки чата за период | `b24cli chats:calls:list sg234 --from 2026-11-09T00:00:00+03:00 --to 2026-11-16T00:00:00+03:00 --json` |
| `chats:calls:show` | Сведения об одном звонке чата | `b24cli chats:calls:show chat37 1001 --json` |
| `chats:find` | Найти чат по названию: групповые чаты, проекты и задачи | `b24cli chats:find --name 'Команда проекта' --json` |
| `chats:messages:list` | Показать сообщения чата за период | `b24cli chats:messages:list sg234 --period yesterday --timezone Europe/Moscow --json` |
| `chats:resolve` | Проверить адресат чата по ссылке или типизированному ID | `b24cli chats:resolve 'https://portal.example/workgroups/group/234/' --json` |
| `chats:send` | Отправить сообщение в чат по ссылке, ID или точному названию | `b24cli chats:send --name 'Команда проекта' --text 'Сегодня меня не будет' --dry-run --json` |

### Создание и редактирование задач

| Команда | Назначение | Пример |
| --- | --- | --- |
| `task:add` | Создать задачу | `b24cli task:add --title "Подготовить договор" --creator 1 --responsible 42 --dry-run` |
| `task:assign` | Сменить ответственного | `b24cli task:assign 123 --responsible 3 --dry-run` |
| [`task:handover`](docs/task-handover.md) | Передать выбранные задачи проекта; фильтр статуса, исключения ID и целевой статус | `b24cli task:handover --group-id 234 --from 14 --responsible 16 --dry-run` |
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

Дату записи задаёт `--created-date` у `task:time:add` и `task:time:update`: ISO 8601 с часовым поясом, в прошлом или будущем, например `b24cli task:time:add 123 --seconds 3600 --created-date 2026-10-22T10:00:00+03:00`. Без флага `task:time:add` датирует запись моментом вызова, а `task:time:update` сохраняет прежнюю дату; `--seconds` у `task:time:update` обязателен.

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
[недельный отчёт проекта](docs/task-weekly-report.md),
[сотрудники и оргструктура](docs/users.md), [адресаты чатов](docs/chats.md), [JTBD по ролям](docs/task-jtbd.md),
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

Настройки меняются командами `config:set` и `config:unset`, действующие значения
показывает `config:list`; вручную править файл не требуется.

Корневой объект `logging` включает суточный файловый лог в `logs/` рядом с `config.json`
с хранением 7 дней; по умолчанию он выключен: `b24cli config:set logging.enabled true`.
Поля и ограничения — в разделе [Файловое логирование](docs/config.md#файловое-логирование).

Формат, приоритеты, defaults и права доступа — в [руководстве по конфигу](docs/config.md),
машиночитаемый контракт — [JSON Schema](docs/config.schema.json).

---

[bitrix24-cli-license](LICENSE) основана на BSD-3-Clause.
Copyright © 2026 Maksim Mesilov <mesilov.maxim@gmail.com>.
Лицензии сторонних компонентов сохранены в пакетах отдельно.
Все упоминаемые товарные знаки принадлежат их владельцам.
