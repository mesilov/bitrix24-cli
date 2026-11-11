# Tasks

## 0. Трассировка

- [x] 0.1 Создать GitHub issue через `gh` с milestone и label, добавить его ссылку в раздел `## GitHub issues` proposal.md и раздел `## OpenSpec` в issue. Переименовать ветку в `feature/<issueID>-file-logging`. Проверить обе ссылки через `gh issue view`.

## 1. Зависимости

- [x] 1.1 Добавить `monolog/monolog` и `psr/log` в `require` через `make composer ARGS="require ..."`, проверить `make composer-validate` и `make lint-allowed-licenses`.

## 2. Настройки `logging`

- [x] 2.1 Добавить `LoggingSettings` (`DEFAULTS`, `effective` со статусом `ok|disabled|invalid` и причиной), включая правила маски с `{YYYY}`, `{MM}`, `{DD}`, запрещённые символы, литеральные integer и `retentionDays`. Проверить unit-тестом на defaults, частичный объект и каждое неверное значение.
- [x] 2.2 Разрешить корневой ключ `logging` в `ProfileRepository` без проверки содержимого при чтении и с сохранением исходного значения при записи. Проверить тестами: `connection:add/use/remove` и doctor configure не меняют валидный и невалидный `logging` и не дают `configuration-error`; `logging` внутри профиля или runtime `--config` не считается корневой настройкой.
- [x] 2.3 Добавить `LoggingSettingsReader`: чтение `config.json` каталога конфигурации при любом источнике подключения, без lock и credentials. Проверить тестами с `BITRIX24_WEBHOOK`, `--env-file` и профилем, а также без `config.json`.
- [x] 2.4 Описать `logging` в `docs/config.schema.json` (включая pattern маски), добавить positive и negative cases в `tests/Fixtures/configuration/schema-cases.json`. Проверить `build/tests/config-schema-check.py` и `make unit TEST_ARGS="--filter ConfigSchemaContractTest"`.
- [x] 2.5 Добавить раздел о `logging` в `docs/config.md`: пример, defaults, путь `logs/`, хранение 7 дней, часовой пояс, сводка SDK выше `debug`, предупреждение о персональных данных на `debug`, несовместимость с прежними версиями. Проверить, что JSON-примеры проходят schema check.

## 3. Handler и processors

- [x] 3.1 Реализовать `DailyMaskFileHandler`: путь по дате записи в поясе `TZ` или PHP, переоткрытие при смене суток, блокировка, права 0700/0600 для новых каталога и файла. Проверить unit-тестами с фиксированными часами: имя по маске, `TZ=Asia/Omsk`, переход через полночь, права.
- [x] 3.2 Реализовать очистку по `retentionDays` при первой записи и смене суток только по regex маски, с игнорированием ошибок `unlink`. Проверить тестами: удаление файлов старше 7 дней, перерыв в использовании, сохранение посторонних файлов, повторное удаление уже удалённого файла.
- [x] 3.3 Реализовать обработку ошибок: одно предупреждение через `ResultPresenter::diagnostic()`, дальше записи отбрасываются. Проверить тестом с каталогом без прав записи и с неверным `logging`: stdout и код выхода команды не меняются, предупреждение одно.
- [x] 3.4 Реализовать `SdkSummaryProcessor`. Проверить тестом на `info`: в файле нет `parameters`, `responseInfo`, `url` и текста описания задачи, есть метод, `requestId`, статус и длительность.
- [x] 3.5 Реализовать `SizeLimitProcessor` (4096 байт на строку, base64 и бинарные значения как длина, 64 KiB на запись). Проверить тестом загрузки большого файла на `debug`: запись не больше 64 KiB и не содержит данных файла.
- [x] 3.6 Реализовать `SecretRedactingProcessor` (webhook и токен всех поколений `RuntimeState`, ключи с `auth`, `password`, `secret`, `token`, `webhook` без учёта регистра) и `RunIdProcessor`. Проверить тестами: полного webhook и токена в файле нет после debug-вызова и смены поколения; `access_token` и `refreshToken` замаскированы; `runId` одинаков в процессе.
- [x] 3.7 Добавить фабрику логгера: `NullLogger` или Monolog без handler при выключенном логировании, иначе `JsonFormatter`, фильтр уровня и processors в порядке из design. Проверить тестом, что при `enabled=false` каталог не создаётся, а `level=warning` отсекает `info`.

## 4. Подключение к CLI и SDK

- [x] 4.1 Зарегистрировать логгер в контейнере (`config/services.php`, `ContainerFactory`) и передать его в `B24ClientProvider` (`CoreBuilder`, `Batch`, `BulkItemsReaderBuilder`, `ServiceBuilder`) и `Base64Encoder` вместо `NullLogger`. Проверить `ContainerRegistrationTest` и тестом с fake transport, что сводка вызова SDK попадает в файл.
- [x] 4.2 Добавить подписчика на события консоли: включение handler после разбора `--config-dir`, начало, завершение с кодом и длительностью, ошибки, без аргументов. Проверить тестом JSON Lines для успешной команды, кода 3 и исключения; отсутствие `Договор` при `--title "Договор"`; отсутствие файлов для `--help`, `--version`, `list`.
- [x] 4.3 Добавить блок `logging` в `connection:show` (human, plain, JSON) со статусом и абсолютными путями, без создания каталога. Проверить тестами для `ok`, `disabled`, `invalid` и обновить документацию команды.
- [x] 4.4 Проверить тестом на существующей read-команде, что stdout с `--json` совпадает при включённом и выключенном логировании.

## 5. Интеграционные проверки

- [x] 5.1 Запустить `make lint-all` и `make unit` без ошибок.
- [ ] 5.2 Проверить запись лога в PHAR или native-сборке с временным `--config-dir` через `make test-package` или эквивалентный smoke.
- [x] 5.3 Добавить запись в `CHANGELOG.md` (раздел «Добавлено») со ссылкой на #189 и предупреждением, что прежние версии CLI отклоняют `config.json` с `logging`.

## Workflow follow-up

- Открыть MR в `dev`, после merge и закрытия issue архивировать change и удалить worktree `/private/tmp/bitrix24-cli-file-logging`.
