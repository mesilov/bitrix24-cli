# cli-logging Specification

## Purpose
Сохранять локальный файловый лог работы CLI и b24phpsdk для разбора ошибок, не меняя вывод команд, не раскрывая секреты и не накапливая данные пользователя без ограничений.

## Requirements

### Requirement: Logging is opt-in
CLI SHALL писать файловый лог только при `logging.enabled=true` в `config.json` выбранного каталога конфигурации. Без `config.json`, без `logging` или при `enabled=false` CLI MUST NOT создавать каталог и файлы логов.

#### Scenario: Default configuration
- **WHEN** `config.json` отсутствует либо не содержит `logging`
- **THEN** команда выполняется как раньше, а каталог и файлы логов не создаются

#### Scenario: Enabled logging
- **WHEN** `logging.enabled=true` и выполняется команда CLI
- **THEN** CLI создаёт каталог логов при его отсутствии и дописывает записи в файл текущих суток

#### Scenario: Commands without configuration access
- **WHEN** выполняется `--help`, `--version`, `list` или completion
- **THEN** CLI не читает конфигурацию ради логирования и не создаёт файлы логов

### Requirement: Logging settings independent of connection source
CLI SHALL читать `logging` из `config.json` каталога конфигурации (`--config-dir`, `B24CLI_CONFIG_DIR` или OS default) при любом источнике подключения: сохранённый профиль, `--env-file`, `B24CLI_ENV_FILE`, переменные процесса или dotenv исходников. Чтение `logging` MUST NOT менять выбор подключения и MUST NOT применять профильные defaults.

#### Scenario: Process webhook with logging enabled
- **WHEN** задан `BITRIX24_WEBHOOK`, а `config.json` каталога конфигурации содержит `logging.enabled=true`
- **THEN** команда использует webhook процесса и пишет лог в каталог из этого `config.json`

#### Scenario: Process webhook without config
- **WHEN** задан `BITRIX24_WEBHOOK`, а `config.json` отсутствует
- **THEN** логирование выключено, команда выполняется как раньше и ничего не создаёт

### Requirement: Invalid logging settings do not block commands
Если `logging` или весь `config.json` нельзя разобрать или проверить, CLI SHALL выключить логирование на этот запуск и напечатать в stderr одно предупреждение без секретов. Результат, stdout и код выхода команды MUST оставаться такими же, как без логирования, если команда сама не требует этот `config.json`.

#### Scenario: Wrong level in env mode
- **WHEN** задан `BITRIX24_WEBHOOK`, а `logging.level="Info"`
- **THEN** команда выполняется с webhook процесса, лог не пишется, stderr содержит одно предупреждение о неверном `logging`

#### Scenario: Profile command with broken logging
- **WHEN** команда использует сохранённый профиль, а `logging.retentionDays=0`
- **THEN** команда работает с профилем, лог не пишется, stderr содержит одно предупреждение

### Requirement: Log directory location
Каталог логов SHALL определяться `logging.directory`. Значение `null` или отсутствие поля MUST означать `logs/` внутри каталога конфигурации. Относительный путь MUST отсчитываться от каталога конфигурации, а не от cwd. CLI MUST NOT раскрывать `~` и переменные окружения в пути.

#### Scenario: Default directory follows config directory
- **WHEN** команда запущена с `--config-dir /tmp/b24` и `logging.directory` не задан
- **THEN** файлы логов создаются в `/tmp/b24/logs/`

#### Scenario: Relative directory
- **WHEN** `logging.directory="var/log"` и cwd отличается от каталога конфигурации
- **THEN** файлы логов создаются в `var/log/` внутри каталога конфигурации

#### Scenario: Absolute Windows directory
- **WHEN** на Windows `logging.directory="C:\\b24cli\\logs"`
- **THEN** файлы логов создаются в `C:\b24cli\logs`

### Requirement: Daily file name from mask
Имя файла SHALL строиться из `logging.fileMask` заменой `{YYYY}`, `{MM}`, `{DD}` на дату записи. Дата MUST вычисляться в часовом поясе `TZ`, если он задан, иначе в часовом поясе PHP по умолчанию. Маска по умолчанию — `b24cli-{YYYY}{MM}{DD}.log`. Записи разных суток MUST попадать в разные файлы, в том числе в одном долгом процессе.

#### Scenario: Default mask
- **WHEN** логирование включено с маской по умолчанию и запись сделана 12 ноября 2026 года
- **THEN** запись попадает в файл `b24cli-20261112.log`

#### Scenario: Timezone from TZ
- **WHEN** `TZ=Asia/Omsk` и запись сделана 11 ноября 2026 года в 20:30 UTC
- **THEN** запись попадает в `b24cli-20261112.log`

#### Scenario: Midnight during a command
- **WHEN** команда начинается 12 ноября и пишет запись после полуночи 13 ноября
- **THEN** новая запись попадает в `b24cli-20261113.log`, а прежние записи остаются в `b24cli-20261112.log`

### Requirement: Log retention by days
CLI SHALL удалять файлы логов, у которых дата из имени раньше текущих суток больше чем на `logging.retentionDays - 1` дней; по умолчанию `retentionDays=7` хранит текущие сутки и 6 предыдущих. Проверка MUST выполняться при первой записи процесса и при смене суток. CLI MUST NOT удалять файлы, имя которых не соответствует маске.

#### Scenario: Old files removed
- **WHEN** 12 ноября в каталоге есть файлы за 1, 5, 6 и 12 ноября и `retentionDays=7`
- **THEN** после первой записи остаются файлы за 6 и 12 ноября

#### Scenario: Gaps in usage
- **WHEN** CLI не запускали неделю, а в каталоге лежат 3 файла старше 7 дней
- **THEN** при первой записи все 3 файла удаляются, остаётся только файл текущих суток

#### Scenario: Foreign files preserved
- **WHEN** в каталоге логов лежат `notes.txt` и `b24cli-latest.log`
- **THEN** удаление их не затрагивает

#### Scenario: Concurrent cleanup
- **WHEN** два процесса одновременно удаляют один устаревший файл
- **THEN** оба продолжают работу, ошибка удаления уже удалённого файла не выводится

### Requirement: Level filtering
CLI SHALL записывать только события с уровнем не ниже `logging.level` по шкале PSR-3. По умолчанию уровень `info`.

#### Scenario: Debug messages filtered
- **WHEN** `logging.level="warning"` и происходят события `debug`, `info` и `error`
- **THEN** в файл попадает только событие `error`

### Requirement: Record format and correlation
Каждая запись SHALL быть одной строкой JSON (JSON Lines) с полями `datetime` (ISO 8601 со смещением), `level`, `channel`, `message`, `context` и `runId`. `runId` MUST быть одинаковым для всех записей одного процесса и разным у разных процессов.

#### Scenario: Two parallel commands
- **WHEN** две команды одновременно пишут в один файл суток
- **THEN** каждая строка является целой JSON-записью, а по `runId` записи разделяются на два запуска

### Requirement: Command lifecycle events
CLI SHALL записывать на уровне `info` начало и завершение команды с её именем, кодом выхода и длительностью, а необработанные ошибки — на уровне `error`. Значения аргументов и опций команды MUST NOT записываться.

#### Scenario: Partial result
- **WHEN** логирование включено и команда завершается с кодом 3
- **THEN** в файле есть запись о начале и запись о завершении с именем команды, кодом 3 и длительностью

#### Scenario: Arguments not logged
- **WHEN** команда запущена с `--title "Договор"` и `--webhook-stdin`
- **THEN** в файле нет значения `Договор` и данных stdin

### Requirement: SDK request data limits
На уровнях выше `debug` CLI SHALL писать о REST-вызовах SDK только сводку: метод API, `requestId`, HTTP-статус и длительность. Параметры запроса, тела, заголовки и `responseInfo` MUST NOT попадать в такие записи.

#### Scenario: Info level hides payload
- **WHEN** `logging.level="info"` и `task:add` создаёт задачу с описанием «Секретный договор»
- **THEN** в файле есть сводка вызова, но нет текста «Секретный договор» и параметров запроса

### Requirement: Debug data size limits
На уровне `debug` CLI SHALL писать данные SDK с ограничением: строка контекста длиннее 4096 байт MUST обрезаться с пометкой исходной длины, а base64 и бинарные значения MUST заменяться их длиной. Одна запись MUST NOT превышать 64 KiB.

#### Scenario: File upload at debug
- **WHEN** `logging.level="debug"` и `task:file:attach` загружает файл 100 MB
- **THEN** запись о вызове содержит длину содержимого файла, но не само содержимое, и её размер не больше 64 KiB

### Requirement: Secrets never logged
CLI MUST маскировать в сообщениях и контексте любого уровня: полный webhook, его токен, токен в пути `/rest/{userId}/{token}/`, содержимое credential files и значения ключей, имя которых после разбиения на слова (camelCase, snake_case, kebab-case) без учёта регистра содержит слово `auth`, `password`, `secret`, `token` или `webhook`.

#### Scenario: Webhook in SDK response info
- **WHEN** уровень `debug` и SDK пишет URL запроса с токеном вебхука
- **THEN** файл содержит `[redacted]` вместо токена, а полный webhook и токен в файле не встречаются

#### Scenario: Token-like key
- **WHEN** контекст записи содержит ключи `access_token`, `refreshToken` и `authorId`
- **THEN** значения `access_token` и `refreshToken` заменены на `[redacted]`, а `authorId` сохраняется

### Requirement: Logging does not change command output
Лог MUST NOT выводиться в stdout. Ошибка создания каталога, записи или удаления файла MUST NOT менять результат, stdout и код выхода команды. В stderr допускается не больше одного предупреждения о логировании за запуск.

#### Scenario: Unwritable directory
- **WHEN** логирование включено, а каталог логов недоступен для записи
- **THEN** команда возвращает тот же результат и код выхода, что и без логирования, и печатает в stderr одно предупреждение без секретов

#### Scenario: JSON output unchanged
- **WHEN** логирование включено и команда запущена с `--json`
- **THEN** stdout содержит тот же JSON, что и без логирования

### Requirement: Log file protection
На Unix CLI SHALL создавать каталог логов с правами 0700, а файлы логов — с правами 0600. На Windows новые каталог и файлы MUST получать ту же защиту, что и managed storage конфигурации. Права существующего каталога, указанного пользователем, CLI MUST NOT менять. CLI MUST писать только в обычный файл и MUST NOT следовать symbolic link на месте файла суток.

#### Scenario: New log file permissions
- **WHEN** на Linux или macOS создаются каталог по умолчанию и новый файл суток
- **THEN** у каталога права 0700, а у файла 0600

#### Scenario: Symbolic link instead of daily file
- **WHEN** в каталоге логов файл текущих суток — symbolic link на другой файл или на несуществующий путь
- **THEN** CLI не пишет по ссылке, цель ссылки не меняется и не создаётся, а stderr содержит одно предупреждение

### Requirement: Initialization failures outside log
Ошибки, которые происходят до выбора каталога конфигурации и создания логгера, SHALL обрабатываться как раньше и MAY не попадать в файловый лог.

#### Scenario: Broken service definitions
- **WHEN** CLI не может создать контейнер сервисов
- **THEN** CLI печатает прежнюю ошибку `configuration-error` и возвращает код 1, файл лога может не создаваться
