# Proposal

## Why

Standalone-бинарники для Linux, macOS и Windows уже собираются, но подключение по-прежнему привязано к process env или dotenv в project root. Пользователю нужно один раз сохранить несколько подключений и выбирать портал при запуске из любого каталога, сохраняя действующие CI/Docker сценарии.

## What Changes

- Добавить пользовательский каталог конфигурации, versioned non-secret JSON с именованными профилями и отдельные защищённые plaintext credential files.
- Спроектировать окружения `work`, `pet`, `clientA`, `clientB` как отдельные профили порталов: обязательный `name` содержит описательный домен портала, обязательный вложенный `connection` — `hostname` и `credentialFile`. Запросы идут по HTTPS к `connection.hostname`; `name` не используется как адрес подключения.
- Добавить в каждый профиль секцию `default.task`: опциональные положительные `groupId`, `creatorId`, `responsibleId` и boolean `allowsTimeTracking` с дефолтом false. Перенести прежний profile-root groupId в `default.task.groupId`; defaults используются только для `task:add` фактически выбранного профиля. Явные поля команды выше defaults; ключи defaults совпадают с полями REST 3.0: `creatorId` — автор/постановщик, `responsibleId` — исполнитель. Required fields проверяются после подстановки; отсутствующие автор/исполнитель не выводятся из webhook.
- Использовать `default.task.groupId` в config и `fields.groupId` в API; `--group-id` поставлен отдельно в [#27](https://github.com/mesilov/bitrix24-cli/issues/27), [PR #31](https://github.com/mesilov/bitrix24-cli/pull/31). Старый `--project` удалён без alias; профильные defaults входят в этот change.
- Расширить существующий `--profile` (сейчас только default); добавить `--env-file`, `--config-dir` и `connection:add/list/show/use/remove`.
- **BREAKING:** прежний `--profile default` / `config.profile=default` становится явным выбором сохранённого профиля; для legacy env убрать selector либо создать профиль.
- Сохранить `--config` как non-secret runtime JSON и его profile selector, а также legacy process env / SDK alias / `B24CLI_ENV_FILE` / source dotenv.
- Явный selector выбирает одно подключение и исключает ambient credentials; некорректный выбранный источник не вызывает fallback.
- Исключить автоматический поиск dotenv в cwd/PHAR для packaged launcher; не сохранять секреты в argv, вывод или пакеты.
- Документировать приоритеты, настройку на трёх OS и результаты исследования шести CLI в `research.md`.
- Описать в `design.md` пользовательский путь с командами и состоянием файлов после add/use/read/remove; credential references отсчитываются от конфигурационного каталога.
- Опубликовать `docs/configuration.md` с описанием schema v1 и `docs/config.schema.json` (JSON Schema Draft 2020-12); проверить общие positive/negative fixtures через schema validator и runtime, отдельно описать проверки файлов/permissions/defaultProfile references вне JSON Schema.

## Capabilities

### New Capabilities

- `connection-configuration`: хранение, локальное управление и детерминированный выбор подключения для source и standalone запусков.

### Modified Capabilities

Нет. `cli-experience` уже требует единых global options, защищённых credentials и отдельного auth/storage механизма; `binary-distribution` уже требует независимого запуска и исключения секретов из пакета. Эти контракты сохраняются.

## Impact

- `src/Infrastructure/Connection/`, `config/services.php`, `B24Application`, `CommandRunner::context`, `CommandCatalog`, bootstrap/source vs packaged execution context; `TaskInputMapper`/контекст `AddTaskRequest` для profile-local task defaults, подстановки одноимённых API fields перед required-field validation без изменения REST маршрутов. Переименование явного флага выполняется отдельно в #27.
- Connection unit/subprocess tests, native packaging smoke на Linux amd64/macOS arm64/Windows amd64, README/help и Compose wrapper forwarding.
- v1 использует существующий Symfony Dotenv/JSON и файловый credential backend; OS keyring/helpers и OAuth вне объёма.
- Запрос 12.10.2026 включает реализацию полного change, проверку и закрытие #19 через PR в dev.

## GitHub issues

- [#19](https://github.com/mesilov/bitrix24-cli/issues/19): параметры подключения, профили, запуск бинарника на трёх OS, сравнение других CLI, структура конфига нескольких окружений, описательный домен, вложенный connection и `default.task` с groupId/creatorId/responsibleId/allowsTimeTracking.
- Связанная упаковка: [#14](https://github.com/mesilov/bitrix24-cli/issues/14).

- [#27](https://github.com/mesilov/bitrix24-cli/issues/27) — поставленное отдельное удаление --project и замена на --group-id без alias ([PR #31](https://github.com/mesilov/bitrix24-cli/pull/31)); архив `openspec/changes/archive/2026-10-11-unify-group-id-option/`.

## Baseline

Актуальная `dev`: `56654f1310b75580362bb5ad58b00ad63d006363`, проверена 11.10.2026. Артефакты создаются локально в отдельном worktree `feature/19-cross-platform-connection-profiles`; наличие плана не подтверждает реализацию.
