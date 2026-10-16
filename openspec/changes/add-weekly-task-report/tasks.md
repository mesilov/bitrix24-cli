# Tasks

Постановка подготовлена и влита в dev отдельно в MR #169. Issue #168 связан с change; ниже — фактическое состояние реализации в отдельной ветке `feature/168-weekly-task-report`.

## 1. Трассировка и вход команды

- [x] 1.1 Создать issue #168, добавить ссылки в proposal/CHANGELOG и проверить enhancement/milestone 0.3.0 и обратную ссылку `add-weekly-task-report`; evidence readback сохранён в planning-check.md. Issue types в репозитории недоступны (repository.issueTypes=null).
- [x] 1.2 После свежего fetch/sync dev создать ветку `feature/<issue-id>-weekly-task-report` в отдельном worktree и сверить текущие каталог/смежный #141; проверить чистый baseline и записать SHA в change.
- [x] 1.3 Добавить `ReportTasksCommand`, mapper/request с обязательным group-id и optional responsible, week, timezone, max-scan без default.task responsible; проверить missing/invalid/duplicate args и отсутствие API при usage-error в command unit-тесте, добавить примеры input в help и docs/task-usage.md.
- [x] 1.4 Реализовать календарный ReportWeekResolver с фиксированным Clock; проверить last-week/this-week/ISO date, DST, новый год, invalid/future dates, effectiveTo и timezone default; описать границы в пользовательском руководстве отчёта.

## 2. REST1 чтение и полнота

- [x] 2.1 Добавить WeeklyReportTaskGateway и adapter над общим classic list primitive без изменения семантики deadline-команд; payload-тестом проверить GROUP_ID, optional RESPONSIBLE_ID, REAL_STATUS, CREATED_DATE/CLOSED_DATE, select, ID ASC и start; регрессионно выполнить TaskDeadlineFilterTest.
- [x] 2.2 Реализовать четыре потока с общим max-scan и seen IDs; fake gateway тестами проверить >50 строк, монотонное продолжение, duplicate/no-progress/empty-next, общий бюджет и точное завершение на границе.
- [x] 2.3 Реализовать per-section complete/count/null/observedCount/reasons и сохранение данных при page-error; тестами проверить отсутствующие поля, неверные даты, конфликт scope/status/duplicate, unscanned sections и cancellation/exit 130.
- [x] 2.4 Подключить заранее объявленный read route, API policy и scope manifest; unit-тестами проверить task scope, strict-rest3 до credentials/API, scope-missing, отсутствие write/fallback и штатную scope validation.

## 3. Четыре показателя и форматы

- [x] 3.1 Реализовать агрегацию closed/new и двух текущих статусов; прямыми unit-тестами проверить пересечение new+closed, date equality, reopened exclusion, old awaiting-control, pending/deferred exclusion, whole project и responsible AND.
- [x] 3.2 Добавить reportAt/observedUntil/period/scope metadata и обнаружение конфликтующих текущих статусов между потоками; тестом доказать partial только затронутых секций и отсутствие обещания исторического/атомарного snapshot в выводе и документации.
- [x] 3.3 Реализовать четыре блока human, TSV recordType task/summary и envelope JSON без скрытого output-limit; formatter-тестами сверить одинаковые ID/counts/partial, экранирование, null против 0, stderr metadata и подписи текущих показателей.

## 4. Каталог, документация и приёмка

- [x] 4.1 Зарегистрировать task:report как read и отдельный ReportTasksCommandTest в integration suite; проверить IntegrationCommandCoverageTest и smoke harness, актуализировать все затронутые inventories от свежего baseline (сейчас план 50 task / 64 public).
- [x] 4.2 Обновить docs/task-jtbd.md: PM-09, AM-03, дату/SHA, статус команды, ссылку на реальный integration class и границы приёмки; сверить 25 JTBD (9/7/9), CLI map, help/completion, README/navigation и содержательный CHANGELOG со ссылкой на issue.
- [x] 4.3 Запустить относящиеся unit/formatter/API payload тесты, общие обязательные offline checks и `openspec validate add-weekly-task-report --strict`; сохранить команды, числа и exit codes в verification.md, не считать skipped integration tests live-приёмкой.
- [ ] 4.4 На разрешённом тестовом подключении проверить весь проект и responsible subset, все четыре показателя, native payload/пагинацию, созданную/закрытую задачу и повторное открытие; сохранить sanitized evidence, очистить owned fixtures. Если живые проверки не разрешены или неполны, явно зафиксировать конкретные границы.
- [x] 4.5 Выполнить openspec-verify-change, свежий fetch/merge dev и проверку issue↔change/JTBD/API-field/changelog связности перед MR; повторить затронутые проверки и проверить exact head mandatory CI и review requirements перед merge.

## Workflow follow-up

- После merge реализации в dev и проверки закрытия issue синхронизировать delta specs и архивировать change через openspec-archive-change; сохранить архив/синхронизацию в dev отдельным MR, если требуется.
- Обновить ссылку issue и PM-09 на фактические канонические/архивные пути, проверить обратную трассировку и удалить только связанный worktree после сохранения всех данных. Этот planning-этап change не архивирует.
