# Verification

Дата: 13.11.2026. База: `origin/dev` `ff9481e`. Ветка: `feature/179-time-entry-date`.

## Контракт API

- MCP документации Битрикс24 (`bitrix-search` → `bitrix-method-details`): `task.elapseditem.add`, `update`, `get`, `getlist`, `getmanifest`. Страницы add/update документируют в `ARFIELDS` только `SECONDS`, `COMMENT_TEXT`, `USER_ID`; записываемость `CREATED_DATE` подтверждена манифестом и сырыми запросами на тестовом портале (таблица в [design.md](design.md#проверка-на-портале)).
- Код и тесты используют только `CREATED_DATE` в `ARFIELDS`, имя вывода — существующее `createdDate`. `DATE_START`/`DATE_STOP` не записываются.

## Автоматические проверки

| Проверка | Результат |
| --- | --- |
| `make check` (composer validate + lint) | OK |
| `make lint-all` (licenses, php-cs-fixer, phpstan, rector) | OK после применения rector (статические `fields()` → методы экземпляра, пустая строка в тесте) |
| `make unit` | OK, 1204 tests, 19476 assertions |
| `make cli-reference-check` | OK, 71 публичная команда; первый пример команд не изменился |
| `openspec validate add-time-entry-created-date --strict` | valid |
| `git diff --check` | OK |

Новые unit-проверки:

- `CommandContractsTest`: `time add dated` → `ARFIELDS` `SECONDS=60`, `CREATED_DATE=2026-10-22T10:00:00+03:00`; `time update dated` → `SECONDS`, `COMMENT_TEXT`, `CREATED_DATE=2026-10-21T18:00:00Z`. Прежние кейсы `time add`/`time update` без флага подтверждают неизменный payload.
- `CommandBehaviorTest::testInvalidInputNeverCallsApi`: дата без смещения, `2026-02-30…`, дата без времени, `yesterday`, пустая строка, `not-a-date` (update), update с датой без `--seconds` → exit 2, ноль вызовов API.
- `CommandBehaviorTest::testDatedTimeEntryShowsCreatedDateInPlanAndChangedFields`: dry-run без вызовов с `createdDate` в шаге плана; `changedFields` = `[seconds, createdDate]` с флагом и `[seconds]` без него; `strict-rest3` для add/update с датой → exit 4 `policy-denied` без вызовов.

## Интеграционные тесты (тестовый портал, `.env.local`)

`make integration --filter "AddTaskTimeEntryCommandTest|UpdateTaskTimeEntryCommandTest"`: OK, 7 tests, 58 assertions, 2 skipped (проверки второго пользователя без `B24CLI_TEST_RESTRICTED_WEBHOOK`/второго webhook в окружении контейнера).

- `testHappyPathBackdatesTimeEntry`: запись с `--created-date 2026-10-22T10:00:00+03:00`, readback `createdDate` с тем же моментом времени.
- `testHappyPathCorrectsEntryDateAndKeepsItWhenOmitted`: update с `--created-date 2026-10-21T18:00:00Z` меняет дату; следующий update без флага сохраняет её.

Прогон вместе с `ListTaskTimeEntriesCommandTest`/`DeleteTaskTimeEntryCommandTest`: падает `ListTaskTimeEntriesCommandTest::testKnownTimeEntriesAcrossDocumentedNavigation` (portal-acceptance, навигация `NAV_PARAMS nPageSize=2` вернула 2 из 3 известных записей). Change не меняет код списка: `Rest1TimeEntryAdapter::page`, `TimeSupport`, `ListTaskTimeEntriesCommand` и сам тест не входят в diff. Код списка одинаков в `main` и `dev`. Это существующее расхождение навигации, вне scope #179.

## Не проверено

- Права второго пользователя для записи с датой (нет restricted webhook в окружении теста). Флаг не меняет маршрут и проверку прав; существующие тесты second-user не изменены.
- Ручная приёмка standalone-бинарника.

## Поставка

- PR [#217](https://github.com/mesilov/bitrix24-cli/pull/217) влит в `dev` 12.11.2026 20:36 UTC, merge commit `f316eb0`, head `ceff349`. Перед merge: 15/15 GitHub checks `SUCCESS` на `ceff349`, нерешённых review threads нет, Codex review без замечаний, `origin/dev` (`f57a0f0`) включена, запись #179 в `CHANGELOG.md` присутствует в diff.
- Issue [#179](https://github.com/mesilov/bitrix24-cli/issues/179) закрыто (completed).
- Change архивирован в `openspec/changes/archive/2026-11-13-add-time-entry-created-date/`; три ADDED требования синхронизированы в `openspec/specs/task-mvp-scope/spec.md`.
