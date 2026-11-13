# Tasks

## 1. Хранилище профилей

- [x] 1.1 Добавить в `ProfileRepository` атомарную замену credentials существующего профиля: новый файл, переключение ссылки под lock, удаление старого после публикации, обновление `connection.hostname` (design.md, решение 4). Проверка: unit-тесты замены, отказа проверки без изменений, смены hostname, отсутствия старого файла и сохранения `name`/`default.*`/`defaultProfile`/`logging`/`telemetry`.
- [x] 1.2 Добавить в `ProfileRepository` операции установки и удаления ключа (design.md, решения 7 и 8): перечитывание под lock, типизированный разбор, проверка документа runtime-валидатором, удаление пустых контейнеров, сохранение неверного `logging` для ключей вне `logging.*`, создание файла без профилей для корневого ключа. Проверка: unit-тесты на каждый тип ключа, межполевые правила, `unset name`, неверные значения со сценариев «Strict configuration values» и тест конкурентной записи с `auth:*`/`doctor`.
- [x] 1.3 Выбор профиля по умолчанию при первом входе: `defaultProfile` выставляется только при null. Проверка: unit-тесты сценариев «First login selects the profile» и «Second portal keeps the current default».

## 2. Команды auth

- [x] 2.1 Создать `src/Console/Command/Auth/{Login,Status,List,Switch,Logout}Command.php` с русскими описаниями и справкой, удалить `src/Console/Command/Connection/*`, обновить `CommandCatalog` (команды, effects, маршруты `auth:status` = `profile`+`scope`) и `TaskUsage`. Проверка: `b24cli list auth` выводит пять команд, `connection:add` даёт ошибку неизвестной команды; `HelpAndAboutTest` и `RussianHelpTest` проходят.
- [x] 2.2 Реализовать `auth:login`: ранние локальные проверки, скрытый ввод или `--webhook-stdin`, проверка `profile` и `scope` до записи, `--replace`, `--skip-check`, отказ `strict-rest3` без `--skip-check`, вывод владельца и признака профиля по умолчанию (design.md, решения 2–4). Проверка: unit-тесты с фейковым transport на сценарии «Verified login», «Existing profile requires explicit replacement», «Credential replacement preserves settings», «Strict policy and login»; секрет из fixture не появляется ни в одном потоке.
- [x] 2.3 Перевести `connection:check` в `auth:status`: принудительное обновление, `owner` через `profile`, неактивный пользователь — ошибка, форма отчёта `scopeValidation` сохраняется; добавить `'profile' => []` в `ScopeManifest`. Проверка: `ScopeValidationCommandTest` и `ScopeManifestAndStateTest` на сценарии «Webhook owner identity», «Explicit connection scope diagnostics», полнота манифеста.
- [x] 2.4 Реализовать `auth:list` (профили, отметка по умолчанию, блок фактического подключения) и `auth:switch`/`auth:logout` (design.md, решения 5 и 6). Проверка: тесты сценариев «Local connection listing» и «Logout removes the whole profile», без чтения секретов и без API-вызовов.
- [x] 2.5 Обновить все подсказки `Failure` с `connection:*` в `ProfileRepository`, `ConnectionManager`, `ScopeValidator`, `ScopeCheckingTransport`, `SdkApiTransport`. Проверка: `rg -n "connection:(add|list|show|use|remove|check)" src tests` не находит совпадений, кроме тестов на отсутствие старых команд.

## 3. Команды config

- [x] 3.1 Создать `src/Console/Command/Config/{List,Get,Set,Unset}Command.php` с закрытым списком ключей, выбором профиля (`--profile` → `B24CLI_PROFILE` → `defaultProfile`, `--env-file` → usage-error), определением корневых ключей по префиксу, защитой `defaultProfile`/`connection.*` (design.md, решения 7–9). Проверка: тесты сценариев «Configuration command set», «Supported configuration keys», «Configuration profile selection»; `b24cli list config` выводит четыре команды.
- [x] 3.2 Перенести блок `logging` из `connection:show` в `config:list`, добавить эффективные значения с источником `profile|root|default` и формат вывода `config:get` (design.md, решение 10). Проверка: `FileLoggingTest` и новые тесты сценариев «Effective settings in config:list»; каталог логов не создаётся.
- [x] 3.3 Обновить `user-workflows` USR-04: интеграционный тест или фикстура `config:set default.users.*` + `config:list`. Проверка: сценарий «Keep portal-specific roles» без API-вызовов.

## 4. Документация

- [x] 4.1 Переименовать `docs/connection.md` → `docs/auth.md` и `docs/connection-scopes.md` → `docs/auth-scopes.md`, переписать на `auth:*`, обновить все внутренние ссылки. Проверка: `rg -n "connection\.md|connection-scopes\.md" README.md docs src` пусто, кроме `docs/releases/`.
- [x] 4.2 В `docs/config.md` заменить «Настройки меняют вручную» разделом о командах `config:*` с таблицей ключей, типов и примеров. Обновить README (первый запуск `auth:login` + `auth:status`, таблица команд), `docs/cli-command-map.md`, `docs/users.md`, `docs/task-jtbd.md` (по правилам JTBD из скилла мейнтейнера). Проверка: сценарий «No removed commands in docs» — `rg -n "connection:" README.md docs -g '!docs/releases/**'` пусто.
- [x] 4.3 Добавить запись в `CHANGELOG.md` (`Unreleased`, раздел о ломающих изменениях) с таблицей соответствия `connection:*` → `auth:*`/`config:*` и ссылкой на issue. Проверка: `git diff origin/dev...HEAD -- CHANGELOG.md` содержит запись.

## 5. Интеграционная проверка

- [x] 5.1 Прогнать PHPUnit, PHPStan, PHP-CS-Fixer, Rector в режиме dry-run и `openspec validate restructure-auth-config-commands --strict`. Проверка: все команды успешны, вывод сохранён в отчёт verify.
- [ ] 5.2 Приёмка на тестовом портале: `auth:login` (интерактивно и `--webhook-stdin`), вебхук без scope `user` (владелец через `profile`), неверный вебхук не сохраняется, `auth:login --replace`, `auth:status --json` с exit 4 при нехватке прав, `config:set` значений по умолчанию и `task:add --dry-run` с ними, `auth:logout`. Проверка: evidence без секретов, поведение совпадает со сценариями.
- [x] 5.3 Проверить трассировку: issue создано, его URL в разделе `## GitHub issues` proposal, раздел `## OpenSpec` в issue, milestone и метка назначены, ветка переименована в `feature/<issueID>-auth-config-commands`. Проверка: `gh issue view <ID> --json body,milestone,labels`.

## Workflow follow-up

- После merge этого change перевести change `explain-webhook-setup` (#152) на `auth:login` и `docs/auth.md`.
- Синхронно с релизом обновить `bitrix24-cli-skill` и сценарии агентов на новые команды.
- MR в `dev` с проверкой CHANGELOG. После merge — архивация change, обновление пути в issue, удаление worktree.
