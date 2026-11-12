# Design

## Context

Мотивация описана в proposal.md, раздел Why.

Текущее состояние в `dev`:

- `ProfileRepository` проверяет корневые ключи `config.json` по списку `version`, `defaultProfile`, `profiles`, `telemetry`. Неизвестный ключ даёт `configuration-error`.
- `TelemetrySettings` хранит `DEFAULTS`, проверяет типы (`validate`) и возвращает эффективные значения (`effective`), не записывая defaults в файл.
- `ConnectionManager::selected()` для `--env-file`, `B24CLI_ENV_FILE` и переменных процесса возвращается до `ProfileRepository::load()`. В этих режимах `config.json` сейчас не читается.
- `B24ClientProvider` создаёт `CoreBuilder`, `Batch`, `BulkItemsReaderBuilder` и `ServiceBuilder` с `NullLogger`; `config/services.php` передаёт `NullLogger` в `Base64Encoder`. `B24ClientProvider::redact()` заменяет webhook и токен на `[redacted]`. `RuntimeState::generation` позволяет сменить webhook внутри процесса.
- b24phpsdk на уровне `info` пишет `getResponse.start` с полным `parameters` и `getResponse.end` с `responseInfo` клиента Symfony HttpClient (включая `url` с токеном). На `debug` он пишет `getResponse.requestPayload` с `url` и `requestOptions`. Для исключений в SDK есть `ExceptionContextSanitizer`.
- `ResultPresenter::diagnostic()` пишет служебные сообщения в stderr. Отчёты используют часовой пояс `getenv('TZ') ?: date_default_timezone_get()`.
- `psr/log` приходит только транзитивно через b24phpsdk, Monolog не установлен.

## Goals / Non-Goals

**Goals:**

- Один логгер PSR-3 на процесс, общий для кода CLI и b24phpsdk.
- Нулевые затраты при выключенном логировании: `NullLogger`, без файловых операций.
- Неверный `logging` никогда не ломает команды и хранение профилей.
- Объём и содержимое лога предсказуемы при любом уровне.

**Non-Goals:**

- Команда для изменения `logging`.
- Ротация по размеру файла, сжатие, внешние системы.
- Изменение формата существующих ошибок CLI.
- Терпимость будущих версий к неизвестным корневым ключам: это отдельное решение о совместимости формата.

## Decisions

### Корневой объект `logging` в `config.json`, отдельный от проверки профилей

`LoggingSettings` повторяет API `TelemetrySettings` (`DEFAULTS`, `validate`, `effective`), но возвращает результат проверки, а не бросает `configuration-error`: `effective()` даёт `{settings, status: ok|disabled|invalid, reason}`.

`ProfileRepository` добавляет `logging` в разрешённые корневые ключи. При загрузке он не проверяет содержимое `logging` и отдаёт его как есть. При записи он сохраняет исходное значение без нормализации: даже невалидное значение сохраняет то же JSON-значение. Так опечатка в `logging` не мешает `connection:*` и doctor.

Альтернативы:
- строгая проверка как у `telemetry` — отклонено: опечатка во вспомогательной настройке блокировала бы все команды;
- переменные окружения `B24CLI_LOG_*` — отклонено: пользователь выбрал общий конфиг.

### Чтение `logging` не зависит от источника подключения

Новый `LoggingSettingsReader` берёт каталог через `ConnectionManager::directory()` и читает только `config.json` этого каталога: без lock, без credentials и без изменения выбора подключения. Ошибка чтения или JSON даёт статус `invalid`. Отсутствие файла даёт `disabled`. Профильные defaults не применяются.

### Логгер включается в `B24Application::run()`

`B24Application` не использует диспетчер событий Symfony и сам перехватывает исключения в `run()`, поэтому жизненный цикл пишется там, а не подписчиком `ConsoleEvents`. Так покрываются все команды, включая `connection:*` и `doctor:*`, и все пути отказа. `run()` определяет имя известной команды, берёт `--config-dir` из сырого ввода и вызывает `LoggingRuntime::activate()`. До этого `LoggingRuntime` — два Monolog-логгера (`cli` и `sdk`) без handler, записи отбрасываются. SDK получает отдельный экземпляр `sdk`, а не `withName()`: клон не увидел бы handler, добавленный позже. Команды `help`, `list`, completion и запуски с `--help`, `--version`, `--about` не включают логирование. Неизвестное имя команды не пишется: оно может содержать данные пользователя. Ошибки `Launcher` до создания приложения в лог не попадают.

### Суточный handler по маске и хранение по дням

Маска использует токены в фигурных скобках `{YYYY}`, `{MM}`, `{DD}`. Буквенные токены отклонены: литерал вроде `ADDON` содержит `DD`. Маска компилируется в regex: литералы экранируются, токены становятся группами `(\d{4})`, `(\d{2})`.

Свой `DailyMaskFileHandler` на базе Monolog `StreamHandler` (`useLocking=true`) вычисляет путь по дате записи в поясе `TZ` или PHP по умолчанию, переоткрывает поток при смене суток и запускает очистку при первой записи и при смене суток. Очистка удаляет только файлы, имя которых полностью совпадает с regex и дата из имени раньше `today - (retentionDays - 1)`. Ошибки `unlink` игнорируются.

Альтернатива: Monolog `RotatingFileHandler` — отклонено: один плейсхолдер `{date}` в фиксированной форме, хранение по числу файлов, а не по дням, и очистка по glob.

### Обработка записей SDK

Processors применяются в таком порядке:

1. `SdkSummaryProcessor` (только канал `sdk`, только при `logging.level` выше `debug`) заменяет контекст `getResponse.start` и `getResponse.end` сводкой: `apiMethod`, `apiVersion`, `requestId`, HTTP-статус и длительность, измеренная между start и end. У остальных записей SDK удаляются ключи с параметрами, телами, заголовками, `responseInfo`, `url` и `trace`, а составные значения заменяются на `[omitted]`. Решение зависит от настроенного уровня, а не от уровня записи: при `level=info` запись `getResponse.start` уровня `info` теряет `parameters`.
2. `SecretRedactingProcessor` заменяет на `[redacted]` все webhook и токены, которые `B24ClientProvider` зарегистрировал за процесс, токен в любом пути `/rest/{userId}/{token}/` и значения ключей, в имени которых есть слово `auth`, `password`, `secret`, `token` или `webhook`. Имя разбивается на слова по camelCase, snake_case и kebab-case, поэтому `authorId` не маскируется. Маскирование идёт до обрезки, чтобы обрезка не оставила часть токена.
3. `SizeLimitProcessor` обрезает строки длиннее 4096 байт с пометкой `…[truncated N bytes]`, заменяет base64-подобные значения от 256 байт на `[base64 N bytes]`, бинарные строки на `[binary N bytes]`, ограничивает глубину 8 уровнями и 200 элементами.

Формат — собственный `JsonLinesFormatter`: одна строка с полями `datetime`, `level`, `channel`, `message`, `context`, `runId` (UUID v4 на процесс). Если строка больше 64 KiB, контекст заменяется на `{truncated, originalBytes}`. Monolog `JsonFormatter` не подошёл: у него другой набор полей.

### Жизненный цикл команды

`B24Application::run()` пишет `command.start`, `command.finish` с кодом выхода и длительностью и `command.error` для исключений. Пишется только имя команды. Для ошибки пишутся код ошибки CLI, класс исключения и публичное сообщение `Failure`, но не исходное сообщение исключения: оно может содержать аргументы или данные портала.

### Ошибки записи и предупреждения

Handler и reader перехватывают все исключения. Первое из них даёт одно сообщение через `ResultPresenter::diagnostic()` в stderr. Дальше записи отбрасываются до конца процесса.

### `connection:show`

Команда добавляет блок `logging` с эффективными значениями, абсолютными путями каталога и файла текущих суток и статусом из `LoggingSettings::effective()`. Блок вычисляется через `LoggingSettingsReader` без создания каталога. В human и plain режимах колонка `logging` появляется только у `connection:show`, вывод `connection:list` не меняется.

### Права доступа

На Unix новый каталог создаётся с правами 0700, новый файл — с 0600. На Windows применяется существующий механизм ACL managed storage. Права существующего каталога пользователя не меняются.

## Risks / Trade-offs

- [Сводка SDK зависит от имён сообщений `getResponse.start` и `getResponse.end`] → тест фиксирует сводку на текущей версии b24phpsdk. При обновлении SDK неизвестные записи уровней выше `debug` проходят `SizeLimitProcessor` и маскирование.
- [Персональные данные на `debug` всё равно попадают в лог] → документация предупреждает об этом, данные ограничены по размеру, секреты маскируются.
- [Неверный `logging` легко не заметить] → одно предупреждение в stderr на каждый запуск и статус `invalid` в `connection:show`.
- [Удаление файлов в каталоге пользователя] → удаляются только файлы, полностью совпадающие с regex маски.
- [Логирование ломается в standalone или PHAR] → smoke-проверка пакета пишет лог во временный `--config-dir`.
- [Старый CLI отклоняет `config.json` с `logging`] → предупреждение в CHANGELOG и `docs/config.md`. Откат: удалить ключ `logging`.

## Migration Plan

Миграция не нужна: `config.json` без `logging` остаётся валидным, логирование по умолчанию выключено. Откат на прежнюю версию CLI требует удалить `logging` из `config.json`.
