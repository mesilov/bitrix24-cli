# Proposal

## Why

Пространство `connection:*` смешивает два разных понятия: учётные данные с выбором портала и настройки. Из-за этого:

- одно значение по умолчанию (`default.task.groupId`, `default.users.observerId`) меняется только через `connection:remove` + `connection:add`, с повторным вводом вебхука и потерей остальных значений;
- вебхук нельзя заменить, сохранив настройки профиля;
- корневые `logging` и `telemetry` правят вручную в `config.json`, а смотрят через `connection:show`;
- первый запуск — это три команды (`add` → `use` → `users:current`), и ошибка в вебхуке видна только на первом реальном запросе.

Привычная семантика `gh` и `glab` (`auth login/status/switch/logout` + `config get/set`) решает это разделением. До 1.0 CLI может менять команды без слоя совместимости, поэтому переименование делаем сразу целиком.

## What Changes

- **BREAKING** Удаляются `connection:add`, `connection:list`, `connection:show`, `connection:use`, `connection:remove`, `connection:check`. Алиасов и скрытых команд не остаётся.
- Новое пространство `auth:*` для учётных данных и выбора портала:
  - `auth:login NAME [--webhook-stdin] [--replace] [--skip-check]` — сохраняет вебхук. По умолчанию до записи проверяет его на портале методами `profile` и `scope`: неверный вебхук не сохраняется, нехватка прав сохраняет профиль с предупреждением. Если профиль по умолчанию не выбран, новый профиль становится им. `--replace` меняет вебхук существующего профиля, не трогая его настройки. Флаги значений по умолчанию в `login` не принимаются.
  - `auth:status [NAME] [--dry-run]` — прежний `connection:check`: всегда обновляет снимок прав, показывает владельца вебхука (`owner` с полями REST 1.0 `ID`, `NAME`, `LAST_NAME`, `ADMIN`), выданные и недостающие права и затронутые команды. При нехватке прав завершается с exit 4.
  - `auth:list [NAME]` — локально и без сети: сохранённые профили, hostname, отметка «по умолчанию», плюс блок фактического подключения текущего вызова (profile/env/file).
  - `auth:switch NAME` — выбрать профиль по умолчанию.
  - `auth:logout NAME` — удалить профиль и его credentials. Вебхук на портале не отзывается.
- Новое пространство `config:*` для настроек:
  - `config:list` — эффективные настройки выбранного профиля и корневые `logging`/`telemetry` с источником значения. Блок `logging` со статусом и путём файла переезжает сюда из `connection:show`.
  - `config:get KEY`, `config:set KEY VALUE`, `config:unset KEY`. Ключи — пути в `config.json`, имена полей REST 3.0 без переименования: `name`, `default.task.groupId|creatorId|responsibleId|allowsTimeTracking`, `default.users.observerId|managerId|autoAddObserver`, `logging.enabled|level|directory|fileMask|retentionDays`, `telemetry.collectProblemTrajectories|maxProblemTrajectoryFiles`.
  - Профиль выбирается через `--profile`, иначе `B24CLI_PROFILE`, иначе `defaultProfile`. Корневые ключи определяются по префиксу `logging.`/`telemetry.`. Значения проверяются теми же строгими правилами, что и при чтении файла. Запись атомарна, идёт под общей блокировкой и сохраняет остальное содержимое.
- В манифест прав добавляется маршрут REST 1.0 `profile` без scope: через него `auth:login` и `auth:status` узнают владельца вебхука.
- Справка, подсказки в ошибках (`connection-unavailable`, `scope-missing`, `scope-check-unavailable`, `permission-denied`, «профиль уже существует», «профиль не найден»), README, `docs/` и `CHANGELOG.md` переводятся на новые команды. До 1.0 README описывает только текущие команды, без упоминаний прежних.

Вне объёма: формат `config.json`, `state.json` и credential files не меняется; меняются только команды. OAuth, мастер первого запуска и ASCII-заставка сюда не входят.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `connection-configuration`:
  - удаляются требования о `connection:*` и замене профиля через `remove` + `add`;
  - добавляются требования о командах `auth:*` (проверка при входе, выбор по умолчанию, замена вебхука, выход, локальный список) и `config:*` (ключи, строгие значения, атомарная запись, `logging` в `config:list`);
  - в требованиях о защите credentials, вводе секрета, диагностике, ролях, telemetry и logging имена команд заменяются на новые.
- `connection-scope-validation`:
  - явная диагностика переходит на `auth:status` и показывает владельца вебхука;
  - проверка в `auth:login` пишет снимок прав;
  - офлайн-граница распространяется на `auth:list|switch|logout`, `config:*` и `auth:login --skip-check`;
  - `strict-rest3` отклоняет сетевую проверку `auth:login`.
- `user-workflows`: USR-04 настраивает и проверяет рабочих адресатов через `config:set`/`config:list`.

## Impact

- Код:
  - `src/Console/Command/Connection/*` заменяются на `Auth/*` и `Config/*`;
  - `ConnectionCommandRunner` делится на runner'ы auth и config;
  - `ProfileRepository` получает атомарные операции замены credentials, установки и удаления ключа;
  - `CommandCatalog` (команды, effects, маршруты `auth:status`/`auth:login`), `ScopeManifest` (`profile` → без scope), `TaskUsage`, тексты `Failure`.
- Тесты: `ConnectionProfilesTest`, `ScopeValidationCommandTest`, `FileLoggingTest`, `HelpAndAboutTest`, `RussianHelpTest`, `ConfigSchemaContractTest`, `tests/Integration/Connection/CheckConnectionCommandTest.php`.
- Сборка и справка: `build/tests/connection-smoke.py` и `build/tests/runtime-probe.php` (native-приёмка подключений), `scripts/cli-reference.php` и `docs/cli-command-map.md`.
- Документация:
  - `README.md`;
  - `docs/connection.md` → `docs/auth.md`, `docs/connection-scopes.md` → `docs/auth-scopes.md`;
  - `docs/config.md` (раздел о командах `config:*` вместо ручной правки);
  - `docs/cli-command-map.md`, `docs/task-jtbd.md`, `docs/users.md`, `CHANGELOG.md`.
- Внешние потребители: скилл [bitrix24-cli-skill](https://github.com/mesilov/bitrix24-cli-skill) и все агентные сценарии, вызывающие `connection:*`. Их обновляют отдельным изменением в том репозитории в день релиза.
- Связанный change `explain-webhook-setup` ([#152](https://github.com/mesilov/bitrix24-cli/issues/152), ветка `feature/152-webhook-setup-guide`) описывает `connection:add`. Его нужно перевести на `auth:login` после merge этого change: подсказка перед скрытым вводом и раздел о вебхуке переезжают в `auth:login` и `docs/auth.md`.
- API: добавляется только чтение `profile` (REST 1.0, без scope) в `auth:login` и `auth:status`.

## GitHub issues

- [#214](https://github.com/mesilov/bitrix24-cli/issues/214) — «Команды auth:* и config:* вместо connection:*». Change покрывает всё issue: удаление `connection:*`, команды `auth:*` с проверкой при входе и заменой вебхука, команды `config:*`, документацию и CHANGELOG. Приёмка на портале — задача 5.2.
- [#152](https://github.com/mesilov/bitrix24-cli/issues/152) — связанное issue, его покрывает отдельный change `explain-webhook-setup`. Этот change сокращает первый запуск, а подсказка о вебхуке переедет в `auth:login` после merge.
