# Verification

Ветка `feature/214-auth-config-commands` от `dev` ff9481e, 13.11.2026. Реализация — коммит efc8827; затем в ветку влита `origin/dev` aa2397c (#148, #179, #185 и их архивы). Таблица ниже — проверки на efc8827, раздел «После merge origin/dev» — повтор на объединённом дереве.

## Локальные проверки

| Проверка | Результат |
| --- | --- |
| `make unit` до изменений (база ff9481e) | OK, 1192 теста, 19 409 проверок |
| `vendor/bin/phpunit --testsuite unit-tests` после изменений | OK, 1251 тест, 20 705 проверок |
| `vendor/bin/phpstan analyse --memory-limit=2G` | No errors |
| `vendor/bin/php-cs-fixer check` | 0 файлов к исправлению из 467 |
| `vendor/bin/rector process --dry-run` | без изменений |
| `vendor/bin/composer-license-checker` | все лицензии разрешены |
| `php scripts/cli-reference.php --check` | карта соответствует 74 публичным командам |
| `build/tests/config-schema-check.py` (jsonschema 4.25.1) | 176 общих fixtures, 4 примера `docs/config.md` |
| `openspec validate restructure-auth-config-commands --strict` | valid |

Проверочные поиски из tasks.md пусты:

- `grep -rnE "connection:(add|list|show|use|remove|check)" src tests`;
- `grep -rnE "connection\.md|connection-scopes\.md" README.md docs src` (кроме `docs/releases/`);
- `grep -rn "connection:" README.md docs` (кроме `docs/releases/`);
- справка всех девяти команд `auth:*` и `config:*` не содержит `connection:`.

## После merge origin/dev

`git merge --no-edit origin/dev` (aa2397c) дал два конфликта:

- `docs/cli-command-map.md` — файл сгенерирован заново (`php scripts/cli-reference.php --write`);
- `src/Console/ResultPresenter.php` — объединены выбор строк `config-settings` из этой ветки и профиль `chat-created` из #185.

| Проверка | Результат |
| --- | --- |
| `vendor/bin/phpunit --testsuite unit-tests` | OK, 1306 тестов, 21 115 проверок |
| `vendor/bin/phpstan analyse --memory-limit=2G` | No errors |
| `vendor/bin/php-cs-fixer check` | 0 файлов к исправлению из 478 |
| `vendor/bin/rector process --dry-run` | без изменений |
| `vendor/bin/composer-license-checker` | все лицензии разрешены |
| `php scripts/cli-reference.php --check` | карта соответствует 75 публичным командам |
| `build/tests/config-schema-check.py` | 176 общих fixtures, 4 примера |
| `openspec validate restructure-auth-config-commands --strict` | valid |
| Проверочные поиски `connection:` и ссылок на `connection*.md` | пусто |

Перед открытием MR `origin/dev` ушла до 985bc01 (#141, относительные сроки): повторный merge прошёл без конфликтов (0914cc6). На этом дереве: 1374 теста и 21 519 проверок — OK; PHPStan, php-cs-fixer (483 файла), Rector, лицензии — без замечаний; карта CLI — 75 команд; проверочные поиски пусты.

Native smoke и приёмка на портале после merge не повторялись: `origin/dev` не менял `build/tests/`, хранилище профилей и транспорт прав.

## Ревью

Замечание Codex к MR #221 (P2): `config:set … --config runtime.json` с `{"profile":"other"}` менял профиль `other`, хотя цепочка выбора — `--profile` → `B24CLI_PROFILE` → `defaultProfile`. Воспроизведено на 693ac16. Исправлено: `config:*` учитывает только опцию командной строки `--profile`, `profile` из runtime `--config` игнорируется; требование, design и `docs/config.md` уточнены, добавлен сценарий «Runtime config profile does not redirect settings» и проверки в `ConfigCommandsTest::testProfileSelectionIgnoresWebhooksAndEnvironmentFiles`.

## Покрытие сценариев тестами

| Требование | Тесты |
| --- | --- |
| Authentication command set, Removed connection commands | `ConnectionProfilesTest::testLocalCommandsAreRegisteredAndHaveSafeOutputInAllModes`, `testRemovedConnectionCommandsAreUnknown`, `RussianHelpTest`, `HelpAndAboutTest` |
| Verified login, Login stores the first snapshot | `AuthLoginTest` (10 тестов через реальный SDK-стек и `MockHttpClient`): успешный вход, `INVALID_CREDENTIALS`/`NO_AUTH_FOUND`/`ACCESS_DENIED`/transport/неактивный пользователь, нехватка прав, недоступный `scope`, `--skip-check` |
| Default profile on first login | `ConnectionProfilesTest::testFirstLoginSelectsTheProfileAndSecondPortalKeepsTheDefault`, `AuthLoginTest::testSecondPortalKeepsTheDefaultAndHumanOutputNamesTheOwner` |
| Existing profile requires explicit replacement, Concurrent logins | `ConnectionProfilesTest::testExistingProfileRejectsLoginBeforeWebhookInputAndPreservesFiles`, `testRepositoryLoginUsesTheSameDiagnosticAndCreatesNoCredential`, `testConcurrentRepositoryLoginsDoNotOverwriteTheWinner` |
| Credential replacement preserves settings | `ConnectionProfilesTest::testReplaceRotatesOnlyTheSecretAndKeepsSettings`, `AuthLoginTest::testReplacementVerifiesTheNewWebhookAndNeverInheritsTheOldSnapshot`, `testFailedLoginLeavesExistingConfigurationUntouched` |
| Logout removes the whole profile, Local connection listing | `ConnectionProfilesTest::testLogoutRemovesTheWholeProfileAndClearsOnlyAMatchingDefault`, `testListShowsProfilesAndTheEffectiveSourceWithoutSecrets` |
| Configuration command set, Supported keys, Strict values, Profile selection, Atomic updates, Effective settings | `ConfigCommandsTest` (43 теста, включая 30 наборов неверных значений и конкурентную запись пяти процессов: `config:set` профиля и корня, `login`, `use`, `doctor configure`), `FileLoggingTest::testConfigListReportsEffectiveLogging` |
| Webhook owner identity, Explicit connection scope diagnostics, Strict policy and login | `ScopeValidationCommandTest` (владелец в трёх режимах вывода, вебхук без `user`, неактивный пользователь, манифест), `AuthLoginTest::testStrictPolicyDeniesVerificationBeforeTheSecretIsRead` |
| Invalid logging does not break profile storage, Logging persistence | `FileLoggingTest::testProfileAndTelemetryWritesKeepValidAndInvalidLogging`, `testInvalidLoggingWarnsOnceAndKeepsCommandResult`, `ConfigCommandsTest::testInvalidLoggingSurvivesUnrelatedWritesAndBlocksLoggingChanges` |
| USR-04 — Keep portal-specific roles | `ConfigCommandsTest::testWorkingRolesStayWithTheirPortalProfile`, `ConnectionProfilesTest::testWorkingRolesRoundTripThroughLocalCommands` |

## Native smoke

`build/tests/connection-smoke.py` переведён на `auth:*`/`config:*`; fixture `runtime-probe connection-fixture` дополнена последовательностью `profile` → `scope`. Сценарий выполнен целиком в dev-контейнере в режиме `--phar` поверх исходников (вместо собранных PHAR — PHP-обёртки запуска `Launcher::run(..., packaged: true)` и `runtime-probe.php`): статус `passed`, включая новые проверки `config-commands`, `webhook-replacement`, `verified-login-snapshot`.

Этап документированных оболочек (Bash/zsh, PowerShell, cmd.exe) в режиме `--phar` не запускается: его команды изменены, но выполнятся только на собранных native-бинарниках.

## Приёмка на тестовом портале

Запуск из исходников в Docker (PHP 8.4), вебхуки из `.env.local` основного checkout (`BITRIX24_WEBHOOK`, `BITRIX24_SECOND_USER_WEBHOOK`) подавались в `--webhook-stdin`; временный каталог конфигурации удалён после запуска. Секреты, hostname портала и имена пользователей в отчёт не выводились.

| Шаг | Результат |
| --- | --- |
| `make integration --group command:auth:status` | OK, 1 тест, 12 проверок: `owner` с ключами `ID`, `NAME`, `LAST_NAME`, `ADMIN`, вызовы `profile` → `scope` |
| `auth:login work --webhook-stdin --json` | exit 0; вызовы `profile`, `scope`; `becameDefault=true`, `verified=true`; `owner.ID` — строка, `owner.ADMIN` — boolean (как в документации метода); снимок `fresh`, одна запись в `state.json` |
| `task:list --limit 1 --json` сразу после входа | exit 0; только `tasks.task.list`, без повторного `scope` |
| `auth:login bad` с испорченным токеном | exit 1, `api-error`, один вызов `profile`; профиль и credential file не созданы |
| `auth:login second` (вебхук второго пользователя) | exit 0; `isDefault=false`, профиль по умолчанию остался `work` |
| `auth:login work --replace --webhook-stdin` | exit 0; `replaced=true`, `becameDefault=false`; в `credentials/` два файла (прежний удалён) |
| `auth:status work` / `auth:status second` | exit 0; владелец, права, `missingScopes=[]` |
| `config:set default.task.groupId 187`, `creatorId 1`, `responsibleId 1` | exit 0, без API-вызовов |
| `task:add --title … --dry-run --json` | поля плана: `groupId=187`, `creatorId=1`, `responsibleId=1`, `allowsTimeTracking=false`, без API-вызовов |
| `auth:list --json` | два профиля, `work` по умолчанию, источник `profile`, без API-вызовов |
| `auth:logout second`, `auth:logout work` | exit 0; `wasDefault` false/true; профилей и credential files не осталось |
| `auth:login human --no-ansi` | exit 0; в stderr строки о владельце и профиле по умолчанию; токен отсутствует в stdout, stderr, `config.json` и `state.json` |

Скрытый ввод проверен отдельно через псевдотерминал: `auth:login work --skip-check` показал приглашение `HTTPS incoming webhook:`, принял значение без эха и сохранил профиль (fixture-вебхук, без портала).

## Не проверено

- Вебхук без scope `user` и `auth:status` с exit 4 на живом портале: оба вебхука окружения имеют полный набор прав. Поведение покрыто `ScopeValidationCommandTest` и `AuthLoginTest` на управляемом HTTP-fixture. Для живой проверки нужен вебхук с ограниченными правами.
- Интерактивный скрытый ввод с проверкой на портале (в терминале пользователя); проверены скрытый ввод с `--skip-check` и вход с проверкой через `--webhook-stdin`.
- Native-бинарники Linux/macOS/Windows и этап документированных оболочек smoke-теста: нужна сборка через `builds`.
