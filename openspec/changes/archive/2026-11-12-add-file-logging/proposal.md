# Proposal

## Why

CLI сейчас не пишет логи. `B24ClientProvider` и `config/services.php` передают b24phpsdk `NullLogger`. Поэтому после ошибки REST-вызова или сбоя команды не остаётся данных для разбора. Нужно файловое логирование: его включают в общем `config.json`, а записи каждых суток попадают в отдельный файл, который хранится 7 дней.

## What Changes

- Добавить в пользовательский `config.json` schema v1 optional корневой объект `logging`. Он устроен так же, как `telemetry`: действует на весь каталог конфигурации и не зависит от профиля портала.
  - `enabled` — boolean, по умолчанию `false`;
  - `level` — минимальный уровень PSR-3 в нижнем регистре (`debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency`), по умолчанию `info`;
  - `directory` — каталог логов или `null`. По умолчанию `null` — это `logs/` в каталоге конфигурации;
  - `fileMask` — маска имени файла с токенами `{YYYY}`, `{MM}`, `{DD}`, по умолчанию `b24cli-{YYYY}{MM}{DD}.log` (файл `b24cli-20261112.log`);
  - `retentionDays` — сколько дней хранить файлы, по умолчанию `7`.
- Читать `logging` из каталога конфигурации при любом способе подключения: профиль, `--env-file`, `B24CLI_ENV_FILE`, переменные процесса, dotenv исходников.
- Если `logging` задан неверно, команда не падает: CLI выключает логирование на этот запуск и печатает одно предупреждение в stderr. Профили читаются и записываются как раньше, а `logging` при записи сохраняется без изменений. Неверный `logging` отклоняют JSON Schema и `connection:show` показывает ошибку.
- Писать JSON Lines с `runId` процесса: начало и завершение команды (имя, код выхода, длительность), необработанные ошибки и события b24phpsdk.
- На уровнях выше `debug` вместо тел запросов и ответов SDK писать безопасную сводку (метод, `requestId`, HTTP-статус, длительность). На `debug` писать тела с ограничением длины; base64 и бинарные данные заменять их длиной.
- Маскировать webhook, токены и пароли во всех записях. Аргументы команд не писать.
- Не выводить лог в stdout. Ошибка записи лога не меняет результат и код выхода команды.
- Показывать действующие настройки `logging` и путь к файлу текущих суток в `connection:show`.
- Обновить `docs/config.schema.json`, общие fixtures, `ProfileRepository` и `docs/config.md`.

## Capabilities

### New Capabilities

- `cli-logging`: поведение файлового логирования: когда оно включено, что и в каком виде пишется, имена суточных файлов, хранение по дням, ограничение данных, маскирование секретов и обработка ошибок.

### Modified Capabilities

- `connection-configuration`: schema v1 принимает корневой объект `logging` со строгими типами и defaults. Записи профилей и telemetry сохраняют `logging`. `connection:show` показывает действующее логирование.

## Impact

- `src/Infrastructure/Connection/ProfileRepository.php` — корневой ключ `logging`: проверка и сохранение при записи.
- `src/Infrastructure/Connection/ConnectionManager.php` — чтение `logging` из каталога конфигурации независимо от источника подключения.
- Новые классы в `src/`: настройки логирования, фабрика логгера, суточный файловый handler, processors маскирования, ограничения данных и `runId`, подписчик на события консоли.
- `src/Infrastructure/Connection/B24ClientProvider.php`, `config/services.php` — логгер вместо `NullLogger`.
- Команда `connection:show` — блок `logging` в human, plain и JSON-выводе.
- `composer.json`, `composer.lock` — прямые зависимости `monolog/monolog` и `psr/log`.
- `docs/config.schema.json`, `docs/config.md`, `tests/Fixtures/configuration/schema-cases.json`, `build/tests/config-schema-check.py`, unit-тесты, `CHANGELOG.md`.
- Пока `logging` отсутствует или `enabled=false`, поведение CLI не меняется, кроме нового блока в `connection:show`.
- **Совместимость:** CLI до этой версии отклоняет `config.json` с ключом `logging` как неизвестный и перестаёт работать с этим каталогом конфигурации. Это нужно указать в CHANGELOG и `docs/config.md`.

Вне области:
- отдельная команда для изменения `logging` (настройки правят в `config.json` вручную по JSON Schema);
- переменные окружения для логирования;
- вывод лога в консоль и связь с `-v`;
- отправка логов во внешние системы, сжатие;
- терпимость будущих версий к неизвестным корневым ключам.

Файловый лог не заменяет карточки проблемных сценариев в `.problem-trajectories/`: карточки — структурированные данные для разбора с агентом, лог — хронология запуска.

## GitHub issues

- [#189 — Файловое логирование CLI: настройки logging в config.json, суточные файлы и хранение 7 дней](https://github.com/mesilov/bitrix24-cli/issues/189): change покрывает все требования issue — корневой объект `logging`, суточные файлы по маске, хранение 7 дней, маскирование секретов, ограничение данных и согласованность schema/runtime. Milestone: `0.3.0`; label: `enhancement`; тип: Feature.
